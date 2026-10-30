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

        return response()->json([
            'periodo' => [
                'de'        => $de->format('Y-m-d'),
                'ate'       => $ate->format('Y-m-d'),
                'provincia' => $provincia ?? 'Todas',
            ],
            'kpis'                 => $this->kpis($de, $ate, $provincia),
            'cobrancaMensal'       => $this->cobrancaMensal(),
            'topInfracoes'         => $this->topInfracoes($de, $ate, $provincia),
            'topMetodos'           => $this->topMetodos($de, $ate),
            'desempenhoProvincia'  => $this->desempenhoProvincia($de, $ate),
            'totaisProvincias'     => $this->totaisProvincias($de, $ate),
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

    private function baseMultas(Carbon $de, Carbon $ate, ?string $provincia = null)
    {
        $q = Multa::query()->whereBetween('data', [$de->toDateString(), $ate->toDateString()]);
        if ($provincia) $q->where('provincia', $provincia);
        return $q;
    }

    private function baseRecibos(Carbon $de, Carbon $ate)
    {
        return Recibo::query()->whereBetween('created_at', [$de, $ate]);
    }

    private function kpis(Carbon $de, Carbon $ate, ?string $provincia): array
    {
        $multas = $this->baseMultas($de, $ate, $provincia);

        $emitidas     = (clone $multas)->count();
        $pagas        = (clone $multas)->where('estado', 'Paga')->count();
        $pendentes    = (clone $multas)->where('estado', 'Pendente')->count();
        $contestadas  = (clone $multas)->where('estado', 'Contestada')->count();

        $pendenteValor = (float) (clone $multas)->where('estado', 'Pendente')->sum('valor');

        // Cobrado — soma dos recibos confirmados
        $recibos = $this->baseRecibos($de, $ate);

        if ($provincia) {
            $recibos->whereHas('multa', fn ($m) => $m->where('provincia', $provincia));
        }

        $cobrado = (float) (clone $recibos)->where('estado', 'Confirmado')->sum('valor');
        $totalRecibos = (clone $recibos)->count();

        $ticketMedio = $totalRecibos > 0
            ? round((float) (clone $recibos)->sum('valor') / $totalRecibos)
            : 0;

        $taxaCobranca = $emitidas > 0
            ? round(($pagas / $emitidas) * 100, 1)
            : 0;

        return [
            'emitidas'      => $emitidas,
            'pagas'         => $pagas,
            'pendentes'     => $pendentes,
            'contestadas'   => $contestadas,
            'cobrado'       => $cobrado,
            'pendenteValor' => $pendenteValor,
            'ticketMedio'   => $ticketMedio,
            'taxaCobranca'  => $taxaCobranca,
        ];
    }

    private function cobrancaMensal(): array
    {
        $resultado = [];

        for ($i = 5; $i >= 0; $i--) {
            $data = Carbon::today()->subMonths($i);
            $ini  = $data->copy()->startOfMonth();
            $fim  = $data->copy()->endOfMonth();

            $emitidas = Multa::whereBetween('data', [
                $ini->toDateString(),
                $fim->toDateString(),
            ])->count();

            $cobrado = (float) Recibo::whereBetween('created_at', [$ini, $fim])
                ->where('estado', 'Confirmado')
                ->sum('valor');

            $resultado[] = [
                'mes'      => mb_strtoupper($data->locale('pt')->isoFormat('MMM')),
                'emitidas' => $emitidas,
                'cobrado'  => round($cobrado / 1000, 1), // mil MT
            ];
        }

        return $resultado;
    }

    private function topInfracoes(Carbon $de, Carbon $ate, ?string $provincia): array
    {
        $query = Multa::query()
            ->select('infracao', DB::raw('COUNT(*) as total'))
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->groupBy('infracao')
            ->orderByDesc('total');

        if ($provincia) $query->where('provincia', $provincia);

        $itens = $query->limit(6)->get();
        $soma  = $itens->sum('total') ?: 1;

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
                DB::raw('SUM(CASE WHEN estado = "Paga" THEN 1 ELSE 0 END) as pagas'),
                DB::raw('SUM(CASE WHEN estado = "Paga" THEN valor ELSE 0 END) as cobrado')
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

    private function totaisProvincias(Carbon $de, Carbon $ate): array
    {
        $linhas = $this->desempenhoProvincia($de, $ate);

        return [
            'emitidas' => array_sum(array_column($linhas, 'emitidas')),
            'pagas'    => array_sum(array_column($linhas, 'pagas')),
            'cobrado'  => array_sum(array_column($linhas, 'cobrado')),
        ];
    }
}