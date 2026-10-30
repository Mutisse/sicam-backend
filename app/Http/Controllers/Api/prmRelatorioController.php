<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Abordagem;
use App\Models\Hotlist;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RelatorioPrmController extends Controller
{
    /**
     * GET /api/relatorios/prm?de=2026-09-01&ate=2026-09-30
     */
    public function prm(Request $request)
    {
        $de  = $request->filled('de')  ? Carbon::parse($request->de)->startOfDay()  : null;
        $ate = $request->filled('ate') ? Carbon::parse($request->ate)->endOfDay()    : null;

        // ============================================================
        // KPIs base
        // ============================================================
        $qAbordagens = Abordagem::query();
        if ($de)  $qAbordagens->where('data', '>=', $de);
        if ($ate) $qAbordagens->where('data', '<=', $ate);

        $totalAbordagens = (clone $qAbordagens)->count();
        $totalVeiculos   = Veiculo::count();
        $totalHotlist    = Hotlist::count();

        $hotlistActiva  = Hotlist::where('estado', 'activa')->count();
        $hotlistResolv  = Hotlist::where('estado', 'resolvida')->count();

        // Abordagens nos últimos 30 dias
        $abordagens30d = Abordagem::where('data', '>=', now()->subDays(30))->count();

        // Detenções = abordagens com resultado "detido" (ou similar)
        $detencoes = (clone $qAbordagens)
            ->where('resultado', 'like', '%deti%')
            ->count();

        // Resolvidos / em aberto (na hotlist)
        $resolvidos = $hotlistResolv;
        $emAberto   = $hotlistActiva;

        $taxaResolucao = $totalHotlist > 0
            ? round(($resolvidos / $totalHotlist) * 100, 1)
            : 0;

        // ============================================================
        // Série por mês (últimos 6 meses) — abordagens
        // ============================================================
        $inicio = now()->subMonths(5)->startOfMonth();
        $fim    = now()->endOfMonth();

        $registos = Abordagem::query()
            ->whereBetween('data', [$inicio, $fim])
            ->selectRaw("DATE_FORMAT(data, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $linhaValores = [];
        $linhaLabels  = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fim)) {
            $ym = $cursor->format('Y-m');
            $linhaValores[] = (int) ($registos[$ym] ?? 0);
            $linhaLabels[]  = $cursor->translatedFormat('M');
            $cursor->addMonth();
        }

        // ============================================================
        // Hotlist por motivo (coluna real = motivo)
        // ============================================================
        $coresMotivos = ['#dc2626', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'];

        $hotlistPorMotivo = Hotlist::query()
            ->select('motivo')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('motivo')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->values()
            ->map(function ($r, $i) use ($totalHotlist, $coresMotivos) {
                return [
                    'nome'        => $r->motivo ?? 'Outro',
                    'valor'       => (int) $r->total,
                    'cor'         => $coresMotivos[$i % count($coresMotivos)],
                    'percentagem' => $totalHotlist > 0
                        ? round(($r->total / $totalHotlist) * 100, 1)
                        : 0,
                ];
            })
            ->toArray();

        // ============================================================
        // Desempenho por posto (abordagens)
        // ============================================================
        $desempenhoPorPosto = (clone $qAbordagens)
            ->select('posto_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN resultado LIKE '%deti%' THEN 1 ELSE 0 END) as detencoes")
            ->selectRaw("SUM(CASE WHEN resultado LIKE '%auto%' OR resultado LIKE '%multa%' THEN 1 ELSE 0 END) as autos")
            ->groupBy('posto_id')
            ->with('posto:id,nome,provincia')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($r) {
                $taxa = $r->total > 0 ? round(($r->detencoes / $r->total) * 100, 1) : 0;

                return [
                    'posto'      => $r->posto?->nome ?? '—',
                    'provincia'  => $r->posto?->provincia ?? '—',
                    'abordagens' => (int) $r->total,
                    'detencoes'  => (int) $r->detencoes,
                    'autos'      => (int) $r->autos,
                    'taxa'       => $taxa,
                ];
            })
            ->toArray();

        // ============================================================
        // Top matrículas abordadas
        // ============================================================
        $topMatriculas = Abordagem::query()
            ->when($de, fn ($q) => $q->where('data', '>=', $de))
            ->when($ate, fn ($q) => $q->where('data', '<=', $ate))
            ->select('veiculo_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('MAX(resultado) as ultimo_resultado')
            ->groupBy('veiculo_id')
            ->with('veiculo:id,matricula')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($r) {
                $ab = (int) $r->total;
                $nivel = $ab >= 4 ? 'alto' : ($ab >= 2 ? 'medio' : 'baixo');

                return [
                    'matricula'  => $r->veiculo?->matricula ?? '—',
                    'motivo'     => $r->ultimo_resultado ?? 'Abordagem',
                    'abordagens' => $ab,
                    'nivel'      => $nivel,
                ];
            })
            ->toArray();

        // ============================================================
        // KPIs visíveis (com formato para cartões)
        // ============================================================
        $kpisVisiveis = [
            [
                'variant' => 'critico',
                'icon'    => 'local_police',
                'valor'   => $hotlistActiva,
                'label'   => 'Hotlist activa',
                'sub'     => 'veículos procurados',
            ],
            [
                'variant' => 'info',
                'icon'    => 'fact_check',
                'valor'   => $abordagens30d,
                'label'   => 'Abordagens (30d)',
                'sub'     => 'últimos 30 dias',
            ],
            [
                'variant' => 'atencao',
                'icon'    => 'gavel',
                'valor'   => $detencoes,
                'label'   => 'Detenções',
                'sub'     => 'no período',
            ],
            [
                'variant' => 'regular',
                'icon'    => 'check_circle',
                'valor'   => $resolvidos,
                'label'   => 'Resolvidos',
                'sub'     => 'hotlist fechada',
            ],
            [
                'variant' => 'neutral',
                'icon'    => 'hourglass_empty',
                'valor'   => $emAberto,
                'label'   => 'Em aberto',
                'sub'     => 'a acompanhar',
            ],
        ];

        return response()->json([
            'kpis' => [
                'hotlistActiva' => $hotlistActiva,
                'abordagens30d' => $abordagens30d,
                'detencoes'     => $detencoes,
                'resolvidos'    => $resolvidos,
                'emAberto'      => $emAberto,
                'taxaResolucao' => $taxaResolucao,
                'tempoMedio'    => 0,
            ],
            'kpisVisiveis' => $kpisVisiveis,
            'linha' => [
                'valores' => $linhaValores,
                'labels'  => $linhaLabels,
            ],
            'anel' => [
                'taxa'       => $taxaResolucao,
                'resolvidos' => $resolvidos,
                'emAberto'   => $emAberto,
            ],
            'hotlistPorMotivo'   => $hotlistPorMotivo,
            'desempenhoPorPosto' => $desempenhoPorPosto,
            'topMatriculas'      => $topMatriculas,
        ]);
    }
}