<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistCampo;
use App\Models\ChecklistSeccao;
use App\Models\Inspecao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InatroInspecaoController extends Controller
{
    /**
     * GET /api/inspecoes
     * Lista paginada com filtros.
     */
    public function index(Request $request)
    {
        $query = Inspecao::with([
                'veiculo:id,matricula,marca,modelo',
                'posto:id,nome',
            ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $termo = '%' . $request->q . '%';
                $q->where(function ($sub) use ($termo) {
                    $sub->where('codigo', 'like', $termo)
                        ->orWhere('inspetor', 'like', $termo)
                        ->orWhereHas('veiculo', fn ($v) => $v->where('matricula', 'like', $termo));
                });
            })
            ->when($request->filled('estado'),    fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('resultado'), fn ($q) => $q->where('resultado', $request->resultado))
            ->when($request->filled('posto_id'),  fn ($q) => $q->where('posto_id', $request->posto_id))
            ->when($request->filled('data_inicio'), fn ($q) => $q->whereDate('data', '>=', $request->data_inicio))
            ->when($request->filled('data_fim'),    fn ($q) => $q->whereDate('data', '<=', $request->data_fim))
            ->orderByDesc('data')
            ->orderByDesc('id');

        $porPagina = max(1, min((int) $request->input('per_page', 15), 100));
        $pagina = $query->paginate($porPagina);

        return response()->json([
            'data' => $pagina->items(),
            'meta' => [
                'total'        => $pagina->total(),
                'por_pagina'   => $pagina->perPage(),
                'pagina_atual' => $pagina->currentPage(),
                'ultima'       => $pagina->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/inspecoes/kpis
     * Contadores para os cards do topo (replica o dashboard INATRO).
     */
    public function kpis()
    {
        $ins = Inspecao::selectRaw("
            COUNT(*) as total,
            COUNT(DISTINCT CASE WHEN estado = 'valida'   THEN veiculo_id END) as ok,
            COUNT(DISTINCT CASE WHEN estado = 'expirada' THEN veiculo_id END) as expiradas,
            COUNT(DISTINCT CASE WHEN estado = 'pendente' THEN veiculo_id END) as pendentes,
            SUM(CASE WHEN resultado = 'Aprovada'  THEN 1 ELSE 0 END) as aprovadas,
            SUM(CASE WHEN resultado = 'Reprovada' THEN 1 ELSE 0 END) as reprovadas
        ")->first();

        return response()->json([
            'total'      => (int) $ins->total,
            'valida'     => (int) $ins->ok,
            'expirada'   => (int) $ins->expiradas,
            'pendente'   => (int) $ins->pendentes,
            'aprovadas'  => (int) $ins->aprovadas,
            'reprovadas' => (int) $ins->reprovadas,
        ]);
    }

    /**
     * GET /api/inspecoes/checklist
     * Devolve as secções + campos do checklist para o formulário.
     */
    public function checklist()
    {
        $seccoes = ChecklistSeccao::with([
                'campos' => fn ($q) => $q->orderBy('ordem'),
            ])
            ->orderBy('ordem')
            ->get();

        return response()->json(['data' => $seccoes]);
    }

    /**
     * POST /api/inspecoes
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'veiculo_id'       => ['required', 'integer', 'exists:veiculos,id'],
            'posto_id'         => ['nullable', 'integer', 'exists:postos,id'],
            'data'             => ['nullable', 'date'],
            'validade'         => ['nullable', 'date', 'after_or_equal:data'],
            'proxima_inspecao' => ['nullable', 'date'],
            'resultado'        => ['required', Rule::in(['Aprovada', 'Reprovada'])],
            'estado'           => ['nullable', Rule::in(['valida', 'expirada', 'pendente'])],
            'tipo'             => ['nullable', 'string', 'max:50'],
            'observacoes'      => ['nullable', 'string'],
            'checklist'        => ['nullable', 'array'],
            'inspetor'         => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $inspecao = DB::transaction(function () use ($dados) {
                // Gera código INS-YYYY-XXXX
                $ano = now()->year;
                $ultimo = Inspecao::where('codigo', 'like', "INS-{$ano}-%")
                    ->lockForUpdate()
                    ->count();
                $codigo = sprintf('INS-%d-%04d', $ano, $ultimo + 1);

                $dataInspecao = $dados['data'] ?? now()->toDateString();
                $validade = $dados['validade']
                    ?? date('Y-m-d', strtotime($dataInspecao . ' +1 year'));

                return Inspecao::create([
                    'codigo'           => $codigo,
                    'veiculo_id'       => $dados['veiculo_id'],
                    'posto_id'         => $dados['posto_id'] ?? null,
                    'data'             => $dataInspecao,
                    'validade'         => $validade,
                    'proxima_inspecao' => $dados['proxima_inspecao'] ?? $validade,
                    'resultado'        => $dados['resultado'],
                    'estado'           => $dados['estado'] ?? 'valida',
                    'tipo'             => $dados['tipo'] ?? 'Periodica',
                    'observacoes'      => $dados['observacoes'] ?? null,
                    'checklist'        => $dados['checklist'] ?? null,
                    'inspetor'         => $dados['inspetor'] ?? (auth()->user()->name ?? null),
                ]);
            });

            // Recalcula o estado das inspecções do veículo com base na validade
            $this->atualizarEstadosDoVeiculo($inspecao->veiculo_id);

            $inspecao->load(['veiculo:id,matricula,marca,modelo', 'posto:id,nome']);

            return response()->json([
                'message' => 'Inspecção registada com sucesso.',
                'data'    => $inspecao,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Falha ao registar a inspecção.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/inspecoes/{id}
     */
    public function show($id)
    {
        $inspecao = Inspecao::with([
            'veiculo:id,matricula,marca,modelo,ano,cor',
            'posto:id,nome',
        ])->find($id);

        if (!$inspecao) {
            return response()->json(['message' => 'Inspecção não encontrada.'], 404);
        }

        return response()->json(['data' => $inspecao]);
    }

    /**
     * Marca como expiradas as inspecções cuja validade já passou.
     */
    private function atualizarEstadosDoVeiculo(int $veiculoId): void
    {
        Inspecao::where('veiculo_id', $veiculoId)
            ->where('estado', 'valida')
            ->whereDate('validade', '<', now()->toDateString())
            ->update(['estado' => 'expirada']);
    }
}