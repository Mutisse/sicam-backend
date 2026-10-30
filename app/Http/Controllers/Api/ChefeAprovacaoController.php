<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aprovacao;
use Illuminate\Http\Request;

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
            'id'     => (string) $a->id,
            'aprovacao' => $this->formatar($a->fresh()),
            'kpis'   => $this->kpis(),
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
            'id'     => (string) $a->id,
            'aprovacao' => $this->formatar($a->fresh()),
            'kpis'   => $this->kpis(),
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

    private function kpis(): array
    {
        $hoje = now()->startOfDay();

        return [
            'pendentes'       => Aprovacao::where('estado', 'pendente')->count(),
            'aprovadosHoje'   => Aprovacao::where('estado', 'aprovado')
                ->where('decidido_em', '>=', $hoje)->count(),
            'rejeitadosHoje'  => Aprovacao::where('estado', 'rejeitado')
                ->where('decidido_em', '>=', $hoje)->count(),
            'tempoMedio'      => 12,
        ];
    }
}