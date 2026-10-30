<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AdminRelatoriosOperacoesController extends Controller
{
    /**
     * GET /api/admin/relatorios/operacoes
     */
    public function index(Request $request)
    {
        $de = $request->filled('de')
            ? Carbon::parse($request->de)->startOfDay()
            : null;

        $ate = $request->filled('ate')
            ? Carbon::parse($request->ate)->endOfDay()
            : null;

        $filtroPosto = $request->input('posto', '');

        return response()->json([
            'periodo' => [
                'de'  => $de?->format('Y-m-d'),
                'ate' => $ate?->format('Y-m-d'),
            ],
            'kpis'       => $this->kpis($de, $ate, $filtroPosto),
            'serieDias'  => $this->serieDias($de, $ate, $filtroPosto),
            'porPosto'   => $this->operacoesPorPosto($de, $ate, $filtroPosto),
            'distEstado' => $this->distribuicaoEstado($de, $ate, $filtroPosto),
            'operacoes'  => $this->operacoes($de, $ate, $filtroPosto),
            'viaturas'   => $this->viaturas($de, $ate, $filtroPosto),
            'postos'     => $this->postos(),
        ]);
    }

    public function xlsx(Request $request)
    {
        $dados = $this->index($request)->getData(true);

        $cabecalhos = ['Código', 'Turno', 'Viatura', 'Posto', 'Início', 'Estado'];
        $linhas = collect($dados['operacoes'])->map(fn ($o) => [
            $o['codigo'],
            $o['tipo'],
            $o['viatura'],
            $o['posto'],
            $o['agendada'],
            $o['estado'],
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Operações'),
            'admin-operacoes-' . date('Y-m-d') . '.xlsx',
        );
    }

    public function pdf(Request $request)
    {
        $dados = $this->index($request)->getData(true);

        $cabecalhos = ['Código', 'Turno', 'Viatura', 'Posto', 'Início', 'Estado'];
        $linhas = collect($dados['operacoes'])->map(fn ($o) => [
            $o['codigo'],
            $o['tipo'],
            $o['viatura'],
            $o['posto'],
            $o['agendada'],
            $o['estado'],
        ])->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Sistema Integrado de Controlo Automóvel de Moçambique',
            'sigla'       => 'ADMIN',
            'rodape'      => 'SICAM — ADMIN · Relatório de Operações',
            'titulo'      => 'Relatório de Operações',
            'subtitulo'   => "Período: {$dados['periodo']['de']} a {$dados['periodo']['ate']}",
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Operações'      => $dados['kpis']['operacoes'],
                'Concluídas'     => $dados['kpis']['concluidas'],
                'Canceladas'     => $dados['kpis']['canceladas'],
                'Taxa conclusão' => $dados['kpis']['taxa'] . '%',
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('admin-operacoes-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function base($de = null, $ate = null, string $posto = '')
    {
        $q = DB::table('patrulhas');

        if ($de && $ate) $q->whereBetween('inicio', [$de, $ate]);
        elseif ($de)     $q->where('inicio', '>=', $de);
        elseif ($ate)    $q->where('inicio', '<=', $ate);

        if ($posto) {
            $postoId = DB::table('postos')->where('nome', $posto)->value('id');
            if ($postoId) $q->where('posto_id', $postoId);
        }

        return $q;
    }

    private function kpis($de, $ate, string $posto): array
    {
        $total      = (clone $this->base($de, $ate, $posto))->count();
        $concluidas = (clone $this->base($de, $ate, $posto))
            ->where('estado', 'Concluída')->count();
        $canceladas = (clone $this->base($de, $ate, $posto))
            ->where('estado', 'Cancelada')->count();
        $taxa       = $total > 0 ? round(($concluidas / $total) * 100, 1) : 0;

        return [
            'operacoes'  => $total,
            'concluidas' => $concluidas,
            'canceladas' => $canceladas,
            'taxa'       => $taxa,
        ];
    }

    private function serieDias($de, $ate, string $posto): array
    {
        $fim = $ate ? $ate->copy() : Carbon::today();
        $ini = $de ? $de->copy() : Carbon::today()->subDays(6);

        $raw = (clone $this->base($de, $ate, $posto))
            ->select(DB::raw('DATE(inicio) as dia'), DB::raw('COUNT(*) as total'))
            ->groupBy('dia')
            ->pluck('total', 'dia')
            ->toArray();

        $dias = [];
        $cursor = $ini->copy();
        while ($cursor <= $fim) {
            $iso = $cursor->format('Y-m-d');
            $dias[] = [
                'data'  => $iso,
                'label' => $cursor->format('d'),
                'valor' => (int) ($raw[$iso] ?? 0),
            ];
            $cursor->addDay();
        }
        return $dias;
    }

    private function operacoesPorPosto($de, $ate, string $posto): array
    {
        $rows = (clone $this->base($de, $ate, $posto))
            ->leftJoin('postos', 'postos.id', '=', 'patrulhas.posto_id')
            ->select('postos.nome as nome', DB::raw('COUNT(*) as total'))
            ->groupBy('postos.nome')
            ->orderByDesc('total')
            ->get();

        $max = $rows->max('total') ?: 1;
        return $rows->map(fn ($r) => [
            'nome'        => $r->nome ?? '—',
            'valor'       => (int) $r->total,
            'percentagem' => round(($r->total / $max) * 100, 1),
        ])->toArray();
    }

    private function distribuicaoEstado($de, $ate, string $posto): array
    {
        $rows = (clone $this->base($de, $ate, $posto))
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->get();

        $total = $rows->sum('total') ?: 1;
        $ordem = ['Concluída', 'Em curso', 'Planeada', 'Cancelada'];

        return collect($ordem)->map(function ($estado) use ($rows, $total) {
            $valor = $rows->firstWhere('estado', $estado)?->total ?? 0;
            return [
                'estado'  => $estado,
                'valor'   => (int) $valor,
                'percent' => round(($valor / $total) * 100, 2),
            ];
        })->toArray();
    }

    private function operacoes($de, $ate, string $posto): array
    {
        return (clone $this->base($de, $ate, $posto))
            ->leftJoin('postos', 'postos.id', '=', 'patrulhas.posto_id')
            ->select(
                'patrulhas.codigo',
                'patrulhas.turno',
                'patrulhas.viatura',
                'patrulhas.estado',
                'patrulhas.inicio',
                'postos.nome as posto_nome',
            )
            ->orderByDesc('patrulhas.inicio')
            ->limit(500)
            ->get()
            ->map(function ($p) {
                $inicio = $p->inicio ? Carbon::parse($p->inicio) : null;
                return [
                    'codigo'   => $p->codigo,
                    'tipo'     => $p->turno ?? '—',
                    'viatura'  => $p->viatura ?: '—',
                    'posto'    => $p->posto_nome ?? '—',
                    'agendada' => $inicio?->format('d/m H:i') ?? '—',
                    'data'     => $inicio?->format('Y-m-d') ?? '',
                    'estado'   => $p->estado ?? 'Planeada',
                ];
            })
            ->toArray();
    }

    private function viaturas($de, $ate, string $posto): array
    {
        $rows = (clone $this->base($de, $ate, $posto))
            ->leftJoin('postos', 'postos.id', '=', 'patrulhas.posto_id')
            ->select(
                'patrulhas.viatura',
                'postos.nome as posto_nome',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN patrulhas.estado = "Concluída" THEN 1 ELSE 0 END) as concluidas')
            )
            ->whereNotNull('patrulhas.viatura')
            ->where('patrulhas.viatura', '!=', '')
            ->groupBy('patrulhas.viatura', 'postos.nome')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r) => [
            'viatura'             => $r->viatura,
            'posto'               => $r->posto_nome ?? '—',
            'operacoesTotal'      => (int) $r->total,
            'operacoesConcluidas' => (int) $r->concluidas,
        ])->toArray();
    }

    private function postos(): array
    {
        return DB::table('postos')->orderBy('nome')->pluck('nome')->toArray();
    }
}