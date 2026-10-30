<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Apolice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'kpis'                   => $this->kpis($de, $ate),
            'apolicesPorMes'         => $this->apolicesPorMes(),
            'apolicesPorSeguradora'  => $this->apolicesPorSeguradora(),
            'desempenhoPorSeguradora'=> $this->desempenhoPorSeguradora($de, $ate),
            'topTipos'               => $this->topTipos(),
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

    private function kpis(Carbon $de, Carbon $ate): array
    {
        $hoje = Carbon::today();

        // Apólices com validade futura
        $apolicesActivas = Apolice::query()
            ->where('validade', '>=', $hoje)
            ->count();

        // Sinistros em aberto
        $sinistrosAbertos = Schema::hasTable('sinistros')
            ? DB::table('sinistros')
                ->whereNotIn('estado', ['Resolvido', 'Fechado', 'Pago', 'Arquivado'])
                ->count()
            : 0;

        // A expirar nos próximos 30 dias
        $aExpirar = Apolice::query()
            ->whereBetween('validade', [$hoje, $hoje->copy()->addDays(30)])
            ->count();

        // Taxa de cobertura (apólices activas / total de veículos)
        $totalVeiculos = Schema::hasTable('veiculos')
            ? DB::table('veiculos')->count()
            : 0;

        $taxaCobertura = $totalVeiculos > 0
            ? round(($apolicesActivas / $totalVeiculos) * 100, 1)
            : 0;

        // Prémio médio (coluna correta: premio)
        $premioMedio = (int) round(Apolice::avg('premio') ?? 0);

        // Taxa de sinistralidade
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

    private function apolicesPorMes(): array
    {
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $data = Carbon::today()->subMonths($i);
            $ini  = $data->copy()->startOfMonth();
            $fim  = $data->copy()->endOfMonth();

            $count = Apolice::query()
                ->whereBetween('created_at', [$ini, $fim])
                ->count();

            $meses[] = [
                'mes'      => mb_strtoupper($data->locale('pt')->isoFormat('MMM')),
                'apolices' => $count,
            ];
        }
        return $meses;
    }

    private function apolicesPorSeguradora(): array
    {
        $dados = Apolice::query()
            ->select('seguradora', DB::raw('COUNT(*) as total'))
            ->groupBy('seguradora')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        if ($dados->isEmpty()) return [];

        $max = $dados->max('total') ?: 1;

        return $dados->map(fn ($d) => [
            'nome'        => $d->seguradora,
            'valor'       => (int) $d->total,
            'percentagem' => round(($d->total / $max) * 100, 1),
        ])->toArray();
    }

    private function desempenhoPorSeguradora(Carbon $de, Carbon $ate): array
    {
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

        return $seguradoras->map(function ($s) {
            $sinistros = Schema::hasTable('sinistros')
                ? DB::table('sinistros')
                    ->where('seguradora', $s->seguradora)
                    ->whereNotIn('estado', ['Resolvido', 'Fechado', 'Pago', 'Arquivado'])
                    ->count()
                : 0;

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

    private function topTipos(): array
    {
        $tipos = Apolice::query()
            ->select('tipo', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tipo')
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