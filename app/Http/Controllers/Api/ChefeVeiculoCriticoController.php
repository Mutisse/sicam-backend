<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Hotlist;
use App\Models\Veiculo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ChefeVeiculoCriticoController extends Controller
{
    /**
     * GET /api/chefe/veiculos-criticos
     */
    public function index(Request $request)
    {
        $query = Hotlist::query()->with('veiculo');

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('motivo', 'like', "%{$q}%")
                    ->orWhereHas('veiculo', function ($v) use ($q) {
                        $v->where('matricula', 'like', "%{$q}%")
                          ->orWhere('marca', 'like', "%{$q}%")
                          ->orWhere('modelo', 'like', "%{$q}%")
                          ->orWhere('proprietario_nome', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('motivo'))     $query->where('motivo', $request->motivo);
        if ($request->filled('prioridade')) $query->where('prioridade', $request->prioridade);
        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('posto'))      $query->where('posto', $request->posto);

        $items = $query
            ->orderByDesc('detectado_em')
            ->limit(300)
            ->get()
            ->map(fn ($h) => $this->formatar($h));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/chefe/veiculos-criticos/{id}
     */
    public function show(int $id)
    {
        $h = Hotlist::with('veiculo')->findOrFail($id);
        return response()->json($this->formatar($h));
    }

    /**
     * POST /api/chefe/veiculos-criticos
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula'          => 'required|string|max:20',
            'marca'              => 'required|string|max:60',
            'modelo'             => 'required|string|max:60',
            'proprietario'       => 'required|string|max:120',
            'nif'                => 'required|string|size:9',
            'telefone'           => 'nullable|string|max:30',
            'motivo'             => 'required|string|max:60',
            'prioridade'         => 'required|string|in:Baixa,Média,Alta,Urgente',
            'estado'             => 'nullable|string|max:40',
            'posto'              => 'nullable|string|max:120',
            'zona'               => 'nullable|string|max:120',
            'agente'             => 'nullable|string|max:120',
            'multas_pendentes'   => 'nullable|integer|min:0',
            'valor_multas'       => 'nullable|integer|min:0',
            'observacoes'        => 'nullable|string',
        ]);

        $resultado = DB::transaction(function () use ($dados, $request) {
            // 1) Criar ou atualizar o veículo
            $veiculo = Veiculo::firstOrCreate(
                ['matricula' => strtoupper($dados['matricula'])],
                [
                    'marca'                  => $dados['marca'],
                    'modelo'                 => $dados['modelo'],
                    'proprietario_nome'      => $dados['proprietario'],
                    'proprietario_numero_id' => $dados['nif'],
                    'estado'                 => 'critico',
                ],
            );

            // Se já existia, atualizar dados base
            if (!$veiculo->wasRecentlyCreated) {
                $veiculo->update([
                    'marca'                  => $dados['marca'],
                    'modelo'                 => $dados['modelo'],
                    'proprietario_nome'      => $dados['proprietario'],
                    'proprietario_numero_id' => $dados['nif'],
                    'estado'                 => 'critico',
                ]);
            }

            $agora = now();

            // 2) Criar registo na hotlist
            $h = Hotlist::create([
                'veiculo_id'       => $veiculo->id,
                'motivo'           => $dados['motivo'],
                'perigo'           => $this->perigoDe($dados['prioridade']),
                'prioridade'       => $dados['prioridade'],
                'agente'           => $dados['agente'] ?? null,
                'posto'            => $dados['posto'] ?? null,
                'zona'             => $dados['zona'] ?? null,
                'multas_pendentes' => $dados['multas_pendentes'] ?? 0,
                'valor_multas'     => $dados['valor_multas'] ?? 0,
                'estado'           => $dados['estado'] ?? 'Activo',
                'descricao'        => $dados['observacoes'] ?? null,
                'detectado_em'     => $agora,
                'historico'        => [
                    [
                        'data'        => $agora->format('d/m/Y H:i'),
                        'acao'        => 'Registo criado',
                        'responsavel' => $dados['agente'] ?? 'Chefe de Posto',
                    ],
                ],
            ]);

            return $h;
        });

        return response()->json([
            'id'      => (string) $resultado->id,
            'veiculo' => $this->formatar($resultado->fresh('veiculo')),
        ], 201);
    }

    /**
     * PUT /api/chefe/veiculos-criticos/{id}
     */
    public function update(Request $request, int $id)
    {
        $h = Hotlist::findOrFail($id);

        $dados = $request->validate([
            'marca'              => 'sometimes|string|max:60',
            'modelo'             => 'sometimes|string|max:60',
            'proprietario'       => 'sometimes|string|max:120',
            'nif'                => 'sometimes|string|size:9',
            'telefone'           => 'nullable|string|max:30',
            'motivo'             => 'sometimes|string|max:60',
            'prioridade'         => 'sometimes|string|in:Baixa,Média,Alta,Urgente',
            'estado'             => 'sometimes|string|max:40',
            'posto'              => 'nullable|string|max:120',
            'zona'               => 'nullable|string|max:120',
            'agente'             => 'nullable|string|max:120',
            'multas_pendentes'   => 'nullable|integer|min:0',
            'valor_multas'       => 'nullable|integer|min:0',
            'observacoes'        => 'nullable|string',
        ]);

        // Atualizar veículo
        if ($h->veiculo) {
            $h->veiculo->update(array_filter([
                'marca'                  => $dados['marca'] ?? null,
                'modelo'                 => $dados['modelo'] ?? null,
                'proprietario_nome'      => $dados['proprietario'] ?? null,
                'proprietario_numero_id' => $dados['nif'] ?? null,
            ]));
        }

        // Atualizar hotlist
        $h->update([
            'motivo'           => $dados['motivo'] ?? $h->motivo,
            'prioridade'       => $dados['prioridade'] ?? $h->prioridade,
            'perigo'           => isset($dados['prioridade']) ? $this->perigoDe($dados['prioridade']) : $h->perigo,
            'agente'           => $dados['agente'] ?? $h->agente,
            'posto'            => $dados['posto'] ?? $h->posto,
            'zona'             => $dados['zona'] ?? $h->zona,
            'multas_pendentes' => $dados['multas_pendentes'] ?? $h->multas_pendentes,
            'valor_multas'     => $dados['valor_multas'] ?? $h->valor_multas,
            'estado'           => $dados['estado'] ?? $h->estado,
            'descricao'        => $dados['observacoes'] ?? $h->descricao,
        ]);

        return response()->json([
            'id'      => (string) $h->id,
            'veiculo' => $this->formatar($h->fresh('veiculo')),
        ]);
    }

    /**
     * DELETE /api/chefe/veiculos-criticos/{id}
     */
    public function destroy(int $id)
    {
        $h = Hotlist::findOrFail($id);
        $h->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * PUT /api/chefe/veiculos-criticos/{id}/abater-hotlist
     */
    public function abaterHotlist(int $id)
    {
        $h = Hotlist::findOrFail($id);
        $this->adicionarHistorico($h, 'Hotlist abatida');
        $h->update(['estado' => 'Resolvido', 'resolvido_em' => now()]);

        return response()->json([
            'id'      => (string) $h->id,
            'veiculo' => $this->formatar($h->fresh('veiculo')),
        ]);
    }

    /**
     * PUT /api/chefe/veiculos-criticos/{id}/regularizar-seguro
     */
    public function regularizarSeguro(int $id)
    {
        $h = Hotlist::findOrFail($id);
        $this->adicionarHistorico($h, 'Seguro regularizado');
        $h->update(['estado' => 'Resolvido', 'resolvido_em' => now()]);

        return response()->json([
            'id'      => (string) $h->id,
            'veiculo' => $this->formatar($h->fresh('veiculo')),
        ]);
    }

    /**
     * GET /api/chefe/veiculos-criticos/xlsx
     */
    public function xlsx(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Matrícula', 'Marca', 'Modelo', 'Proprietário', 'NIF',
            'Motivo', 'Prioridade', 'Posto', 'Zona', 'Agente',
            'Multas', 'Valor (MT)', 'Estado',
        ];

        $linhas = $items->map(function ($h) {
            $v = $h->veiculo;
            return [
                $v?->matricula ?? '—',
                $v?->marca ?? '—',
                $v?->modelo ?? '—',
                $v?->proprietario_nome ?? '—',
                $v?->proprietario_numero_id ?? '—',
                $h->motivo ?? '—',
                $h->prioridade ?? '—',
                $h->posto ?? '—',
                $h->zona ?? '—',
                $h->agente ?? '—',
                (string) $h->multas_pendentes,
                (string) $h->valor_multas,
                $h->estado,
            ];
        })->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Veículos Críticos'),
            'veiculos-criticos-' . date('Y-m-d') . '.xlsx',
        );
    }

    /**
     * GET /api/chefe/veiculos-criticos/pdf
     */
    public function pdf(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Matrícula', 'Marca/Modelo', 'Proprietário',
            'Motivo', 'Prioridade', 'Posto', 'Estado',
        ];

        $linhas = $items->map(function ($h) {
            $v = $h->veiculo;
            return [
                $v?->matricula ?? '—',
                ($v?->marca ?? '—') . ' ' . ($v?->modelo ?? ''),
                $v?->proprietario_nome ?? '—',
                $h->motivo ?? '—',
                $h->prioridade ?? '—',
                $h->posto ?? '—',
                $h->estado,
            ];
        })->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Polícia da República de Moçambique',
            'sigla'       => 'PRM',
            'rodape'      => 'SICAM — Chefe de Posto · Veículos Críticos',
            'titulo'      => 'Veículos Críticos',
            'subtitulo'   => 'Situações que requerem intervenção',
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Total'      => $items->count(),
                'Hotlist'    => $items->where('motivo', 'Hotlist PRM')->count(),
                'Urgentes'   => $items->where('prioridade', 'Urgente')->count(),
                'Resolvidos' => $items->where('estado', 'Resolvido')->count(),
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('veiculos-criticos-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Hotlist $h): array
    {
        $v = $h->veiculo;

        return [
            'id'               => (string) $h->id,
            'matricula'        => $v?->matricula ?? '—',
            'marca'            => $v?->marca ?? '—',
            'modelo'           => $v?->modelo ?? '—',
            'proprietario'     => $v?->proprietario_nome ?? '—',
            'nif'              => $v?->proprietario_numero_id ?? '—',
            'telefone'         => '—',  // não existe na BD
            'motivo'           => $h->motivo ?? 'Outro',
            'prioridade'       => $h->prioridade ?? 'Média',
            'estado'           => $this->mapearEstado($h->estado),
            'posto'            => $h->posto ?? '—',
            'zona'             => $h->zona ?? '—',
            'agente'           => $h->agente ?? '—',
            'ultimaOcorrencia' => $h->detectado_em?->format('d/m/Y H:i') ?? '—',
            'multasPendentes'  => (int) $h->multas_pendentes,
            'valorMultas'      => (int) $h->valor_multas,
            'observacoes'      => $h->descricao,
            'historico'        => $h->historico ?? [],
            'criadoEm'         => $h->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function kpis(): array
    {
        $activos = Hotlist::where('estado', '!=', 'Arquivado');

        return [
            'total'          => (clone $activos)->count(),
            'hotlist'        => (clone $activos)->where('motivo', 'Hotlist PRM')->count(),
            'seguroExpirado' => (clone $activos)->where('motivo', 'Seguro expirado')->count(),
            'emAveriguacao'  => (clone $activos)->where('estado', 'Em averiguação')->count(),
            'urgentes'       => (clone $activos)->where('prioridade', 'Urgente')->count(),
            'resolvidos'     => Hotlist::where('estado', 'Resolvido')->count(),
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Hotlist::query()->with('veiculo');

        if ($request->filled('motivo'))     $query->where('motivo', $request->motivo);
        if ($request->filled('prioridade')) $query->where('prioridade', $request->prioridade);
        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('posto'))      $query->where('posto', $request->posto);

        return $query->orderByDesc('detectado_em')->limit(500)->get();
    }

    private function adicionarHistorico(Hotlist $h, string $acao): void
    {
        $historico = $h->historico ?? [];
        $historico[] = [
            'data'        => now()->format('d/m/Y H:i'),
            'acao'        => $acao,
            'responsavel' => 'Chefe de Posto',
        ];
        $h->update(['historico' => $historico]);
    }

    private function perigoDe(string $prioridade): string
    {
        return match ($prioridade) {
            'Urgente', 'Alta' => 'alto',
            'Média'           => 'medio',
            default           => 'baixo',
        };
    }

    private function mapearEstado(?string $estado): string
    {
        // A BD guarda 'Activo', 'Resolvido', etc.
        // O frontend espera 'Activo', 'Em averiguação', 'Resolvido', 'Arquivado'
        if (!$estado) return 'Activo';
        return $estado;
    }
}