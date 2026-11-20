<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SicamIAService;

class AgenteFluxoController extends Controller
{
    public function ocr(Request $request, SicamIAService $iaService)
    {
        $request->validate([
            'imagem_base64' => 'required|string',
        ]);

        $imagem = $request->imagem_base64;
        if (!str_starts_with($imagem, 'data:image')) {
            $imagem = 'data:image/jpeg;base64,' . $imagem;
        }

        $url = env('SICAM_IA_URL', 'http://127.0.0.1:8001') . '/api/ocr';

        try {
            // 1. Pede ao Python para ler a matrícula
            $response = Http::timeout(60)->acceptJson()->post($url, [
                'imagem_base64' => $imagem,
            ]);

            if (!$response->successful()) {
                return response()->json(['ok' => false, 'matricula' => null, 'mensagem' => 'Erro no motor de IA.']);
            }

            $resultadoOcr = $response->json();

            if (!$resultadoOcr['ok'] || !$resultadoOcr['matricula']) {
                return response()->json($resultadoOcr);
            }

            $matricula = $resultadoOcr['matricula'];

            // 2. O Laravel vai à base de dados procurar o carro
            $consultaController = new AgenteConsultaController();
            $respostaConsulta = $consultaController->consultar($request, $matricula);
            $dadosVeiculo = json_decode($respostaConsulta->getContent(), true);

            // 3. Se o carro existir, pede logo a Análise à IA (Gemini)
            $analiseIA = null;
            if ($dadosVeiculo['encontrado'] === true) {
                $respostaIA = $consultaController->analiseIA($request, $matricula, $iaService);
                if ($respostaIA->getStatusCode() === 200) {
                    $analiseIA = json_decode($respostaIA->getContent(), true);
                }
            }

            // 4. Devolve o Pacote Completo para o Vue (Matrícula + Dados + IA)
            return response()->json([
                'ok' => true,
                'matricula' => $matricula,
                'veiculo' => $dadosVeiculo,
                'analiseIA' => $analiseIA
            ]);

        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha no OCR/Consulta', ['erro' => $e->getMessage()]);
            return response()->json(['ok' => false, 'matricula' => null, 'mensagem' => 'Erro de comunicação.']);
        }
    }
}