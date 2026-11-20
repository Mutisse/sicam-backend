<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacao;
use App\Services\SicamIAService;
use App\Services\NotificacaoService; // ⭐ Importado
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    protected SicamIAService $ia;

    // ⭐ Injeção de dependência
    public function __construct(SicamIAService $ia)
    {
        $this->ia = $ia;
    }

    /**
     * GET /api/notificacoes
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $query = Notificacao::where('user_id', $userId);

        if ($request->filled('lida')) {
            $query->where('lida', filter_var($request->lida, FILTER_VALIDATE_BOOLEAN));
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
     */
    public function contagem(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $total = Notificacao::where('user_id', $userId)->count();
            $naoLidas = Notificacao::where('user_id', $userId)->where('lida', 0)->count();

            return response()->json([
                'total'     => $total,
                'nao_lidas' => $naoLidas,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/notificacoes/resumo-ia
     */
    public function resumoIA(Request $request)
    {
        $userId = $request->user()->id;

        // ⭐ Chama o NotificacaoService, passando o SicamIAService
        $resposta = NotificacaoService::resumirNotificacoesComIA($userId, $this->ia);

        return response()->json($resposta);
    }

    /**
     * PUT /api/notificacoes/{id}/ler
     */
    public function marcarLida(Request $request, int $id)
    {
        $userId = $request->user()->id;

        $afetadas = Notificacao::where('user_id', $userId)
            ->where('id', $id)
            ->update(['lida' => true, 'lida_em' => now()]);

        if ($afetadas === 0) {
            return response()->json(['message' => 'Notificação não encontrada.'], 404);
        }

        return response()->noContent();
    }

    /**
     * PUT /api/notificacoes/ler-todas
     */
    public function marcarTodasLidas(Request $request)
    {
        $userId = $request->user()->id;

        $total = Notificacao::where('user_id', $userId)
            ->where('lida', false)
            ->update(['lida' => true, 'lida_em' => now()]);

        return response()->json(['total' => $total]);
    }

    /**
     * DELETE /api/notificacoes/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $userId = $request->user()->id;

        $afetadas = Notificacao::where('user_id', $userId)
            ->where('id', $id)
            ->delete();

        if ($afetadas === 0) {
            return response()->json(['message' => 'Notificação não encontrada.'], 404);
        }

        return response()->noContent();
    }

    /**
     * Helper — tempo relativo
     */
    private function tempoRelativo($data): string
    {
        if (!$data) return '—';
        $diff = (int) now()->diffInMinutes($data, true);
        if ($diff < 1)  return 'agora';
        if ($diff < 60) return "há {$diff} min";
        $horas = intdiv($diff, 60);
        if ($horas < 24) return "há {$horas} h";
        $dias = intdiv($horas, 24);
        return "há {$dias} d";
    }
}