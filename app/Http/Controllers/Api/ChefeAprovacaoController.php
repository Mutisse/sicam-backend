<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aprovacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChefeAprovacaoController extends Controller
{
    /**
     * GET /api/chefe/aprovacoes
     */
    public function index(Request $request)
    {
        $query = Aprovacao::query();

        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('tipo'))       $query->where('tipo', $request->tipo);
        if ($request->filled('prioridade')) $query->where('prioridade', $request->prioridade);
        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('titulo', 'like', "%{$q}%")
                    ->orWhere('matricula', 'like', "%{$q}%")
                    ->orWhere('agente', 'like', "%{$q}%")
                    ->orWhere('posto', 'like', "%{$q}%");
            });
        }

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
     * GET /api/chefe/aprovacoes/{id}
     */
    public function show(int $id)
    {
        $a = Aprovacao::findOrFail($id);
        return response()->json($this->formatar($a));
    }

    /**
     * PUT /api/chefe/aprovacoes/{id}/aprovar
     */
    public function aprovar(Request $request, int $id)
    {
        $a = Aprovacao::findOrFail($id);

        $a->update([
            'estado'       => 'aprovado',
            'decidido_por' => $request->input('decidido_por', $request->user()?->name ?? 'Chefe de Posto'),
            'decidido_em'  => now(),
        ]);

        return response()->json([
            'id'        => (string) $a->id,
            'aprovacao' => $this->formatar($a),
            'kpis'      => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/chefe/aprovacoes/{id}/rejeitar
     */
    public function rejeitar(Request $request, int $id)
    {
        $a = Aprovacao::findOrFail($id);

        $dados = $request->validate([
            'motivo_rejeicao' => 'required|string|min:10',
            'decidido_por'    => 'nullable|string|max:120',
        ]);

        $a->update([
            'estado'          => 'rejeitado',
            'motivo_rejeicao' => $dados['motivo_rejeicao'],
            'decidido_por'    => $dados['decidido_por'] ?? $request->user()?->name ?? 'Chefe de Posto',
            'decidido_em'     => now(),
        ]);

        return response()->json([
            'id'        => (string) $a->id,
            'aprovacao' => $this->formatar($a),
            'kpis'      => $this->kpis(),
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Aprovacao $a): array
    {
        return [
            'id'             => (string) $a->id,
            'codigo'         => $a->codigo,
            'tipo'           => $a->tipo,
            'titulo'         => $a->titulo,
            'matricula'      => $a->matricula ?? '—',
            'agente'         => $a->agente ?? '—',
            'posto'          => $a->posto ?? '—',
            'prioridade'     => $a->prioridade ?? 'Média',
            'estado'         => $a->estado ?? 'pendente',
            'detalhes'       => $a->detalhes ?? [],
            'motivoRejeicao' => $a->motivo_rejeicao,
            'decididoPor'    => $a->decidido_por,
            'decididoEm'     => $a->decidido_em?->format('d/m/Y H:i'),
            'hora'           => $a->created_at?->diffForHumans() ?? '—',
            'criadoEm'       => $a->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    /**
     * KPIs — 1 query com SUM(CASE).
     */
    private function kpis(): array
    {
        $hoje = now()->startOfDay();

        $row = Aprovacao::selectRaw("
            SUM(CASE WHEN estado = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN estado = 'aprovado' AND decidido_em >= ? THEN 1 ELSE 0 END) as aprovados_hoje,
            SUM(CASE WHEN estado = 'rejeitado' AND decidido_em >= ? THEN 1 ELSE 0 END) as rejeitados_hoje
        ", [$hoje, $hoje])->first();

        return [
            'pendentes'      => (int) $row->pendentes,
            'aprovadosHoje'  => (int) $row->aprovados_hoje,
            'rejeitadosHoje' => (int) $row->rejeitados_hoje,
            'tempoMedio'     => 12, // stub — não há dados reais de tempo
        ];
    }
}