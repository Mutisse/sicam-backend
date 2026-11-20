<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotlist;
use App\Models\Multa;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\SicamIAService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrmHotlistController extends Controller
{
    protected SicamIAService $ia;

    public function __construct(SicamIAService $ia)
    {
        $this->ia = $ia;
    }

    /**
     * GET /api/hotlist
     */
    public function index(Request $request)
    {
        $query = Hotlist::with(['veiculo:id,matricula,marca,modelo,cor,score'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $termo = '%' . $request->q . '%';
                $q->where(function ($sub) use ($termo) {
                    $sub->where('motivo', 'like', $termo)
                        ->orWhere('zona', 'like', $termo)
                        ->orWhere('agente', 'like', $termo)
                        ->orWhereHas('veiculo', fn ($v) => $v->where('matricula', 'like', $termo));
                });
            })
            ->when($request->filled('estado'),     fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('prioridade'), fn ($q) => $q->where('prioridade', $request->prioridade))
            ->when($request->filled('perigo'),     fn ($q) => $q->where('perigo', $request->perigo))
            ->orderByRaw("FIELD(prioridade, 'critica','alta','media','baixa')")
            ->orderByDesc('detectado_em')
            ->orderByDesc('id');

        $items = $query->get()->map(function (Hotlist $h) {
            $nivelMap = ['critica' => 'Alto', 'alta' => 'Alto', 'media' => 'Médio', 'baixa' => 'Baixo'];
            $estadoMap = ['ativo' => 'Ativo', 'resolvido' => 'Resolvido', 'arquivado' => 'Resolvido'];

            $totalAbordagens = DB::table('abordagens')->where('veiculo_id', $h->veiculo_id)->count();

            return [
                'id'           => (string) $h->id,
                'matricula'    => $h->veiculo->matricula ?? '',
                'marca'        => $h->veiculo->marca ?? '',
                'modelo'       => $h->veiculo->modelo ?? '',
                'cor'          => $h->veiculo->cor ?? '',
                'origem'       => 'PRM',
                'dataInsercao' => $h->detectado_em ? $h->detectado_em->format('d/m/Y') : '',
                'horaInsercao' => $h->detectado_em ? $h->detectado_em->format('H:i') : '',
                'local'        => $h->zona ?? '',
                'posto'        => $h->posto ?? '',
                'agente'       => $h->agente ?? '',
                'motivo'       => $h->motivo,
                'nivel'        => $nivelMap[$h->prioridade] ?? 'Médio',
                'estado'       => $estadoMap[$h->estado] ?? 'Ativo',
                'abordagens'   => $totalAbordagens,
                'observacoes'  => $h->descricao,
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'kpis'  => [
                'total'    => $items->count(),
                'furtados' => $items->where('motivo', 'Furtado')->count(),
                'clonagem' => $items->where('motivo', 'Clonagem suspeita')->count(),
                'mandados' => $items->where('motivo', 'Mandado de captura')->count(),
                'altos'    => $items->where('nivel', 'Alto')->count(),
                'ativos'   => $items->where('estado', 'Ativo')->count(),
            ],
        ]);
    }

    /**
     * GET /api/hotlist/resumo-ia
     *
     * ⭐ CORRIGIDO: Não usa auth()->user() nem $request->user().
     * Apenas recolhe a hotlist e envia para a IA.
     */
    public function resumoIA()
    {
        $hotlist = Hotlist::with('veiculo')->where('estado', 'ativo')->orderByDesc('detectado_em')->get();

        if ($hotlist->isEmpty()) {
            return response()->json([
                'resumo' => "Bom trabalho, comandante. Não há veículos ativos na Hotlist neste momento. A via está limpa. Sugiro aproveitar para reforçar a fiscalização de rotina nas zonas com maior incidência histórica.",
            ]);
        }

        $total = $hotlist->count();
        $furtados = $hotlist->where('motivo', 'Furtado')->count();
        $clonagem = $hotlist->where('motivo', 'Clonagem suspeita')->count();
        $mandados = $hotlist->where('motivo', 'Mandado de captura')->count();
        $urgentes = $hotlist->whereIn('prioridade', ['critica', 'alta'])->count();

        $dadosVeiculos = $hotlist->take(15)->map(function ($h) {
            return [
                'matricula'   => $h->veiculo?->matricula ?? '—',
                'marca'       => $h->veiculo?->marca ?? '—',
                'modelo'      => $h->veiculo?->modelo ?? '—',
                'motivo'      => $h->motivo ?? '—',
                'prioridade'  => $h->prioridade ?? '—',
                'zona'        => $h->zona ?? '—',
                'posto'       => $h->posto ?? '—',
                'agente'      => $h->agente ?? '—',
                'multas'      => $h->multas_pendentes ?? 0,
                'valor'       => $h->valor_multas ?? 0,
            ];
        })->toArray();

        $resposta = $this->ia->analisarHotlist([
            'total'      => $total,
            'furtados'   => $furtados,
            'clonagem'   => $clonagem,
            'mandados'   => $mandados,
            'urgentes'   => $urgentes,
            'veiculos'   => $dadosVeiculos,
        ]);

        return response()->json($resposta);
    }

    /**
     * GET /api/hotlist/kpis
     */
    public function kpisEndpoint()
    {
        return response()->json([
            'total'      => Hotlist::count(),
            'ativos'     => Hotlist::where('estado', 'ativo')->count(),
            'resolvidos' => Hotlist::where('estado', 'resolvido')->count(),
            'criticos'   => Hotlist::where('prioridade', 'critica')->where('estado', 'ativo')->count(),
            'multas_pendentes' => (int) Hotlist::where('estado', 'ativo')->sum('multas_pendentes'),
            'valor_multas'     => (int) Hotlist::where('estado', 'ativo')->sum('valor_multas'),
        ]);
    }

    /**
     * POST /api/hotlist
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula' => ['required', 'string', 'max:20', 'exists:veiculos,matricula'],
            'motivo'    => ['required', 'string', 'max:255'],
            'perigo'    => ['nullable', 'string', 'max:50'],
            'descricao' => ['nullable', 'string'],
        ]);

        try {
            $hotlist = DB::transaction(function () use ($dados, $request) {
                $veiculo = Veiculo::where('matricula', $dados['matricula'])->firstOrFail();

                $multasPendentes = Multa::where('veiculo_id', $veiculo->id)
                    ->where('estado', 'Pendente')
                    ->count();

                $valorMultas = Multa::where('veiculo_id', $veiculo->id)
                    ->where('estado', 'Pendente')
                    ->sum('valor');

                $prioridadeMap = ['alto' => 'critica', 'medio' => 'media', 'baixo' => 'baixa'];
                $prioridade = $prioridadeMap[$dados['perigo'] ?? ''] ?? 'media';

                $userName = $request->user()?->name ?? 'Sistema';

                $novaHotlist = Hotlist::create([
                    'veiculo_id'       => $veiculo->id,
                    'motivo'           => $dados['motivo'],
                    'perigo'           => $dados['perigo'] ?? null,
                    'prioridade'       => $prioridade,
                    'agente'           => $userName,
                    'posto'            => null,
                    'zona'             => null,
                    'multas_pendentes' => $multasPendentes,
                    'valor_multas'     => $valorMultas,
                    'estado'           => 'ativo',
                    'descricao'        => $dados['descricao'] ?? null,
                    'historico'        => [[
                        'em'   => now()->toIso8601String(),
                        'acao' => 'criado',
                        'por'  => $userName,
                    ]],
                    'detectado_em'     => now(),
                ]);

                $veiculo->update(['score' => 0, 'estado' => 'critico']);

                $chefes = User::whereHas('perfil', fn ($q) => $q->where('key', 'chefe'))->get();
                foreach ($chefes as $chefe) {
                    $chefe->notificar(
                        'Novo veículo na Hotlist',
                        "O veículo {$veiculo->matricula} foi inserido na Hotlist. Motivo: {$dados['motivo']}.",
                        'aviso',
                        'hotlist'
                    );
                }

                return $novaHotlist;
            });

            return response()->json([
                'id'   => (string) $hotlist->id,
                'kpis' => [],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Falha ao adicionar à hotlist.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/hotlist/{id}
     */
    public function update(Request $request, $id)
    {
        $hotlist = Hotlist::find($id);
        if (!$hotlist) {
            return response()->json(['message' => 'Registo não encontrado.'], 404);
        }

        $dados = $request->validate([
            'motivo'    => ['sometimes', 'string', 'max:255'],
            'perigo'    => ['nullable', 'string', 'max:50'],
            'descricao' => ['nullable', 'string'],
        ]);

        if (array_key_exists('perigo', $dados)) {
            $map = ['alto' => 'critica', 'medio' => 'media', 'baixo' => 'baixa'];
            $dados['prioridade'] = $map[$dados['perigo'] ?? ''] ?? 'media';
        }

        $userName = $request->user()?->name ?? 'Sistema';

        $historico = $hotlist->historico ?? [];
        $historico[] = [
            'em'     => now()->toIso8601String(),
            'acao'   => 'atualizado',
            'por'    => $userName,
            'campos' => array_keys($dados),
        ];

        $hotlist->update(array_merge($dados, ['historico' => $historico]));

        return response()->json([
            'message' => 'Registo atualizado.',
            'data'    => $hotlist->fresh('veiculo:id,matricula,marca,modelo'),
        ]);
    }

    /**
     * PUT /api/hotlist/{id}/resolver
     */
    public function resolver(Request $request, $id)
    {
        $hotlist = Hotlist::find($id);
        if (!$hotlist) {
            return response()->json(['message' => 'Registo não encontrado.'], 404);
        }

        if ($hotlist->estado === 'resolvido') {
            return response()->json(['message' => 'Registo já está resolvido.'], 409);
        }

        DB::transaction(function () use ($hotlist, $request) {
            $userName = $request->user()?->name ?? 'Sistema';

            $historico = $hotlist->historico ?? [];
            $historico[] = [
                'em'   => now()->toIso8601String(),
                'acao' => 'resolvido',
                'por'  => $userName,
                'nota' => $request->input('nota'),
            ];

            $hotlist->update([
                'estado'       => 'resolvido',
                'resolvido_em' => now(),
                'historico'    => $historico,
            ]);

            if ($hotlist->veiculo) {
                $hotlist->veiculo->update([
                    'estado' => 'pendente',
                    'score'  => 50,
                ]);

                \App\Http\Controllers\Api\InatroAgendamentoController::criarAgendamentoAutomatico($hotlist->veiculo);

                $chefes = User::whereHas('perfil', fn ($q) => $q->where('key', 'chefe'))->get();
                foreach ($chefes as $chefe) {
                    $chefe->notificar(
                        'Hotlist resolvida e agendada',
                        "O veículo {$hotlist->veiculo->matricula} saiu da Hotlist e foi agendado para reinspeção no INATRO.",
                        'sucesso',
                        'hotlist'
                    );
                }
            }
        });

        return response()->json([
            'message' => 'Registo marcado como resolvido e veículo agendado para inspeção.',
            'data'    => $hotlist->fresh('veiculo:id,matricula,marca,modelo'),
        ]);
    }

    /**
     * DELETE /api/hotlist/{id}
     */
    public function destroy($id)
    {
        $hotlist = Hotlist::find($id);
        if (!$hotlist) {
            return response()->json(['message' => 'Registo não encontrado.'], 404);
        }

        $hotlist->delete();
        return response()->json(['message' => 'Registo eliminado.']);
    }
}