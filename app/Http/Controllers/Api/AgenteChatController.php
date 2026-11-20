<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SicamIAService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgenteChatController extends Controller
{
    protected SicamIAService $iaService;

    public function __construct(SicamIAService $iaService)
    {
        $this->iaService = $iaService;
    }

    /**
     * GET /api/agente/chat/iniciar
     *
     * Devolve a saudação inicial e as sugestões iniciais.
     * Sem nada hardcoded no frontend.
     */
    public function iniciar(Request $request)
    {
        $eu = $request->user();

        $nome  = $eu->name ?? 'Agente';
        $posto = $eu->posto ?? null;

        $mensagem = $posto
            ? "Olá {$nome}. Sou o Copiloto SICAM do posto {$posto}. Em que posso ajudar?"
            : "Olá {$nome}. Sou o Copiloto SICAM. Em que posso ajudar?";

        return response()->json([
            'ok'               => true,
            'mensagem_inicial' => $mensagem,
            'sugestoes'        => [
                'Procedimento para seguro expirado',
                'Como gerar um auto de notícia?',
                'Prazo para recurso de multa',
            ],
        ]);
    }

    /**
     * POST /api/agente/chat
     */
    public function enviar(Request $request)
    {
        $dados = $request->validate([
            'mensagem'          => 'required|string|max:2000',
            'historico'         => 'nullable|array|max:20',
            'historico.*.autor' => 'required_with:historico|in:bot,user',
            'historico.*.texto' => 'required_with:historico|string|max:2000',
        ]);

        $eu = $request->user();

        // Contexto do agente para dar personalidade à IA
        $contexto = [
            'agente_nome' => $eu->name ?? 'Agente',
            'posto'       => $eu->posto ?? '—',
            'perfil'      => $eu->perfil?->key ?? 'agente',
        ];

        try {
            $resposta = $this->iaService->conversar([
                'mensagem'  => $dados['mensagem'],
                'historico' => $dados['historico'] ?? [],
                'contexto'  => $contexto,
            ]);

            return response()->json([
                'ok'        => true,
                'resposta'  => $resposta['resposta'] ?? 'Não consegui responder.',
                'sugestoes' => $resposta['sugestoes'] ?? [],
                'modelo'    => $resposta['modelo'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha no chat', ['erro' => $e->getMessage()]);

            return response()->json([
                'ok'        => false,
                'resposta'  => 'Não consegui contactar o assistente. Tente novamente.',
                'sugestoes' => [],
            ], 200);
        }
    }
}