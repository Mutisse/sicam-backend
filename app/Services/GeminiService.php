<?php

namespace App\Services;

use Gemini\Laravel\Facades\Gemini;

class GeminiService
{
    public function gerarTextoAuto(array $dados): string
    {
        $prompt = "És um assistente da Polícia da República de Moçambique (PRM). 
Gera o texto formal de um Auto de Notícia com base nos seguintes dados:
- Matrícula: {$dados['matricula']}
- Infracção: {$dados['infracao_label']} (Código: {$dados['infracao_codigo']})
- Valor da multa: {$dados['valor']} Meticais
- Local: " . ($dados['local'] ?? 'Não especificado') . "
- Posto: {$dados['posto']}
- Observações do agente: " . ($dados['observacoes'] ?? 'Nenhuma') . "

O texto deve ser formal, em português de Moçambique, e incluir uma referência ao Código de Estrada. 
Devolve APENAS o texto do auto, sem introduções ou explicações.";

        // Tenta falar com o modelo. Se falhar, dispara o erro real para a tela.
        $resposta = Gemini::generativeModel(model: 'gemini-2.0-flash')
            ->generateContent($prompt);

        return trim($resposta->text());
    }
}