<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Apolice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class IssmRelatorioController extends Controller
{
    /**
     * GET /api/issm/relatorios
     */
    public function index(Request $request)
    {
        $de  = $request->filled('de')
            ? Carbon::parse($request->de)->startOfDay()
            : Carbon::today()->subDays(30);

        $ate = $request->filled('ate')
            ? Carbon::parse($request->ate)->endOfDay()
            : Carbon::today();

        return response()->json([
            'periodo' => [
                'de'  => $de->format('Y-m-d'),
                'ate' => $ate->format('Y-m-d'),
            ],
            'kpis'                    => $this->kpis(),
            'apolicesPorMes'          => $this->apolicesPorMes(),
            'apolicesPorSeguradora'   => $this->apolicesPorSeguradora($de, $ate),
            'desempenhoPorSeguradora' => $this->desempenhoPorSeguradora($de, $ate),
            'topTipos'                => $this->topTipos($de, $ate),
        ]);
    }

    /**
     * GET /api/issm/relatorios/xlsx
     */
    public function xlsx(Request $request)
    {
        $dados = $this->index($request)->getData(true);

        $cabecalhos = ['Seguradora', 'Apólices', 'Sinistros', 'Taxa (%)', 'Valor médio', 'Estado'];
        $linhas = collect($dados['desempenhoPorSeguradora'])->map(fn ($d) => [
            $d['nome'],
            (string) $d['apolices'],
            (string) $d['sinistros'],
            (string) $d['taxa'],
            $d['valorMedio'],
            $d['estadoLabel'],
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Relatórios'),
            'issm-relatorios-' . date('Y-m-d') . '.xlsx',
        );
    }

    /**
     * GET /api/issm/relatorios/pdf
     */
    public function pdf(Request $request)
    {
        $dados = $this->index($request)->getData(true);

        $cabecalhos = ['Seguradora', 'Apólices', 'Sinistros', 'Taxa', 'Valor médio', 'Estado'];
        $linhas = collect($dados['desempenhoPorSeguradora'])->map(fn ($d) => [
            $d['nome'],
            (string) $d['apolices'],
            (string) $d['sinistros'],
            $d['taxa'] . '%',
            $d['valorMedio'],
            $d['estadoLabel'],
        ])->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Instituto de Supervisão de Seguros de Moçambique',
            'sigla'       => 'ISSM',
            'rodape'      => 'SICAM — ISSM · Relatórios',
            'titulo'      => 'Relatório Anual',
            'subtitulo'   => "Período: {$dados['periodo']['de']} a {$dados['periodo']['ate']}",
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Apólices activas'  => $dados['kpis']['apolicesActivas'],
                'Sinistros abertos' => $dados['kpis']['sinistrosAbertos'],
                'Taxa cobertura'    => $dados['kpis']['taxaCobertura'] . '%',
                'A expirar (30d)'   => $dados['kpis']['aExpirar'],
                'Prémio médio'      => $dados['kpis']['premioMedio'] . ' MT',
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('issm-relatorios-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * KPIs — 3 queries (agregado de apólices + sinistros + veículos).
     */
    private function kpis(): array
    {
        $hoje   = Carbon::today();
        $fim30d = $hoje->copy()->addDays(30);

        // ── 1 query — agregado de apólices ──
        $a = Apolice::selectRaw("
            SUM(CASE WHEN validade >= ? THEN 1 ELSE 0 END) as activas,
            SUM(CASE WHEN validade BETWEEN ? AND ? THEN 1 ELSE 0 END) as a_expirar,
            AVG(premio) as premio_medio
        ", [$hoje, $hoje, $fim30d])->first();

        $apolicesActivas = (int) $a->activas;
        $aExpirar        = (int) $a->a_expirar;
        $premioMedio     = (int) round($a->premio_medio ?? 0);

        // ── 1 query — sinistros em aberto ──
        $sinistrosAbertos = DB::table('sinistros')
            ->whereNotIn('estado', ['Resolvido', 'Fechado', 'Pago', 'Arquivado'])
            ->count();

        // ── 1 query — total de veículos ──
        $totalVeiculos = DB::table('veiculos')->count();

        $taxaCobertura = $totalVeiculos > 0
            ? round(($apolicesActivas / $totalVeiculos) * 100, 1)
            : 0;

        $taxaSinistralidade = $apolicesActivas > 0
            ? round(($sinistrosAbertos / $apolicesActivas) * 100, 1)
            : 0;

        return [
            'apolicesActivas'    => $apolicesActivas,
            'sinistrosAbertos'   => $sinistrosAbertos,
            'taxaCobertura'      => $taxaCobertura,
            'aExpirar'           => $aExpirar,
            'premioMedio'        => $premioMedio,
            'taxaSinistralidade' => $taxaSinistralidade,
        ];
    }

    /**
     * Apólices por mês — 1 query GROUP BY mês.
     */
    private function apolicesPorMes(): array
    {
        $inicio = Carbon::today()->subMonths(5)->startOfMonth();
        $fim    = Carbon::today()->endOfMonth();

        $porMes = Apolice::query()
            ->whereBetween('created_at', [$inicio, $fim])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $meses = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fim)) {
            $ym = $cursor->format('Y-m');
            $meses[] = [
                'mes'      => mb_strtoupper($cursor->locale('pt')->isoFormat('MMM')),
                'apolices' => (int) ($porMes[$ym] ?? 0),
            ];
            $cursor->addMonth();
        }
        return $meses;
    }

    /**
     * Apólices por seguradora — 1 query, percentagem relativa ao total.
     */
    private function apolicesPorSeguradora(Carbon $de, Carbon $ate): array
    {
        $dados = Apolice::query()
            ->select('seguradora', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$de, $ate])
            ->groupBy('seguradora')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        if ($dados->isEmpty()) return [];

        $soma = $dados->sum('total') ?: 1;

        return $dados->map(fn ($d) => [
            'nome'        => $d->seguradora,
            'valor'       => (int) $d->total,
            'percentagem' => round(($d->total / $soma) * 100, 1),
        ])->toArray();
    }

    /**
     * Desempenho por seguradora — 2 queries fixas (apólices + sinistros agregados).
     */
    private function desempenhoPorSeguradora(Carbon $de, Carbon $ate): array
    {
        // ── 1 query — apólices por seguradora no período ──
        $seguradoras = Apolice::query()
            ->select(
                'seguradora',
                DB::raw('COUNT(*) as apolices'),
                DB::raw('AVG(premio) as premio_medio')
            )
            ->whereBetween('created_at', [$de, $ate])
            ->groupBy('seguradora')
            ->orderByDesc('apolices')
            ->limit(10)
            ->get();

        if ($seguradoras->isEmpty()) return [];

        // ── 1 query — sinistros por seguradora no período ──
        $nomes = $seguradoras->pluck('seguradora')->all();

        $sinistrosPorSeg = DB::table('sinistros')
            ->select('seguradora', DB::raw('COUNT(*) as total'))
            ->whereIn('seguradora', $nomes)
            ->whereNotIn('estado', ['Resolvido', 'Fechado', 'Pago', 'Arquivado'])
            ->whereBetween('created_at', [$de, $ate])
            ->groupBy('seguradora')
            ->pluck('total', 'seguradora');

        return $seguradoras->map(function ($s) use ($sinistrosPorSeg) {
            $sinistros = (int) ($sinistrosPorSeg[$s->seguradora] ?? 0);

            $taxa = $s->apolices > 0
                ? round(($sinistros / $s->apolices) * 100, 1)
                : 0;

            $estado = 'regular';
            $estadoLabel = 'Bom';
            if ($taxa >= 8)      { $estado = 'critico'; $estadoLabel = 'Crítico'; }
            else if ($taxa >= 6) { $estado = 'atencao'; $estadoLabel = 'Atenção'; }

            return [
                'nome'        => $s->seguradora,
                'apolices'    => (int) $s->apolices,
                'sinistros'   => $sinistros,
                'taxa'        => $taxa,
                'valorMedio'  => number_format((int) $s->premio_medio, 0, ',', "\u{00a0}") . ' MT',
                'estado'      => $estado,
                'estadoLabel' => $estadoLabel,
            ];
        })->toArray();
    }

    /**
     * Top tipos de apólice — 1 query, percentagem relativa ao total.
     */
    private function topTipos(Carbon $de, Carbon $ate): array
    {
        $tipos = Apolice::query()
            ->select('tipo', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tipo')
            ->whereBetween('created_at', [$de, $ate])
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $soma = $tipos->sum('total') ?: 1;

        return $tipos->map(fn ($t) => [
            'tipo'        => $t->tipo ?? '—',
            'apolices'    => (int) $t->total,
            'percentagem' => round(($t->total / $soma) * 100, 1),
        ])->toArray();
    }
}