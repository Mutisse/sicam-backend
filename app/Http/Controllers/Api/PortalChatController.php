<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PortalChatController extends Controller
{
    /**
     * POST /api/portal/chat
     *
     * Recebe { mensagem: string } e devolve { resposta: string }.
     * Respostas baseadas em regras simples (FAQ).
     */
    public function responder(Request $request)
    {
        $request->validate([
            'mensagem' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $texto = $this->normalizar($request->input('mensagem'));

        $resposta = $this->procurarResposta($texto);

        return response()->json([
            'resposta' => $resposta,
        ]);
    }

    private function normalizar(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        return $s;
    }

    private function procurarResposta(string $q): string
    {
        if (str_contains($q, 'carta')) {
            return 'Para renovar a carta de condução deve dirigir-se a um centro do INATRO com: carta actual, exame médico, 2 fotografias e comprovativo de residência. O prazo é 30 dias antes da validade.';
        }

        if (str_contains($q, 'multa')) {
            return 'Pode pagar multas no portal do GESTMUL, em postos de atendimento ou por transferência bancária. Tem 15 dias para contestar. Guarde sempre o comprovativo.';
        }

        if (str_contains($q, 'seguro')) {
            return 'O seguro obrigatório deve estar sempre válido. Sem seguro é considerada infracção grave. Verifique a data de validade na sua apólice.';
        }

        if (str_contains($q, 'inspec')) {
            return 'A inspecção é: a cada 2 anos (até 4 anos), anual (4 a 8 anos) e semestral (mais de 8 anos). Leve documento do veículo, seguro válido e o veículo limpo.';
        }

        if (str_contains($q, 'veiculo') || str_contains($q, 'matricula')) {
            return 'Para consultar os dados de um veículo, use o serviço de validação de matrícula no portal ou dirija-se a um posto de atendimento.';
        }

        return 'Não tenho ainda informação específica sobre isso. Experimente perguntar sobre carta, multas, seguro ou inspecção. Para questões mais detalhadas, contacte o posto mais próximo.';
    }
}