<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistSeccao;
use App\Models\Inspecao;
use App\Models\Posto;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InspecaoController extends Controller
{
    /**
     * GET /api/inspecoes
     */
    public function index(Request $request)
    {
        $query = Inspecao::query()->with([
            'veiculo:id,matricula,marca,modelo,ano,cor,proprietario_nome,proprietario_numero_id,chassis,combustivel',
            'posto:id,nome',
        ]);

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo', 'like', "%{$q}%")
                    ->orWhereHas('veiculo', fn ($v) => $v->where('matricula', 'like', "%{$q}%"))
                    ->orWhereHas('posto', fn ($p) => $p->where('nome', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('resultado')) {
            $query->where('resultado', $request->resultado);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('posto')) {
            $query->whereHas('posto', fn ($p) => $p->where('nome', $request->posto));
        }

        if ($request->filled('veiculo_id')) {
            $query->where('veiculo_id', $request->veiculo_id);
        }

        $items = $query->orderByDesc('data')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($i) => $this->formatar($i));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/inspecoes/kpis
     */
    public function kpis()
    {
        $r = Inspecao::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN resultado = 'Aprovada' THEN 1 ELSE 0 END) as aprovadas,
            SUM(CASE WHEN resultado = 'Aprovada com observações' THEN 1 ELSE 0 END) as aprovadas_obs,
            SUM(CASE WHEN resultado = 'Reprovada' THEN 1 ELSE 0 END) as reprovadas,
            SUM(CASE WHEN resultado = 'Pendente'  THEN 1 ELSE 0 END) as pendentes
        ")->first();

        $total  = (int) $r->total;
        $aprov  = (int) $r->aprovadas + (int) $r->aprovadas_obs;
        $reprov = (int) $r->reprovadas;

        return [
            'total'         => $total,
            'aprovadas'     => $aprov,
            'reprovadas'    => $reprov,
            'pendentes'     => (int) $r->pendentes,
            'taxaAprovacao' => $total > 0 ? round(($aprov / $total) * 100, 1) : 0,
        ];
    }

    /**
     * POST /api/inspecoes
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'veiculo_id'  => 'required|integer|exists:veiculos,id',
            'posto_id'    => 'nullable|integer|exists:postos,id',
            'posto'       => 'nullable|string|max:120',
            'data'        => 'required|date',
            'tipo'        => 'nullable|string|max:40',
            'resultado'   => 'nullable|string|in:Aprovada,Aprovada com observações,Reprovada',
            'inspetor'    => 'required|string|max:120',
            'observacoes' => 'nullable|string',
            'checklist'   => 'nullable|array',
        ]);

        // Resolver posto
        $postoId = $dados['posto_id'] ?? null;
        if (!$postoId && !empty($dados['posto'])) {
            $postoId = Posto::where('nome', $dados['posto'])->value('id');
        }

        // Calcular meta (aprovados / falhas / total) com base na BD
        $checklist = $dados['checklist'] ?? [];
        $meta      = $this->calcularMeta($checklist);

        // Sugerir resultado se o user não passou
        $resultado = $dados['resultado'] ?? $this->sugerirResultado($meta);

        $dataInspecao = Carbon::parse($dados['data']);
        $validade = $resultado === 'Reprovada'
            ? $dataInspecao->copy()->addDays(30)
            : $dataInspecao->copy()->addYear();

        $inspecao = DB::transaction(function () use (
            $dados, $postoId, $validade, $dataInspecao, $checklist, $meta, $resultado
        ) {
            $checklist['meta'] = $meta;

            $inspecao = Inspecao::create([
                'veiculo_id'       => $dados['veiculo_id'],
                'posto_id'         => $postoId,
                'data'             => $dataInspecao->toDateString(),
                'validade'         => $validade->toDateString(),
                'proxima_inspecao' => $validade->toDateString(),
                'resultado'        => $resultado,
                'estado'           => $resultado === 'Reprovada' ? 'expirada' : 'valida',
                'tipo'             => $dados['tipo'] ?? 'Periódica',
                'observacoes'      => $dados['observacoes'] ?? null,
                'checklist'        => $checklist,
                'inspetor'         => $dados['inspetor'],
            ]);

            $inspecao->update([
                'codigo' => sprintf('INS-%s-%06d', $dataInspecao->format('Y'), $inspecao->id),
            ]);

            // Atualizar estado do veículo
            Veiculo::where('id', $dados['veiculo_id'])->update([
                'estado'               => $resultado === 'Reprovada' ? 'critico' : 'regular',
                'score'                => $resultado === 'Reprovada' ? 30 : 90,
                'ultima_sincronizacao' => now(),
            ]);

            return $inspecao;
        });

        return response()->json([
            'id'     => (string) $inspecao->id,
            'codigo' => $inspecao->codigo,
            'meta'   => $meta,
            'kpis'   => $this->kpis(),
        ], 201);
    }

    /**
     * GET /api/inspecoes/{id}
     */
    public function show(int $id)
    {
        $i = Inspecao::with([
            'veiculo:id,matricula,marca,modelo,ano,cor,proprietario_nome,proprietario_numero_id,chassis,combustivel',
            'posto:id,nome',
        ])->findOrFail($id);

        return response()->json($this->formatar($i));
    }

    // ============================================================
    // Helpers privados (só existem dentro desta classe)
    // ============================================================

    /**
     * Formata uma inspeção para JSON (shape que o frontend espera).
     */
    private function formatar(Inspecao $i): array
    {
        $v = $i->veiculo;

        return [
            'id'              => (string) $i->id,
            'codigo'          => $i->codigo,
            'veiculo_id'      => (string) ($v?->id ?? ''),
            'matricula'       => $v?->matricula ?? '—',
            'marca'           => $v?->marca ?? '—',
            'modelo'          => $v?->modelo ?? '—',
            'ano'             => $v?->ano,
            'cor'             => $v?->cor ?? '—',
            'chassis'         => $v?->chassis ?? '—',
            'proprietario'    => $v?->proprietario_nome ?? '—',
            'nif'             => $v?->proprietario_numero_id ?? '—',
            'data'            => $i->data?->format('d/m/Y') ?? '—',
            'data_raw'        => $i->data?->format('Y-m-d'),
            'validade'        => $i->validade?->format('d/m/Y') ?? '—',
            'proximaInspecao' => $i->proxima_inspecao?->format('d/m/Y') ?? '—',
            'tipo'            => $i->tipo ?? 'Periódica',
            'resultado'       => $i->resultado ?? '—',
            'estado'          => $i->estado ?? '—',
            'posto'           => $i->posto?->nome ?? '—',
            'inspetor'        => $i->inspetor ?? '—',
            'observacoes'     => $i->observacoes,
            'checklist'       => $i->checklist ?? [],
            'meta'            => $i->checklist['meta'] ?? null,
        ];
    }

    /**
     * Conta aprovados/falhas a partir da estrutura da BD.
     */
    private function calcularMeta(array $checklist): array
    {
        $seccoes = ChecklistSeccao::with([
            'campos' => fn ($q) => $q->where('ativo', true),
        ])
            ->where('ativo', true)
            ->get();

        $aprovados = 0;
        $falhas    = 0;

        foreach ($seccoes as $s) {
            $valores = $checklist[$s->chave] ?? [];

            foreach ($s->campos as $campo) {
                $v = $valores[$campo->chave] ?? null;

                // ---------- BOOLEANO ----------
                if ($campo->tipo === 'booleano') {
                    if ($campo->invertido) {
                        // invertido: true = falha
                        if ($v === true)       $falhas++;
                        elseif ($v === false)  $aprovados++;
                    } else {
                        // normal: true = aprovado
                        if ($v === true)       $aprovados++;
                        elseif ($v === false)  $falhas++;
                    }
                    continue;
                }

                // ---------- NUMÉRICO ----------
                if (is_numeric($v)) {
                    $num = (float) $v;

                    if ($campo->min !== null && $num < (float) $campo->min) {
                        $falhas++;
                        continue;
                    }
                    if ($campo->max !== null && $num > (float) $campo->max) {
                        $falhas++;
                        continue;
                    }
                    $aprovados++;
                    continue;
                }

                // ---------- TEXTO / OPÇÕES ----------
                if (is_string($v) && trim($v) !== '') {
                    $aprovados++;
                }
            }
        }

        return [
            'aprovados' => $aprovados,
            'falhas'    => $falhas,
            'total'     => $aprovados + $falhas,
        ];
    }

    /**
     * Sugere resultado com base na meta.
     */
    private function sugerirResultado(array $meta): string
    {
        if ($meta['falhas'] === 0) return 'Aprovada';
        if ($meta['falhas'] <= 2)  return 'Aprovada com observações';
        return 'Reprovada';
    }
}