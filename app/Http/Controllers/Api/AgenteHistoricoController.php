<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultaAgente;
use App\Models\Veiculo;
use App\Services\SicamIAService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgenteHistoricoController extends Controller
{
    /**
     * GET /api/agente/historico
     */
    public function index(Request $request)
    {
        $eu = $request->user();
        if (!$eu) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $query = ConsultaAgente::query()
            ->where('agente_id', $eu->id)
            ->orderByDesc('consultado_em');

        $periodo = $request->get('periodo', 'tudo');
        $desde = match ($periodo) {
            'hoje'  => Carbon::today(),
            '7d'    => Carbon::now()->subDays(7),
            '30d'   => Carbon::now()->subDays(30),
            default => null,
        };
        if ($desde) {
            $query->where('consultado_em', '>=', $desde);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($s) use ($q) {
                $s->where('matricula', 'like', "%{$q}%")
                  ->orWhere('posto', 'like', "%{$q}%");
            });
        }

        $items = $query->limit(500)->get()->map(fn ($c) => $this->formatar($c));

        return response()->json([
            'items' => $items,
            'total' => $items->count(),
            'kpis'  => $this->kpis($eu->id),
        ]);
    }

    /**
     * POST /api/agente/historico
     */
    public function store(Request $request)
    {
        $eu = $request->user();
        if (!$eu) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $dados = $request->validate([
            'matricula' => 'required|string|max:20',
            'origem'    => 'nullable|string|in:manual,camera,historico',
        ]);

        $veiculo = Veiculo::where('matricula', strtoupper($dados['matricula']))->first();

        $consulta = ConsultaAgente::create([
            'agente_id'     => $eu->id,
            'veiculo_id'    => $veiculo?->id,
            'matricula'     => strtoupper($dados['matricula']),
            'posto'         => $eu->posto ?? null,
            'score'         => $veiculo?->score,
            'estado'        => $this->estadoDoScore($veiculo?->score),
            'origem'        => $dados['origem'] ?? 'manual',
            'consultado_em' => now(),
        ]);

        return response()->json([
            'consulta' => $this->formatar($consulta),
        ], 201);
    }

    /**
     * GET /api/agente/historico/xlsx
     */
    public function xlsx(Request $request)
    {
        $eu = $request->user();

        $items = ConsultaAgente::where('agente_id', $eu->id)
            ->orderByDesc('consultado_em')
            ->limit(2000)
            ->get();

        $csv = "Matrícula,Posto,Score,Estado,Data,Origem\n";
        foreach ($items as $c) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $c->matricula,
                $c->posto ?? '—',
                $c->score ?? '—',
                $c->estado ?? '—',
                $c->consultado_em?->format('d/m/Y H:i') ?? '—',
                $c->origem ?? '—',
            );
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="historico-' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * POST /api/agente/historico/analisar
     */
    public function analisar(Request $request, SicamIAService $ia)
    {
        $eu = $request->user();
        if (!$eu) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $desde = Carbon::now()->subDays(30);

        $consultas = ConsultaAgente::where('agente_id', $eu->id)
            ->where('consultado_em', '>=', $desde)
            ->orderByDesc('consultado_em')
            ->limit(200)
            ->get();

        if ($consultas->isEmpty()) {
            return response()->json([
                'ok'        => true,
                'analiseIA' => [
                    'resumo'           => 'Ainda não tens consultas nos últimos 30 dias. Começa a consultar matrículas para a IA analisar o teu padrão de trabalho.',
                    'alertas'          => [],
                    'recomendacoes'    => ['Faz a tua primeira consulta no ecrã "Consultar".'],
                    'padrao_dominante' => '',
                    'nivel_atividade'  => 'baixo',
                    'modelo_usado'     => null,
                ],
            ]);
        }

        $matriculasRepetidas = $consultas
            ->groupBy('matricula')
            ->map(fn ($g) => [
                'matricula' => $g->first()->matricula,
                'vezes'     => $g->count(),
                'ultima'    => $g->first()->consultado_em?->toIso8601String(),
            ])
            ->filter(fn ($m) => $m['vezes'] > 1)
            ->sortByDesc('vezes')
            ->values()
            ->take(5)
            ->toArray();

        $porHora = $consultas
            ->groupBy(fn ($c) => $c->consultado_em?->format('H') ?? '—')
            ->map(fn ($g, $h) => ['hora' => $h, 'total' => $g->count()])
            ->sortByDesc('total')
            ->values()
            ->take(3)
            ->toArray();

        $porEstado = [
            'critico'   => $consultas->where('estado', 'critico')->count(),
            'atencao'   => $consultas->where('estado', 'atencao')->count(),
            'regular'   => $consultas->where('estado', 'regular')->count(),
            'sem-dados' => $consultas->where('estado', 'sem-dados')->count(),
        ];

        $matriculasCriticas = $consultas
            ->where('estado', 'critico')
            ->pluck('matricula')
            ->unique()
            ->take(10)
            ->values()
            ->toArray();

        // ⭐ CORREÇÃO: leftJoin na tabela veiculos, pois 'matricula' está lá
        $emHotlist = [];
        if (!empty($matriculasCriticas)) {
            $emHotlist = DB::table('hotlist')
                ->leftJoin('veiculos', 'veiculos.id', '=', 'hotlist.veiculo_id')
                ->whereIn('veiculos.matricula', $matriculasCriticas)
                ->where('hotlist.estado', 'Activo')
                ->pluck('veiculos.matricula')
                ->toArray();
        }

        try {
            $analise = $ia->analisarHistorico([
                'agente_nome'           => $eu->name,
                'posto'                 => $eu->posto ?? '—',
                'total_consultas'       => $consultas->count(),
                'por_estado'            => $porEstado,
                'matriculas_repetidas'  => $matriculasRepetidas,
                'horas_de_pico'         => $porHora,
                'matriculas_em_hotlist' => $emHotlist,
                'periodo_dias'          => 30,
            ]);

            if ($analise === null) {
                return response()->json([
                    'ok'        => true,
                    'analiseIA' => [
                        'resumo'           => "Tens {$consultas->count()} consultas nos últimos 30 dias. A análise IA está indisponível neste momento.",
                        'alertas'          => [],
                        'recomendacoes'    => [],
                        'padrao_dominante' => '',
                        'nivel_atividade'  => 'medio',
                        'modelo_usado'     => null,
                    ],
                ]);
            }

            return response()->json([
                'ok'        => true,
                'analiseIA' => $analise,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Falha ao analisar histórico', [
                'erro' => $e->getMessage(),
            ]);

            return response()->json([
                'ok'        => false,
                'analiseIA' => null,
                'mensagem'  => 'Não foi possível analisar o histórico agora.',
            ], 200);
        }
    }

    /**
     * GET /api/agente/historico/pdf
     *
     * Gera um PDF com os KPIs, a análise IA (se disponível) e a lista filtrada.
     */
    public function pdf(Request $request, SicamIAService $ia)
    {
        $eu = $request->user();
        if (!$eu) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        // 1. Filtros iguais aos do index
        $query = ConsultaAgente::query()
            ->where('agente_id', $eu->id)
            ->orderByDesc('consultado_em');

        $periodo = $request->get('periodo', 'tudo');
        $desde = match ($periodo) {
            'hoje'  => Carbon::today(),
            '7d'    => Carbon::now()->subDays(7),
            '30d'   => Carbon::now()->subDays(30),
            default => null,
        };
        if ($desde) {
            $query->where('consultado_em', '>=', $desde);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($s) use ($q) {
                $s->where('matricula', 'like', "%{$q}%")
                  ->orWhere('posto', 'like', "%{$q}%");
            });
        }

        $items = $query->limit(500)->get();

        // 2. KPIs
        $kpis = $this->kpis($eu->id);

        // 3. Análise IA (por defeito tenta; passa ?ia=0 para desligar)
        $analiseIA = null;
        if ($request->boolean('ia', true)) {
            try {
                $analiseIA = $this->buildAnaliseIA($eu, $ia);
            } catch (\Throwable $e) {
                Log::error('[SICAM-IA] PDF: falha na análise IA', [
                    'erro' => $e->getMessage(),
                ]);
                $analiseIA = null;
            }
        }

        // 4. Gera o PDF
        $pdf = Pdf::loadView('pdf.historico', [
            'agente'      => $eu,
            'items'       => $items,
            'kpis'        => $kpis,
            'analiseIA'   => $analiseIA,
            'periodo'     => $periodo,
            'estado'      => $request->get('estado', 'todos'),
            'busca'       => $request->get('q', ''),
            'dataGeracao' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        $nome = 'historico-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($nome);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Helper: gera a análise IA para o PDF.
     */
    private function buildAnaliseIA($eu, SicamIAService $ia): ?array
    {
        $desde = Carbon::now()->subDays(30);

        $consultas = ConsultaAgente::where('agente_id', $eu->id)
            ->where('consultado_em', '>=', $desde)
            ->orderByDesc('consultado_em')
            ->limit(200)
            ->get();

        if ($consultas->isEmpty()) {
            return [
                'resumo'           => 'Sem consultas nos últimos 30 dias.',
                'alertas'          => [],
                'recomendacoes'    => [],
                'padrao_dominante' => '',
                'nivel_atividade'  => 'baixo',
                'modelo_usado'     => null,
            ];
        }

        $porEstado = [
            'critico'   => $consultas->where('estado', 'critico')->count(),
            'atencao'   => $consultas->where('estado', 'atencao')->count(),
            'regular'   => $consultas->where('estado', 'regular')->count(),
            'sem-dados' => $consultas->where('estado', 'sem-dados')->count(),
        ];

        $matriculasRepetidas = $consultas
            ->groupBy('matricula')
            ->map(fn ($g) => [
                'matricula' => $g->first()->matricula,
                'vezes'     => $g->count(),
                'ultima'    => $g->first()->consultado_em?->toIso8601String(),
            ])
            ->filter(fn ($m) => $m['vezes'] > 1)
            ->sortByDesc('vezes')
            ->values()
            ->take(5)
            ->toArray();

        $porHora = $consultas
            ->groupBy(fn ($c) => $c->consultado_em?->format('H') ?? '—')
            ->map(fn ($g, $h) => ['hora' => $h, 'total' => $g->count()])
            ->sortByDesc('total')
            ->values()
            ->take(3)
            ->toArray();

        return $ia->analisarHistorico([
            'agente_nome'           => $eu->name,
            'posto'                 => $eu->posto ?? '—',
            'total_consultas'       => $consultas->count(),
            'por_estado'            => $porEstado,
            'matriculas_repetidas'  => $matriculasRepetidas,
            'horas_de_pico'         => $porHora,
            'matriculas_em_hotlist' => [],
            'periodo_dias'          => 30,
        ]);
    }

    private function formatar(ConsultaAgente $c): array
    {
        return [
            'id'           => (string) $c->id,
            'matricula'    => $c->matricula,
            'posto'        => $c->posto ?? '—',
            'score'        => $c->score,
            'estado'       => $c->estado,
            'origem'       => $c->origem,
            'consultadoEm' => $c->consultado_em?->toIso8601String(),
            'hora'         => $c->consultado_em?->format('H:i') ?? '—',
            'data'         => $c->consultado_em?->format('d/m/Y') ?? '—',
            'dataHora'     => $c->consultado_em?->format('d/m/Y H:i') ?? '—',
            'diasAtras'    => $c->consultado_em ? $c->consultado_em->diffInDays(now()) : 999,
        ];
    }

    private function estadoDoScore(?int $score): string
    {
        if ($score === null) return 'sem-dados';
        if ($score < 30) return 'critico';
        if ($score < 60) return 'atencao';
        return 'regular';
    }

    private function kpis(int $agenteId): array
    {
        $row = ConsultaAgente::where('agente_id', $agenteId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN consultado_em >= ? THEN 1 ELSE 0 END) as hoje,
                SUM(CASE WHEN estado = 'critico' THEN 1 ELSE 0 END) as criticos,
                SUM(CASE WHEN estado = 'atencao' THEN 1 ELSE 0 END) as atencao,
                SUM(CASE WHEN estado = 'regular' THEN 1 ELSE 0 END) as regulares
            ", [Carbon::today()])
            ->first();

        return [
            'total'     => (int) $row->total,
            'hoje'      => (int) $row->hoje,
            'criticos'  => (int) $row->criticos,
            'atencao'   => (int) $row->atencao,
            'regulares' => (int) $row->regulares,
        ];
    }
}