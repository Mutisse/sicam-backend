<?php
// app/Http/Controllers/Api/AgenteAutoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoNoticia;
use App\Models\Veiculo;
use App\Services\SicamIAService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgenteAutoController extends Controller
{
    protected SicamIAService $iaService;

    public function __construct(SicamIAService $iaService)
    {
        $this->iaService = $iaService;
    }

    public function index(Request $request)
    {
        $eu = $request->user();
        $hoje = Carbon::today();

        $items = AutoNoticia::query()
            ->where('agente_id', $eu->id)
            ->where('emitido_em', '>=', $hoje)
            ->orderByDesc('emitido_em')
            ->limit(100)
            ->get()
            ->map(fn ($a) => $this->formatar($a));

        return response()->json([
            'items'     => $items,
            'totalHoje' => $items->count(),
            'infracoes' => config('infracoes'),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula'       => 'required|string|max:20',
            'infracao_codigo' => 'required|string|max:40',
            'local'           => 'nullable|string|max:160',
            'observacoes'     => 'nullable|string|max:2000',
        ]);

        $infracao = collect(config('infracoes'))
            ->firstWhere('codigo', $dados['infracao_codigo']);

        if (!$infracao) {
            return response()->json(['message' => 'Infracção inválida.'], 422);
        }

        $eu = $request->user();

        $auto = DB::transaction(function () use ($dados, $infracao, $eu) {
            $codigo = $this->gerarCodigo();

            return AutoNoticia::create([
                'codigo'          => $codigo,
                'matricula'       => strtoupper($dados['matricula']),
                'infracao_codigo' => $infracao['codigo'],
                'infracao_label'  => $infracao['label'],
                'valor'           => $infracao['valor'],
                'local'           => $dados['local'] ?? null,
                'observacoes'     => $dados['observacoes'] ?? null,
                'agente_id'       => $eu->id,
                'posto'           => $eu->posto ?? 'Posto',
                'estado'          => 'Emitido',
                'emitido_em'      => now(),
            ]);
        });

        // ⭐ Recolher contexto do veículo para a IA
        $veiculo = Veiculo::where('matricula', $auto->matricula)->first();
        $dadosVeiculo = [
            'matricula'         => $auto->matricula,
            'score'             => $veiculo?->score ?? 0,
            'multas_pendentes'  => $veiculo ? DB::table('multas')->where('veiculo_id', $veiculo->id)->where('estado', 'Pendente')->count() : 0,
            'seguro_valido'     => false,
            'inspecao_valida'   => false,
        ];

        $textoGerado = $this->iaService->gerarAutoNoticia([
            'auto' => [
                'codigo'         => $auto->codigo,
                'matricula'      => $auto->matricula,
                'infracao_label' => $auto->infracao_label,
                'valor'          => $auto->valor,
                'local'          => $auto->local,
                'posto'          => $auto->posto,
                'observacoes'    => $auto->observacoes,
                'agente'         => $eu->name,
            ],
            'veiculo' => $dadosVeiculo,
        ]);

        // Guardar o texto gerado no campo observacoes (ou campo separado se preferires)
        $auto->update(['observacoes' => $textoGerado]);

        return response()->json([
            'auto'  => $this->formatar($auto),
            'texto' => $textoGerado,
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $eu = $request->user();
        $auto = AutoNoticia::where('agente_id', $eu->id)->findOrFail($id);

        return response()->json([
            'auto'  => $this->formatar($auto),
            'texto' => $auto->observacoes,
        ]);
    }

    /**
     * GET /api/agente/autos/{id}/pdf
     */
    public function pdf(Request $request, int $id)
    {
        $eu = $request->user();
        $auto = AutoNoticia::where('agente_id', $eu->id)->findOrFail($id);

        $pdf = Pdf::loadView('pdf.auto_noticia', [
            'auto' => $auto,
        ]);

        return $pdf->download("auto-noticia-{$auto->codigo}.pdf");
    }

    private function formatar(AutoNoticia $a): array
    {
        return [
            'id'             => (string) $a->id,
            'codigo'         => $a->codigo,
            'matricula'      => $a->matricula,
            'infracaoCodigo' => $a->infracao_codigo,
            'infracaoLabel'  => $a->infracao_label,
            'valor'          => (float) $a->valor,
            'local'          => $a->local,
            'observacoes'    => $a->observacoes,
            'posto'          => $a->posto,
            'estado'         => $a->estado,
            'hora'           => $a->emitido_em?->format('H:i') ?? '—',
            'data'           => $a->emitido_em?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function gerarCodigo(): string
    {
        $ano = now()->format('y');
        $ultimo = AutoNoticia::where('codigo', 'like', "AN-{$ano}-%")
            ->orderByDesc('id')
            ->value('codigo');

        $seq = 1;
        if ($ultimo && preg_match('/-(\d+)$/', $ultimo, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('AN-%s-%05d', $ano, $seq);
    }
}