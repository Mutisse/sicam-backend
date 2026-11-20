<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Multa;
use App\Models\Veiculo;
use App\Services\SicamIAService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgenteMultaController extends Controller
{
    protected SicamIAService $ia;

    public function __construct(SicamIAService $ia)
    {
        $this->ia = $ia;
    }

    /**
     * GET /api/agente/multa/sugerir
     * Analisa o veículo e sugere infrações.
     */
    public function sugerir(Request $request)
    {
        $request->validate(['matricula' => 'required|string']);
        $matricula = strtoupper($request->matricula);
        
        $veiculo = Veiculo::where('matricula', $matricula)->first();
        if (!$veiculo) {
            return response()->json(['sugestoes' => []]);
        }

        $multasPendentes = DB::table('multas')->where('veiculo_id', $veiculo->id)->where('estado', 'Pendente')->count();
        
        try {
            $analise = $this->ia->sugerirInfracao([
                'matricula' => $matricula,
                'score' => $veiculo->score,
                'multas_pendentes' => $multasPendentes,
                'marca' => $veiculo->marca,
                'modelo' => $veiculo->modelo,
            ]);

            return response()->json($analise);
        } catch (\Throwable $e) {
            Log::error('Erro ao sugerir infração', ['erro' => $e->getMessage()]);
            return response()->json(['sugestoes' => []]);
        }
    }

    /**
     * POST /api/agente/multa
     * Aplica a multa no banco e gera texto jurídico com a IA.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula' => 'required|string',
            'infracao_codigo' => 'required|string',
            'infracao_label' => 'required|string',
            'valor' => 'required|numeric',
            'chapa' => 'required|string',
            'observacoes' => 'nullable|string',
        ]);

        $eu = $request->user();
        $veiculo = Veiculo::where('matricula', strtoupper($dados['matricula']))->first();

        // 1. Criar multa no banco
        $multa = Multa::create([
            'veiculo_id' => $veiculo?->id,
            'agente_id' => $eu->id,
            'codigo' => 'MT-' . strtoupper(uniqid()),
            'infracao' => $dados['infracao_label'],
            'valor' => $dados['valor'],
            'estado' => 'Pendente',
            'chapa' => $dados['chapa'],
            'observacoes' => $dados['observacoes'] ?? null,
            'posto' => $eu->posto ?? 'Posto',
            'created_at' => now(),
        ]);

        // 2. IA: Gerar o texto jurídico do auto
        $textoJuridico = "Multa aplicada referente à infração: {$dados['infracao_label']}.";
        try {
            $textoJuridico = $this->ia->gerarAutoNoticia([
                'auto' => [
                    'codigo' => $multa->codigo,
                    'matricula' => $dados['matricula'],
                    'infracao_label' => $dados['infracao_label'],
                    'valor' => $dados['valor'],
                    'local' => 'Via Pública',
                    'posto' => $eu->posto,
                    'observacoes' => $dados['observacoes'],
                    'agente' => $eu->name,
                ],
                'veiculo' => [
                    'matricula' => $dados['matricula'],
                    'score' => $veiculo?->score ?? 0,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao gerar texto IA para multa', ['erro' => $e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'auto' => [
                'id' => $multa->id,
                'codigo' => $multa->codigo,
                'matricula' => $multa->matricula ?? $dados['matricula'],
                'infracao' => $multa->infracao,
                'valor' => number_format($multa->valor, 2, ',', '.') . ' MT',
                'chapa' => $multa->chapa,
                'data' => now()->format('d/m Y H:i'),
            ],
            'texto_ia' => $textoJuridico
        ]);
    }

    /**
     * GET /api/agente/multa/{id}/pdf
     */
    public function pdf(Request $request, $id)
    {
        $multa = Multa::findOrFail($id);
        
        $pdf = Pdf::loadView('pdf.multa', [
            'multa' => $multa,
            'agente' => $request->user()
        ]);

        return $pdf->download("multa-{$multa->codigo}.pdf");
    }
}