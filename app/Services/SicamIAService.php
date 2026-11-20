<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SicamIAService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = env('SICAM_IA_URL', 'http://127.0.0.1:8001');
        $this->timeout = (int) env('SICAM_IA_TIMEOUT', 30);
    }

    public function analisarVeiculo(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/analisar';
            $payload = [
                'perfil'      => 'agente',
                'entidade_id' => $dados['matricula'] ?? 'desconhecido',
                'dados'       => $dados,
            ];
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, $payload);
            if (!$response->successful()) {
                Log::error('[SICAM-IA] Resposta falhada', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \Exception('O motor de IA devolveu um erro: ' . $response->status());
            }
            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('[SICAM-IA] Sem ligação ao motor', ['erro' => $e->getMessage()]);
            throw new \Exception('Não foi possível contactar o motor de IA.');
        }
    }

    public function gerarAutoNoticia(array $dados): string
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/gerar-auto';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, $dados);
            if (!$response->successful()) {
                throw new \Exception('Erro ao gerar texto do auto.');
            }
            return $response->json()['texto'] ?? 'Texto não gerado.';
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao gerar auto', ['erro' => $e->getMessage()]);
            $auto = $dados['auto'] ?? [];
            return "AUTO DE NOTÍCIA\n\n"
                . "Código: " . ($auto['codigo'] ?? '—') . "\n"
                . "Matrícula: " . ($auto['matricula'] ?? '—') . "\n"
                . "Infracção: " . ($auto['infracao_label'] ?? '—') . "\n"
                . "Valor: " . ($auto['valor'] ?? '—') . " MT\n"
                . "Local: " . ($auto['local'] ?? '—') . "\n"
                . "Posto: " . ($auto['posto'] ?? '—') . "\n"
                . "Agente: " . ($auto['agente'] ?? '—') . "\n"
                . "Observações: " . ($auto['observacoes'] ?? '—');
        }
    }

    /**
     * ⭐ CHAT ITERATIVO — Usado pelo PrmAbordagemController::analisarIA()
     *
     * Aceita payload com:
     * - perfil (string)
     * - pergunta (string) — pergunta do utilizador
     * - contexto (array) — filtros ativos (resultado, posto, periodo)
     * - dados_reais (array) — dados da BD para a IA analisar
     */
   

    public function analisarHistorico(array $dados): ?array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/analisar-historico';
            $response = Http::timeout(15)->acceptJson()->post($url, $dados);
            if (!$response->successful()) {
                throw new \Exception('Erro ao analisar histórico: ' . $response->status());
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao analisar histórico', ['erro' => $e->getMessage()]);
            return null;
        }
    }

    public function sugerirInfracao(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/sugerir-infracao';
            $response = Http::timeout(10)->post($url, $dados);
            return $response->successful() ? $response->json() : ['sugestoes' => []];
        } catch (\Throwable) {
            return ['sugestoes' => []];
        }
    }

    public function resumirAgente(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/resumo-agente';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, ['payload' => $dados]);
            if (!$response->successful()) {
                throw new \Exception('Erro ao resumir agente: ' . $response->status());
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao resumir agente', ['erro' => $e->getMessage()]);
            return ['resumo' => 'Não foi possível gerar a análise do agente agora.'];
        }
    }

    public function analisarEscalas(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/analisar-escalas';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, ['payload' => $dados]);
            if (!$response->successful()) {
                throw new \Exception('Erro ao analisar escalas.');
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao analisar escalas', ['erro' => $e->getMessage()]);
            return ['resumo' => 'Não foi possível gerar a análise das escalas agora.'];
        }
    }

    public function resumirNotificacoes(array $notificacoes): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/resumo-notificacoes';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, ['notificacoes' => $notificacoes]);
            if (!$response->successful()) {
                throw new \Exception('Erro ao resumir notificações: ' . $response->status());
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao resumir notificações', ['erro' => $e->getMessage()]);
            return ['resumo' => 'Não foi possível gerar o resumo das notificações agora.'];
        }
    }

    public function resumirPerfil(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/resumo-perfil';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, ['payload' => $dados]);
            if (!$response->successful()) {
                throw new \Exception('Erro ao resumir perfil: ' . $response->status());
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao resumir perfil', ['erro' => $e->getMessage()]);
            return ['resumo' => 'Não foi possível gerar a análise do perfil agora.'];
        }
    }

    public function resumirPatrulhas(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/resumo-patrulhas';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, ['payload' => $dados]);
            if (!$response->successful()) {
                throw new \Exception('Erro ao resumir patrulhas: ' . $response->status());
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao resumir patrulhas', ['erro' => $e->getMessage()]);
            return ['resumo' => 'Não foi possível gerar o relatório de patrulhas agora.'];
        }
    }

    public function resumirVeiculosCriticos(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/resumo-veiculos-criticos';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, ['payload' => $dados]);
            if (!$response->successful()) {
                throw new \Exception('Erro ao resumir veículos críticos: ' . $response->status());
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao resumir veículos críticos', ['erro' => $e->getMessage()]);
            return ['resumo' => 'Não foi possível gerar a análise de veículos críticos agora.'];
        }
    }

    public function analisarAprovacoes(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/analisar-aprovacoes';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, ['payload' => $dados]);
            if (!$response->successful()) {
                throw new \Exception('Erro ao analisar aprovações: ' . $response->status());
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao analisar aprovações', ['erro' => $e->getMessage()]);
            return ['resumo' => 'Não foi possível gerar a análise das aprovações agora.'];
        }
    }

    /**
     * ⭐ ANÁLISE DE RELATÓRIO (GERAL) — Usado pelo PrmAbordagemController::analisarIA()
     *
     * Quando o utilizador NÃO faz uma pergunta, mas pede uma análise geral
     * dos dados da tabela de abordagens.
     */
    public function analisarRelatorio(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/analisar-relatorio';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, ['payload' => $dados]);

            if (!$response->successful()) {
                throw new \Exception('Erro ao analisar relatório: ' . $response->status());
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao analisar relatório', ['erro' => $e->getMessage()]);
            return [
                'resumo' => 'Não foi possível gerar a análise do relatório agora.',
                'sugestoes' => [],
            ];
        }
    }

    public function analisarHotlist(array $dados): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/analisar-hotlist';
            $response = Http::timeout($this->timeout)->acceptJson()->post($url, ['payload' => $dados]);
            if (!$response->successful()) {
                throw new \Exception('Erro ao analisar hotlist: ' . $response->status());
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao analisar hotlist', ['erro' => $e->getMessage()]);
            return ['resumo' => 'Não foi possível gerar a análise da hotlist agora.'];
        }
    }

    public function conversar(array $dados): array
    {
        try {
            // ⭐ URL corrigida: /api/chat-prm (rota exclusiva do PRM)
            $url = rtrim($this->baseUrl, '/') . '/api/chat-prm';

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($url, $dados);

            if (!$response->successful()) {
                throw new \Exception('Erro ao contactar o chat da IA.');
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha no chat', ['erro' => $e->getMessage()]);

            return [
                'resposta'  => 'Não consegui contactar o assistente agora. Tente novamente em instantes.',
                'sugestoes' => ['Ver hotlist', 'Gerar auto', 'Analisar abordagens'],
                'modelo'    => null,
            ];
        }
    }
}
