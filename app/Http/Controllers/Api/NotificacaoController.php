<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacao;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    /**
     * GET /api/notificacoes
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Notificacao::where('user_id', $user->id);

        // Filtros
        if ($request->filled('lida')) {
            $query->where('lida', filter_var($request->lida, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        $notificacoes = $query->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id'         => (string) $n->id,
                'titulo'     => $n->titulo,
                'mensagem'   => $n->mensagem,
                'tipo'       => $n->tipo,
                'categoria'  => $n->categoria,
                'referencia' => $n->referencia,
                'url'        => $n->url,
                'lida'       => (bool) $n->lida,
                'criada_em'  => $n->created_at?->format('d/m H:i') ?? '—',
                'hora'       => $this->tempoRelativo($n->created_at),
            ]);

        return response()->json([
            'notificacoes' => $notificacoes,
        ]);
    }

    /**
     * GET /api/notificacoes/contagem
     * ⭐ 1 query só com SUM(CASE WHEN)
     */
    public function contagem(Request $request)
    {
        $user = $request->user();

        $row = Notificacao::where('user_id', $user->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN lida = 0 THEN 1 ELSE 0 END) as nao_lidas
            ")
            ->first();

        return response()->json([
            'total'     => (int) $row->total,
            'nao_lidas' => (int) $row->nao_lidas,
        ]);
    }

    /**
     * PUT /api/notificacoes/{id}/ler
     */
    public function marcarLida(Request $request, int $id)
    {
        $user = $request->user();

        // ⭐ 1 query só (findOrFail + update)
        Notificacao::where('user_id', $user->id)
            ->where('id', $id)
            ->update([
                'lida'    => true,
                'lida_em' => now(),
            ]);

        return response()->noContent();
    }

    /**
     * PUT /api/notificacoes/ler-todas
     */
    public function marcarTodasLidas(Request $request)
    {
        $user = $request->user();

        // ⭐ 1 query — e devolve o total marcado
        $total = Notificacao::where('user_id', $user->id)
            ->where('lida', false)
            ->update([
                'lida'    => true,
                'lida_em' => now(),
            ]);

        return response()->json(['total' => $total]);
    }

    /**
     * DELETE /api/notificacoes/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        Notificacao::where('user_id', $user->id)
            ->where('id', $id)
            ->delete();

        return response()->noContent();
    }

    /**
     * Helper — tempo relativo
     */
    private function tempoRelativo($data): string
    {
        if (!$data) return '—';
        $diff = now()->diffInMinutes($data);
        if ($diff < 1) return 'agora';
        if ($diff < 60) return "há {$diff} min";
        $horas = floor($diff / 60);
        if ($horas < 24) return "há {$horas} h";
        $dias = floor($horas / 24);
        return "há {$dias} d";
    }
}