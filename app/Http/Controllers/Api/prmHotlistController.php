<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotlist;
use App\Models\Veiculo;
use App\Services\NotificacaoService;
use Illuminate\Http\Request;

class HotlistController extends Controller
{
    /**
     * GET /api/hotlist
     */
    public function index(Request $request)
    {
        // ⭐ Eager loading COMPLETO (evita N+1)
        $query = Hotlist::with([
            'veiculo:id,matricula,marca,modelo,cor,provincia,posto_id',
            'veiculo.posto:id,nome',
        ]);

        // ⭐ JOIN direto (mais rápido que whereHas)
        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->join('veiculos', 'veiculos.id', '=', 'hotlist.veiculo_id')
                ->where(function ($sub) use ($q) {
                    $sub->where('veiculos.matricula', 'like', "%{$q}%")
                        ->orWhere('veiculos.marca', 'like', "%{$q}%")
                        ->orWhere('veiculos.modelo', 'like', "%{$q}%");
                })
                ->select('hotlist.*'); // evita colisão de colunas
        }

        if ($request->filled('motivo')) {
            $query->where('hotlist.motivo', $request->motivo);
        }
        if ($request->filled('perigo')) {
            $query->where('hotlist.perigo', $request->perigo);
        }
        if ($request->filled('estado')) {
            $query->where('hotlist.estado', $request->estado);
        }

        $items = $query->orderByDesc('hotlist.created_at')
            ->get()
            ->map(fn ($h) => $this->formatar($h));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/hotlist/kpis
     */
    public function kpisEndpoint()
    {
        return response()->json($this->kpis());
    }

    /**
     * ⭐ KPIs — 1 única query
     */
    private function kpis(): array
    {
        $row = Hotlist::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN motivo = 'Furtado' THEN 1 ELSE 0 END) as furtados,
            SUM(CASE WHEN motivo = 'Clonagem suspeita' THEN 1 ELSE 0 END) as clonagem,
            SUM(CASE WHEN motivo = 'Mandado captura' THEN 1 ELSE 0 END) as mandados,
            SUM(CASE WHEN perigo = 'alto' AND estado = 'Activo' THEN 1 ELSE 0 END) as altos,
            SUM(CASE WHEN estado = 'Activo' THEN 1 ELSE 0 END) as ativos
        ")->first();

        return [
            'total'     => (int) $row->total,
            'furtados'  => (int) $row->furtados,
            'clonagem'  => (int) $row->clonagem,
            'mandados'  => (int) $row->mandados,
            'altos'     => (int) $row->altos,
            'ativos'    => (int) $row->ativos,
        ];
    }

    /**
     * Formatar um item
     */
    private function formatar(Hotlist $h): array
    {
        $v = $h->veiculo;

        return [
            'id'           => (string) $h->id,
            'matricula'    => $v?->matricula ?? '—',
            'marca'        => $v?->marca ?? '—',
            'modelo'       => $v?->modelo ?? '—',
            'cor'          => $v?->cor ?? '—',
            'motivo'       => $h->motivo,
            'nivel'        => ucfirst($h->perigo),
            'origem'       => 'SICAM',
            'dataInsercao' => $h->created_at?->format('d/m/Y') ?? '—',
            'horaInsercao' => $h->created_at?->format('H:i') ?? '—',
            'local'        => $v?->provincia ?? '—',
            'posto'        => $v?->posto?->nome ?? '—',
            'agente'       => 'Sistema',
            'abordagens'   => 0,
            'estado'       => $h->estado === 'Activo' ? 'Ativo' : 'Resolvido',
            'observacoes'  => $h->descricao ?? '',
        ];
    }

    /**
     * POST /api/hotlist
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula' => 'required|string|max:20',
            'motivo'    => 'required|string',
            'perigo'    => 'required|in:alto,medio,baixo',
            'descricao' => 'nullable|string',
        ]);

        $veiculo = Veiculo::firstOrCreate(
            ['matricula' => strtoupper($dados['matricula'])],
            ['marca' => '—', 'modelo' => '—', 'score' => 0, 'estado' => 'sem-dados']
        );

        $hotlist = Hotlist::create([
            'veiculo_id'   => $veiculo->id,
            'motivo'       => $dados['motivo'],
            'perigo'       => $dados['perigo'],
            'estado'       => 'Activo',
            'descricao'    => $dados['descricao'] ?? null,
            'detectado_em' => now(),
        ]);

        // ═══════════════════════════════════════════════════════
        // 🔔 GATILHO — Veículo adicionado à hotlist
        // ═══════════════════════════════════════════════════════
        NotificacaoService::veiculoAdicionadoHotlist(
            $veiculo->matricula,
            $dados['motivo'],
            $dados['perigo']
        );

        return response()->json($hotlist, 201);
    }

    /**
     * PUT /api/hotlist/{id}
     */
    public function update(Request $request, int $id)
    {
        $hotlist = Hotlist::findOrFail($id);

        $dados = $request->validate([
            'motivo'    => 'sometimes|string',
            'perigo'    => 'sometimes|in:alto,medio,baixo',
            'estado'    => 'sometimes|in:Activo,Resolvido',
            'descricao' => 'nullable|string',
        ]);

        $hotlist->update($dados);

        if (($dados['estado'] ?? null) === 'Resolvido') {
            $hotlist->resolvido_em = now();
            $hotlist->save();

            // 🔔 GATILHO — Veículo resolvido
            NotificacaoService::veiculoResolvido(
                $hotlist->veiculo?->matricula ?? '—',
                $hotlist->motivo
            );
        }

        return response()->json($hotlist);
    }

    /**
     * PUT /api/hotlist/{id}/resolver
     */
    public function resolver(int $id)
    {
        $hotlist = Hotlist::with('veiculo:id,matricula')->findOrFail($id);
        $hotlist->update([
            'estado'       => 'Resolvido',
            'resolvido_em' => now(),
        ]);

        // ═══════════════════════════════════════════════════════
        // 🔔 GATILHO — Veículo resolvido
        // ═══════════════════════════════════════════════════════
        NotificacaoService::veiculoResolvido(
            $hotlist->veiculo?->matricula ?? '—',
            $hotlist->motivo
        );

        return response()->noContent();
    }

    /**
     * DELETE /api/hotlist/{id}
     */
    public function destroy(int $id)
    {
        Hotlist::findOrFail($id)->delete();
        return response()->noContent();
    }
}