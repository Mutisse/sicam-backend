<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Apolice;
use App\Models\Veiculo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class IssmApoliceController extends Controller
{
    /**
     * GET /api/issm/apolices
     */
    public function index(Request $request)
    {
        $query = Apolice::query()->with('veiculo:id,matricula,marca,modelo,ano,cor');

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('segurado', 'like', "%{$q}%")
                    ->orWhere('nif', 'like', "%{$q}%")
                    ->orWhereHas('veiculo', fn ($v) => $v->where('matricula', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('seguradora')) $query->where('seguradora', $request->seguradora);
        if ($request->filled('tipo'))       $query->where('tipo', $request->tipo);
        if ($request->filled('estado'))     $query->where('estado', $request->estado);

        $items = $query
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->map(fn ($a) => $this->formatar($a));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/issm/apolices/{id}
     */
    public function show(int $id)
    {
        $a = Apolice::with('veiculo:id,matricula,marca,modelo,ano,cor')->findOrFail($id);
        return response()->json($this->formatar($a));
    }

    /**
     * POST /api/issm/apolices
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'numero'      => 'required|string|max:40|unique:apolices,numero',
            'matricula'   => 'required|string|max:20',
            'segurado'    => 'required|string|max:120',
            'nif'         => 'required|string|size:9',
            'telefone'    => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:120',
            'seguradora'  => 'required|string|max:120',
            'tipo'        => 'required|string|max:60',
            'inicio'      => 'required|date',
            'fim'         => 'required|date|after:inicio',
            'premio'      => 'required|numeric|min:0',
            'estado'      => 'nullable|string|in:Activa,A expirar,Expirada,Anulada',
            'observacoes' => 'nullable|string',
        ]);

        // Veículo TEM de existir — não criamos veículos incompletos.
        $veiculo = Veiculo::where('matricula', strtoupper($dados['matricula']))->first();
        if (!$veiculo) {
            return response()->json([
                'message' => 'Veículo não registado. Registe o veículo antes de criar a apólice.',
            ], 422);
        }

        $inicio = $this->parseData($dados['inicio']);
        $fim    = $this->parseData($dados['fim']);
        if (!$inicio || !$fim) {
            return response()->json(['message' => 'Datas inválidas.'], 422);
        }

        $estado = $this->calcularEstado($inicio, $fim, $dados['estado'] ?? null);

        $a = Apolice::create([
            'numero'      => $dados['numero'],
            'veiculo_id'  => $veiculo->id,
            'segurado'    => $dados['segurado'],
            'nif'         => $dados['nif'],
            'telefone'    => $dados['telefone'] ?? null,
            'email'       => $dados['email'] ?? null,
            'seguradora'  => $dados['seguradora'],
            'tipo'        => $dados['tipo'],
            'inicio'      => $inicio,
            'validade'    => $fim,
            'premio'      => $dados['premio'],
            'estado'      => $estado,
            'observacoes' => $dados['observacoes'] ?? null,
        ]);

        return response()->json([
            'id'      => (string) $a->id,
            'apolice' => $this->formatar($a->fresh('veiculo')),
        ], 201);
    }

    /**
     * PUT /api/issm/apolices/{id}
     */
    public function update(Request $request, int $id)
    {
        $a = Apolice::findOrFail($id);

        $dados = $request->validate([
            'matricula'   => 'sometimes|string|max:20',
            'segurado'    => 'sometimes|string|max:120',
            'nif'         => 'sometimes|string|size:9',
            'telefone'    => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:120',
            'seguradora'  => 'sometimes|string|max:120',
            'tipo'        => 'sometimes|string|max:60',
            'inicio'      => 'sometimes|date',
            'fim'         => 'sometimes|date',
            'premio'      => 'sometimes|numeric|min:0',
            'estado'      => 'sometimes|string|in:Activa,A expirar,Expirada,Anulada',
            'observacoes' => 'nullable|string',
        ]);

        if (isset($dados['matricula'])) {
            $veiculo = Veiculo::where('matricula', strtoupper($dados['matricula']))->first();
            if (!$veiculo) {
                return response()->json([
                    'message' => 'Veículo não registado. Registe o veículo antes de associar a apólice.',
                ], 422);
            }
            $dados['veiculo_id'] = $veiculo->id;
            unset($dados['matricula']);
        }

        if (isset($dados['inicio'])) {
            $inicio = $this->parseData($dados['inicio']);
            if (!$inicio) {
                return response()->json(['message' => 'Data de início inválida.'], 422);
            }
            $dados['inicio'] = $inicio;
        }
        if (isset($dados['fim'])) {
            $fim = $this->parseData($dados['fim']);
            if (!$fim) {
                return response()->json(['message' => 'Data de fim inválida.'], 422);
            }
            $dados['validade'] = $fim;
            unset($dados['fim']);
        }

        // Recalcular estado se as datas mudaram (excepto se 'Anulada' explícita)
        if (isset($dados['inicio']) || isset($dados['validade'])) {
            $inicioFinal = $dados['inicio']  ?? $a->inicio;
            $fimFinal    = $dados['validade'] ?? $a->validade;

            if ($fimFinal->lt($inicioFinal)) {
                return response()->json(['message' => 'Data de fim anterior à de início.'], 422);
            }

            if (($dados['estado'] ?? $a->estado) !== 'Anulada') {
                $dados['estado'] = $this->calcularEstado(
                    $inicioFinal,
                    $fimFinal,
                    $dados['estado'] ?? null
                );
            }
        }

        $a->update($dados);

        return response()->json([
            'id'      => (string) $a->id,
            'apolice' => $this->formatar($a->fresh('veiculo')),
        ]);
    }

    /**
     * DELETE /api/issm/apolices/{id}
     */
    public function destroy(int $id)
    {
        $a = Apolice::findOrFail($id);
        $a->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/issm/apolices/xlsx
     */
    public function xlsx(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Nº Apólice', 'Matrícula', 'Segurado', 'NIF', 'Seguradora',
            'Tipo', 'Início', 'Fim', 'Prémio (MT)', 'Estado',
        ];

        $linhas = $items->map(function ($a) {
            $v = $a->veiculo;
            return [
                $a->numero,
                $v?->matricula ?? '—',
                $a->segurado ?? '—',
                $a->nif ?? '—',
                $a->seguradora,
                $a->tipo,
                $a->inicio?->format('d/m/Y') ?? '—',
                $a->validade?->format('d/m/Y') ?? '—',
                (string) $a->premio,
                $a->estado,
            ];
        })->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Apólices'),
            'issm-apolices-' . date('Y-m-d') . '.xlsx',
        );
    }

    /**
     * GET /api/issm/apolices/pdf
     */
    public function pdf(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Nº Apólice', 'Matrícula', 'Segurado', 'Seguradora',
            'Tipo', 'Início', 'Fim', 'Estado',
        ];

        $linhas = $items->map(function ($a) {
            $v = $a->veiculo;
            return [
                $a->numero,
                $v?->matricula ?? '—',
                $a->segurado ?? '—',
                $a->seguradora,
                $a->tipo,
                $a->inicio?->format('d/m/Y') ?? '—',
                $a->validade?->format('d/m/Y') ?? '—',
                $a->estado,
            ];
        })->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Instituto de Supervisão de Seguros de Moçambique',
            'sigla'       => 'ISSM',
            'rodape'      => 'SICAM — ISSM · Apólices de Seguro Obrigatório',
            'titulo'      => 'Apólices',
            'subtitulo'   => 'Registo de apólices e estados de validade',
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Total'     => $items->count(),
                'Activas'   => $items->where('estado', 'Activa')->count(),
                'A expirar' => $items->where('estado', 'A expirar')->count(),
                'Expiradas' => $items->where('estado', 'Expirada')->count(),
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('issm-apolices-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Apolice $a): array
    {
        $v = $a->veiculo;

        return [
            'id'          => (string) $a->id,
            'numero'      => $a->numero,
            'matricula'   => $v?->matricula ?? '—',
            'marca'       => $v?->marca ?? '—',
            'modelo'      => $v?->modelo ?? '—',
            'segurado'    => $a->segurado ?? '—',
            'nif'         => $a->nif ?? '—',
            'telefone'    => $a->telefone ?? null,
            'email'       => $a->email ?? null,
            'seguradora'  => $a->seguradora,
            'tipo'        => $a->tipo,
            'inicio'      => $a->inicio?->format('d/m/Y') ?? '—',
            'fim'         => $a->validade?->format('d/m/Y') ?? '—',
            'premio'      => (float) $a->premio,
            'estado'      => $a->estado,
            'observacoes' => $a->observacoes,
            'criadoEm'    => $a->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    /**
     * KPIs — 1 query com SUM(CASE WHEN)
     */
    private function kpis(): array
    {
        $r = Apolice::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Activa'    THEN 1 ELSE 0 END) as activas,
            SUM(CASE WHEN estado = 'A expirar' THEN 1 ELSE 0 END) as a_expirar,
            SUM(CASE WHEN estado = 'Expirada'  THEN 1 ELSE 0 END) as expiradas,
            SUM(CASE WHEN estado = 'Anulada'   THEN 1 ELSE 0 END) as anuladas,
            SUM(premio) as premio_total
        ")->first();

        return [
            'total'       => (int) $r->total,
            'activas'     => (int) $r->activas,
            'aExpirar'    => (int) $r->a_expirar,
            'expiradas'   => (int) $r->expiradas,
            'anuladas'    => (int) $r->anuladas,
            'premioTotal' => (float) $r->premio_total,
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Apolice::query()->with('veiculo:id,matricula,marca,modelo');

        if ($request->filled('seguradora')) $query->where('seguradora', $request->seguradora);
        if ($request->filled('tipo'))       $query->where('tipo', $request->tipo);
        if ($request->filled('estado'))     $query->where('estado', $request->estado);

        return $query->orderByDesc('created_at')->limit(500)->get();
    }

    /**
     * Parse tolerante de datas: aceita `Y-m-d` e `d/m/Y`.
     * Devolve null se inválida (em vez de rebentar).
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

    /**
     * Calcula estado com base nas datas.
     * 'Anulada' explícita prevalece sobre o cálculo.
     */
    private function calcularEstado(Carbon $inicio, Carbon $fim, ?string $estadoManual = null): string
    {
        if ($estadoManual === 'Anulada') {
            return 'Anulada';
        }

        $hoje = now()->startOfDay();

        if ($fim->lt($hoje)) {
            return 'Expirada';
        }
        if ($inicio->gt($hoje)) {
            return 'A expirar';  // ainda não começou
        }
        if ($fim->diffInDays($hoje) <= 30) {
            return 'A expirar';  // expira nos próximos 30 dias
        }
        return 'Activa';
    }
}