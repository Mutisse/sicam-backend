<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class NotificacaoController extends Controller
{
    /**
     * GET /api/notificacoes/contagem
     */
    public function contagem(Request $request)
    {
        $userId = $request->user()->id;
        $cacheKey = "notif_contagem_{$userId}";

        // Limpa o cache antigo se estiver a dar erro (removemos o cache antigo)
        // Para testar sem cache, vamos desativar o cache temporariamente:
        
        $colunaLida = Schema::hasColumn('notificacoes', 'lida') ? 'lida' : 'lido';

        try {
            $total = Notificacao::where('user_id', $userId)->count();
            $naoLidas = Notificacao::where('user_id', $userId)
                ->where($colunaLida, false)
                ->count();

            return response()->json([
                'total'     => $total,
                'nao_lidas' => $naoLidas,
            ]);
        } catch (\Exception $e) {
            // Se a coluna for diferente, o erro vai aparecer aqui no log
            return response()->json([
                'error' => $e->getMessage(),
                'coluna_usada' => $colunaLida
            ], 500);
        }
    }

    /**
     * GET /api/notificacoes
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $colunaLida = Schema::hasColumn('notificacoes', 'lida') ? 'lida' : 'lido';

        $query = Notificacao::where('user_id', $userId);

        if ($request->filled('lida')) {
            $query->where($colunaLida, filter_var($request->lida, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $notificacoes = $query->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id'         => (string) $n->id,
                'titulo'     => $n->titulo,
                'mensagem'   => $n->mensagem,
                'tipo'       => $n->tipo,
                'url'        => $n->url,
                'lida'       => (bool) $n->{$colunaLida},
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'notificacoes' => $notificacoes,
        ]);
    }

    public function marcarLida(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $colunaLida = Schema::hasColumn('notificacoes', 'lida') ? 'lida' : 'lido';

        $afetadas = Notificacao::where('user_id', $userId)
            ->where('id', $id)
            ->update([
                $colunaLida => true,
                'lida_em'   => now(),
            ]);

        if ($afetadas === 0) {
            return response()->json(['message' => 'Notificação não encontrada.'], 404);
        }

        Cache::forget("notif_contagem_{$userId}");
        return response()->noContent();
    }

    public function marcarTodasLidas(Request $request)
    {
        $userId = $request->user()->id;
        $colunaLida = Schema::hasColumn('notificacoes', 'lida') ? 'lida' : 'lido';

        $total = Notificacao::where('user_id', $userId)
            ->where($colunaLida, false)
            ->update([
                $colunaLida => true,
                'lida_em'   => now(),
            ]);

        Cache::forget("notif_contagem_{$userId}");
        return response()->json(['total' => $total]);
    }

    public function destroy(Request $request, int $id)
    {
        $userId = $request->user()->id;

        $afetadas = Notificacao::where('user_id', $userId)
            ->where('id', $id)
            ->delete();

        if ($afetadas === 0) {
            return response()->json(['message' => 'Notificação não encontrada.'], 404);
        }

        Cache::forget("notif_contagem_{$userId}");
        return response()->noContent();
    }
}