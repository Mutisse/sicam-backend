<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Apolice;
use App\Models\Sinistro;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class IssmSinistroController extends Controller
{
    /**
     * GET /api/issm/sinistros
     */
    public function index(Request $request)
    {
        $query = Sinistro::query()->with('apolice:id,numero');

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('matricula', 'like', "%{$q}%")
                    ->orWhere('segurado', 'like', "%{$q}%");
            });
        }

        if ($request->filled('tipo'))       $query->where('tipo', $request->tipo);
        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('gravidade'))  $query->where('gravidade', $request->gravidade);
        if ($request->filled('seguradora')) $query->where('seguradora', $request->seguradora);

        $items = $query
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->map(fn ($s) => $this->formatar($s));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/issm/sinistros/{id}
     */
    public function show(int $id)
    {
        $s = Sinistro::with('apolice:id,numero')->findOrFail($id);
        return response()->json($this->formatar($s));
    }

    /**
     * POST /api/issm/sinistros
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'numero'        => 'required|string|max:40|unique:sinistros,numero',
            'matricula'     => 'required|string|max:20',
            'apolice'       => 'nullable|string|max:40',
            'segurado'      => 'required|string|max:120',
            'seguradora'    => 'required|string|max:120',
            'tipo'          => 'required|string|in:Colisão,Furto,Incêndio,Inundação,Vandalismo',
            'gravidade'     => 'required|string|in:Leve,Moderado,Grave',
            'data'          => 'required|date',
            'local'         => 'required|string|max:200',
            'descricao'     => 'required|string',
            'valorEstimado' => 'required|numeric|min:0',
            'valorAprovado' => 'nullable|numeric|min:0',
            'estado'        => 'nullable|string|in:Aberto,Em análise,Resolvido,Recusado',
            'responsavel'   => 'required|string|max:120',
            'observacoes'   => 'nullable|string',
        ]);

        // Apólice — se enviada, tem de existir
        $apoliceId = null;
        if (!empty($dados['apolice'])) {
            $apoliceId = Apolice::where('numero', $dados['apolice'])->value('id');
            if (!$apoliceId) {
                return response()->json(['message' => 'Apólice não encontrada.'], 422);
            }
        }

        // Data — parse tolerante
        $data = $this->parseData($dados['data']);
        if (!$data) {
            return response()->json(['message' => 'Data inválida.'], 422);
        }

        $s = Sinistro::create([
            'numero'         => $dados['numero'],
            'apolice_id'     => $apoliceId,
            'matricula'      => strtoupper($dados['matricula']),
            'segurado'       => $dados['segurado'],
            'seguradora'     => $dados['seguradora'],
            'tipo'           => $dados['tipo'],
            'gravidade'      => $dados['gravidade'],
            'data'           => $data,
            'local'          => $dados['local'],
            'descricao'      => $dados['descricao'],
            'valor_estimado' => $dados['valorEstimado'],
            'valor_aprovado' => $dados['valorAprovado'] ?? null,
            'estado'         => $dados['estado'] ?? 'Aberto',
            'responsavel'    => $dados['responsavel'],
            'observacoes'    => $dados['observacoes'] ?? null,
            'criado_por'     => $request->user()?->id,
        ]);

        $s->load('apolice:id,numero');

        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s),
        ], 201);
    }

    /**
     * PUT /api/issm/sinistros/{id}
     */
    public function update(Request $request, int $id)
    {
        $s = Sinistro::findOrFail($id);

        $dados = $request->validate([
            'matricula'     => 'sometimes|string|max:20',
            'apolice'       => 'nullable|string|max:40',
            'segurado'      => 'sometimes|string|max:120',
            'seguradora'    => 'sometimes|string|max:120',
            'tipo'          => 'sometimes|string|in:Colisão,Furto,Incêndio,Inundação,Vandalismo',
            'gravidade'     => 'sometimes|string|in:Leve,Moderado,Grave',
            'data'          => 'sometimes|date',
            'local'         => 'sometimes|string|max:200',
            'descricao'     => 'sometimes|string',
            'valorEstimado' => 'sometimes|numeric|min:0',
            'valorAprovado' => 'nullable|numeric|min:0',
            'estado'        => 'sometimes|string|in:Aberto,Em análise,Resolvido,Recusado',
            'responsavel'   => 'sometimes|string|max:120',
            'observacoes'   => 'nullable|string',
        ]);

        $update = [];
        if (isset($dados['matricula']))     $update['matricula']      = strtoupper($dados['matricula']);
        if (isset($dados['segurado']))      $update['segurado']       = $dados['segurado'];
        if (isset($dados['seguradora']))    $update['seguradora']     = $dados['seguradora'];
        if (isset($dados['tipo']))          $update['tipo']           = $dados['tipo'];
        if (isset($dados['gravidade']))     $update['gravidade']      = $dados['gravidade'];
        if (isset($dados['local']))         $update['local']          = $dados['local'];
        if (isset($dados['descricao']))     $update['descricao']      = $dados['descricao'];
        if (isset($dados['valorEstimado'])) $update['valor_estimado'] = $dados['valorEstimado'];
        if (array_key_exists('valorAprovado', $dados)) $update['valor_aprovado'] = $dados['valorAprovado'];
        if (isset($dados['estado']))        $update['estado']         = $dados['estado'];
        if (isset($dados['responsavel']))   $update['responsavel']    = $dados['responsavel'];
        if (array_key_exists('observacoes', $dados)) $update['observacoes'] = $dados['observacoes'];

        // Data — parse tolerante
        if (isset($dados['data'])) {
            $data = $this->parseData($dados['data']);
            if (!$data) {
                return response()->json(['message' => 'Data inválida.'], 422);
            }
            $update['data'] = $data;
        }

        // Apólice — permite desassociar (null ou string vazia) e associar (número válido)
        if (array_key_exists('apolice', $dados)) {
            if (empty($dados['apolice'])) {
                $update['apolice_id'] = null;
            } else {
                $apoliceId = Apolice::where('numero', $dados['apolice'])->value('id');
                if (!$apoliceId) {
                    return response()->json(['message' => 'Apólice não encontrada.'], 422);
                }
                $update['apolice_id'] = $apoliceId;
            }
        }

        $s->update($update);
        $s->load('apolice:id,numero');

        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s),
        ]);
    }

    /**
     * DELETE /api/issm/sinistros/{id}
     */
    public function destroy(int $id)
    {
        $s = Sinistro::findOrFail($id);
        $s->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * PUT /api/issm/sinistros/{id}/em-analise
     */
    public function marcarEmAnalise(int $id)
    {
        $s = Sinistro::findOrFail($id);
        $s->update(['estado' => 'Em análise']);
        $s->load('apolice:id,numero');

        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s),
        ]);
    }

    /**
     * PUT /api/issm/sinistros/{id}/resolver
     */
    public function marcarResolvido(Request $request, int $id)
    {
        $dados = $request->validate([
            'valorAprovado' => 'nullable|numeric|min:0',
        ]);

        $s = Sinistro::findOrFail($id);

        $update = ['estado' => 'Resolvido'];
        if (array_key_exists('valorAprovado', $dados) && $dados['valorAprovado'] !== null) {
            $update['valor_aprovado'] = $dados['valorAprovado'];
        }

        $s->update($update);
        $s->load('apolice:id,numero');

        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s),
        ]);
    }

    /**
     * PUT /api/issm/sinistros/{id}/recusar
     */
    public function recusar(int $id)
    {
        $s = Sinistro::findOrFail($id);
        $s->update(['estado' => 'Recusado']);
        $s->load('apolice:id,numero');

        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s),
        ]);
    }

    /**
     * GET /api/issm/sinistros/xlsx
     */
    public function xlsx(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Nº Sinistro', 'Matrícula', 'Segurado', 'Seguradora',
            'Tipo', 'Gravidade', 'Data', 'Valor Estimado', 'Estado', 'Responsável',
        ];

        $linhas = $items->map(fn ($s) => [
            $s->numero,
            $s->matricula,
            $s->segurado,
            $s->seguradora,
            $s->tipo,
            $s->gravidade,
            $s->data?->format('d/m/Y') ?? '—',
            (string) $s->valor_estimado,
            $s->estado,
            $s->responsavel,
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Sinistros'),
            'issm-sinistros-' . date('Y-m-d') . '.xlsx',
        );
    }

    /**
     * GET /api/issm/sinistros/pdf
     */
    public function pdf(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Nº Sinistro', 'Matrícula', 'Seguradora',
            'Tipo', 'Gravidade', 'Data', 'Estado',
        ];

        $linhas = $items->map(fn ($s) => [
            $s->numero,
            $s->matricula,
            $s->seguradora,
            $s->tipo,
            $s->gravidade,
            $s->data?->format('d/m/Y') ?? '—',
            $s->estado,
        ])->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Instituto de Supervisão de Seguros de Moçambique',
            'sigla'       => 'ISSM',
            'rodape'      => 'SICAM — ISSM · Sinistros',
            'titulo'      => 'Sinistros',
            'subtitulo'   => 'Registo de sinistros associados a apólices',
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Total'      => $items->count(),
                'Abertos'    => $items->where('estado', 'Aberto')->count(),
                'Em análise' => $items->where('estado', 'Em análise')->count(),
                'Resolvidos' => $items->where('estado', 'Resolvido')->count(),
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('issm-sinistros-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Sinistro $s): array
    {
        return [
            'id'            => (string) $s->id,
            'numero'        => $s->numero,
            'matricula'     => $s->matricula,
            'apolice'       => $s->apolice?->numero ?? '—',
            'segurado'      => $s->segurado,
            'seguradora'    => $s->seguradora,
            'tipo'          => $s->tipo,
            'gravidade'     => $s->gravidade,
            'data'          => $s->data?->format('d/m/Y') ?? '—',
            'local'         => $s->local,
            'descricao'     => $s->descricao,
            'valorEstimado' => (float) $s->valor_estimado,
            'valorAprovado' => $s->valor_aprovado !== null ? (float) $s->valor_aprovado : null,
            'estado'        => $s->estado,
            'responsavel'   => $s->responsavel,
            'observacoes'   => $s->observacoes,
            'criadoEm'      => $s->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    /**
     * KPIs — 1 query com SUM(CASE WHEN)
     */
    private function kpis(): array
    {
        $r = Sinistro::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Aberto'     THEN 1 ELSE 0 END) as abertos,
            SUM(CASE WHEN estado = 'Em análise' THEN 1 ELSE 0 END) as em_analise,
            SUM(CASE WHEN estado = 'Resolvido'  THEN 1 ELSE 0 END) as resolvidos,
            SUM(CASE WHEN estado = 'Recusado'   THEN 1 ELSE 0 END) as recusados,
            SUM(valor_estimado) as valor_total,
            SUM(valor_aprovado) as valor_aprovado
        ")->first();

        return [
            'total'         => (int) $r->total,
            'abertos'       => (int) $r->abertos,
            'emAnalise'     => (int) $r->em_analise,
            'resolvidos'    => (int) $r->resolvidos,
            'recusados'     => (int) $r->recusados,
            'valorTotal'    => (float) $r->valor_total,
            'valorAprovado' => (float) $r->valor_aprovado,
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Sinistro::query();

        if ($request->filled('tipo'))       $query->where('tipo', $request->tipo);
        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('gravidade'))  $query->where('gravidade', $request->gravidade);
        if ($request->filled('seguradora')) $query->where('seguradora', $request->seguradora);

        return $query->orderByDesc('created_at')->limit(500)->get();
    }

    /**
     * Parse tolerante: aceita `Y-m-d` e `d/m/Y`. Devolve null se inválida.
     */
    private function parseData(?string $data): ?Carbon
    {
        if (!$data) return null;

        try {
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $data, $m)) {
                $data = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            return Carbon::parse($data);
        } catch (\Exception $e) {
            return null;
        }
    }
}