<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Multa;
use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class GestmulRelatorioController extends Controller
{
    /**
     * GET /api/relatorios/gestmul
     * Query: de, ate, provincia
     */
    public function index(Request $request)
    {
        $de  = $request->filled('de')
            ? Carbon::parse($request->de)->startOfDay()
            : Carbon::today()->subDays(30);

        $ate = $request->filled('ate')
            ? Carbon::parse($request->ate)->endOfDay()
            : Carbon::today();

        $provincia = $request->filled('provincia') && $request->provincia !== 'Todas'
            ? $request->provincia
            : null;

        $desempenho = $this->desempenhoProvincia($de, $ate);

        return response()->json([
            'periodo' => [
                'de'        => $de->format('Y-m-d'),
                'ate'       => $ate->format('Y-m-d'),
                'provincia' => $provincia ?? 'Todas',
            ],
            'kpis'                => $this->kpis($de, $ate, $provincia),
            'cobrancaMensal'      => $this->cobrancaMensal(),
            'topInfracoes'        => $this->topInfracoes($de, $ate, $provincia),
            'topMetodos'          => $this->topMetodos($de, $ate),
            'desempenhoProvincia' => $desempenho,
            'totaisProvincias'    => [
                'emitidas' => array_sum(array_column($desempenho, 'emitidas')),
                'pagas'    => array_sum(array_column($desempenho, 'pagas')),
                'cobrado'  => array_sum(array_column($desempenho, 'cobrado')),
            ],
        ]);
    }

    /**
     * GET /api/relatorios/gestmul/xlsx
     */
    public function xlsx(Request $request)
    {
        $dados = $this->index($request)->getData(true);

        $cabecalhos = ['Província', 'Emitidas', 'Pagas', 'Cobrado (MT)', 'Taxa (%)'];
        $linhas = collect($dados['desempenhoProvincia'])->map(fn ($p) => [
            $p['provincia'],
            (string) $p['emitidas'],
            (string) $p['pagas'],
            (string) $p['cobrado'],
            (string) $p['taxa'],
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Relatório'),
            'gestmul-relatorio-' . date('Y-m-d') . '.xlsx',
        );
    }

    /**
     * GET /api/relatorios/gestmul/pdf
     */
    public function pdf(Request $request)
    {
        $dados = $this->index($request)->getData(true);

        $cabecalhos = ['Província', 'Emitidas', 'Pagas', 'Cobrado (MT)', 'Taxa'];
        $linhas = collect($dados['desempenhoProvincia'])->map(fn ($p) => [
            $p['provincia'],
            (string) $p['emitidas'],
            (string) $p['pagas'],
            number_format($p['cobrado'], 0, ',', '.'),
            $p['taxa'] . '%',
        ])->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — GESTMUL — Sistema de Gestão de Multas',
            'sigla'       => 'GESTMUL',
            'rodape'      => 'SICAM — GESTMUL · Relatório',
            'titulo'      => 'Relatório de Cobrança',
            'subtitulo'   => "Período: {$dados['periodo']['de']} a {$dados['periodo']['ate']} · Província: {$dados['periodo']['provincia']}",
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Multas emitidas'  => $dados['kpis']['emitidas'],
                'Pagas'            => $dados['kpis']['pagas'],
                'Pendentes'        => $dados['kpis']['pendentes'],
                'Contestadas'      => $dados['kpis']['contestadas'],
                'Cobrado (MT)'     => number_format($dados['kpis']['cobrado'], 0, ',', '.'),
                'Taxa de cobrança' => $dados['kpis']['taxaCobranca'] . '%',
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('gestmul-relatorio-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function kpis(Carbon $de, Carbon $ate, ?string $provincia): array
    {
        // ── 1 query — agregados de multas ──
        $m = Multa::query()
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->when($provincia, fn ($q) => $q->where('provincia', $provincia))
            ->selectRaw("
                COUNT(*) as emitidas,
                SUM(CASE WHEN estado = 'Paga'       THEN 1 ELSE 0 END) as pagas,
                SUM(CASE WHEN estado = 'Pendente'   THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN estado = 'Contestada' THEN 1 ELSE 0 END) as contestadas,
                SUM(CASE WHEN estado = 'Pendente'   THEN valor ELSE 0 END) as pendente_valor
            ")->first();

        // ── 1 query — agregados de recibos ──
        $r = Recibo::query()
            ->whereBetween('created_at', [$de, $ate])
            ->when($provincia, fn ($q) =>
                $q->whereHas('multa', fn ($mm) => $mm->where('provincia', $provincia))
            )
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'Confirmado' THEN valor ELSE 0 END) as cobrado,
                SUM(valor) as valor_total
            ")->first();

        $emitidas = (int) $m->emitidas;
        $pagas    = (int) $m->pagas;
        $totalR   = (int) $r->total;

        return [
            'emitidas'      => $emitidas,
            'pagas'         => $pagas,
            'pendentes'     => (int) $m->pendentes,
            'contestadas'   => (int) $m->contestadas,
            'cobrado'       => (float) $r->cobrado,
            'pendenteValor' => (float) $m->pendente_valor,
            'ticketMedio'   => $totalR > 0 ? round((float) $r->valor_total / $totalR) : 0,
            'taxaCobranca'  => $emitidas > 0 ? round(($pagas / $emitidas) * 100, 1) : 0,
        ];
    }

    /**
     * Cobrança mensal — 2 queries (GROUP BY mês), independente do nº de meses.
     */
    private function cobrancaMensal(): array
    {
        $inicio = Carbon::today()->subMonths(5)->startOfMonth();
        $fim    = Carbon::today()->endOfMonth();

        // 1 query — multas emitidas por mês
        $emitidasPorMes = Multa::query()
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->selectRaw("DATE_FORMAT(data, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // 1 query — cobrado por mês
        $cobradoPorMes = Recibo::query()
            ->whereBetween('created_at', [$inicio, $fim])
            ->where('estado', 'Confirmado')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(valor) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $resultado = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fim)) {
            $ym = $cursor->format('Y-m');
            $resultado[] = [
                'mes'      => mb_strtoupper($cursor->locale('pt')->isoFormat('MMM')),
                'emitidas' => (int) ($emitidasPorMes[$ym] ?? 0),
                'cobrado'  => round((float) ($cobradoPorMes[$ym] ?? 0) / 1000, 1),
            ];
            $cursor->addMonth();
        }

        return $resultado;
    }

    private function topInfracoes(Carbon $de, Carbon $ate, ?string $provincia): array
    {
        $itens = Multa::query()
            ->select('infracao', DB::raw('COUNT(*) as total'))
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->when($provincia, fn ($q) => $q->where('provincia', $provincia))
            ->groupBy('infracao')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $soma = $itens->sum('total') ?: 1;

        return $itens->map(fn ($i) => [
            'nome'        => $i->infracao,
            'total'       => (int) $i->total,
            'percentagem' => round(($i->total / $soma) * 100),
        ])->toArray();
    }

    private function topMetodos(Carbon $de, Carbon $ate): array
    {
        $itens = Recibo::query()
            ->select('metodo', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$de, $ate])
            ->where('estado', 'Confirmado')
            ->groupBy('metodo')
            ->orderByDesc('total')
            ->get();

        $soma = $itens->sum('total') ?: 1;

        return $itens->map(fn ($m) => [
            'metodo'      => $m->metodo,
            'total'       => (int) $m->total,
            'percentagem' => round(($m->total / $soma) * 100),
        ])->toArray();
    }

    private function desempenhoProvincia(Carbon $de, Carbon $ate): array
    {
        $linhas = Multa::query()
            ->select(
                'provincia',
                DB::raw('COUNT(*) as emitidas'),
                DB::raw("SUM(CASE WHEN estado = 'Paga' THEN 1 ELSE 0 END) as pagas"),
                DB::raw("SUM(CASE WHEN estado = 'Paga' THEN valor ELSE 0 END) as cobrado")
            )
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->groupBy('provincia')
            ->orderByDesc('emitidas')
            ->get();

        return $linhas->map(function ($l) {
            $taxa = $l->emitidas > 0
                ? round(($l->pagas / $l->emitidas) * 100)
                : 0;

            return [
                'provincia' => $l->provincia,
                'emitidas'  => (int) $l->emitidas,
                'pagas'     => (int) $l->pagas,
                'cobrado'   => (float) $l->cobrado,
                'taxa'      => $taxa,
            ];
        })->toArray();
    }
}