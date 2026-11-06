<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotlist;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrmHotlistController extends Controller
{
    /**
     * GET /api/hotlist
     * Devolve { items, kpis } — contrato esperado pelo hotlist.service.ts.
     */
    public function index(Request $request)
    {
        $query = Hotlist::with(['veiculo:id,matricula,marca,modelo,cor'])
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
                'abordagens'   => 0,
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
     * Frontend envia: { matricula, motivo, perigo, descricao }
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
            $veiculo = Veiculo::where('matricula', $dados['matricula'])->firstOrFail();

            // Mapear perigo → prioridade
            $prioridadeMap = ['alto' => 'alta', 'medio' => 'media', 'baixo' => 'baixa'];

            $hotlist = Hotlist::create([
                'veiculo_id'       => $veiculo->id,
                'motivo'           => $dados['motivo'],
                'perigo'           => $dados['perigo'] ?? null,
                'prioridade'       => $prioridadeMap[$dados['perigo'] ?? ''] ?? 'media',
                'agente'           => auth()->user()->name ?? null,
                'posto'            => null,
                'zona'             => null,
                'multas_pendentes' => 0,
                'valor_multas'     => 0,
                'estado'           => 'ativo',
                'descricao'        => $dados['descricao'] ?? null,
                'historico'        => [[
                    'em'   => now()->toIso8601String(),
                    'acao' => 'criado',
                    'por'  => auth()->user()->name ?? 'sistema',
                ]],
                'detectado_em'     => now(),
            ]);

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
     * Frontend envia: { motivo, perigo, descricao }
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
            $map = ['alto' => 'alta', 'medio' => 'media', 'baixo' => 'baixa'];
            $dados['prioridade'] = $map[$dados['perigo'] ?? ''] ?? 'media';
        }

        $historico = $hotlist->historico ?? [];
        $historico[] = [
            'em'     => now()->toIso8601String(),
            'acao'   => 'atualizado',
            'por'    => auth()->user()->name ?? 'sistema',
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

        $historico = $hotlist->historico ?? [];
        $historico[] = [
            'em'   => now()->toIso8601String(),
            'acao' => 'resolvido',
            'por'  => auth()->user()->name ?? 'sistema',
            'nota' => $request->input('nota'),
        ];

        $hotlist->update([
            'estado'       => 'resolvido',
            'resolvido_em' => now(),
            'historico'    => $historico,
        ]);

        return response()->json([
            'message' => 'Registo marcado como resolvido.',
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