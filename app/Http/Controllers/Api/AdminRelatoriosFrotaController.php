<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AdminRelatoriosFrotaController extends Controller
{
    /**
     * GET /api/admin/relatorios/frota
     */
    public function index(Request $request)
    {
        $dados = $this->dados($request);

        return response()->json($dados);
    }

    /**
     * GET /api/admin/relatorios/frota/xlsx
     */
    public function xlsx(Request $request)
    {
        $dados = $this->dados($request);

        $cabecalhos = ['Viatura', 'Operações', 'GPS', 'Odómetro', 'Variação'];
        $linhas = collect($dados['viaturas'])->map(fn ($v) => [
            $v['viatura'],
            $v['operacoes'],
            $v['gps'],
            $v['odometro'],
            $v['variacao'],
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Frota'),
            'admin-frota-' . date('Y-m-d') . '.xlsx',
        );
    }

    /**
     * GET /api/admin/relatorios/frota/pdf
     */
    public function pdf(Request $request)
    {
        $dados = $this->dados($request);

        $cabecalhos = ['Viatura', 'Operações', 'GPS', 'Odómetro', 'Variação'];
        $linhas = collect($dados['viaturas'])->map(fn ($v) => [
            $v['viatura'],
            $v['operacoes'],
            $v['gps'],
            $v['odometro'],
            $v['variacao'],
        ])->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Sistema Integrado de Controlo Automóvel de Moçambique',
            'sigla'       => 'ADMIN',
            'rodape'      => 'SICAM — ADMIN · Relatório de Frota',
            'titulo'      => 'Relatório de Frota',
            'subtitulo'   => "Período: {$dados['periodo']['de']} a {$dados['periodo']['ate']}",
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Total de operações' => $dados['kpis']['operacoes'],
                'Concluídas'         => $dados['kpis']['concluidas'],
                'Taxa de conclusão'  => $dados['kpis']['taxaConclusao'],
                'Distância total'    => $dados['kpis']['distancia'] . ' km',
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('admin-frota-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Núcleo — calcula os dados uma vez para todos os métodos
    // ============================================================
    private function dados(Request $request): array
    {
        $de  = $request->filled('de')
            ? Carbon::parse($request->de)->startOfDay()
            : Carbon::today()->subDays(30);

        $ate = $request->filled('ate')
            ? Carbon::parse($request->ate)->endOfDay()
            : Carbon::today();

        $filtroViatura = $request->input('viatura', '');
        $filtroEstado  = $request->input('estado', '');

        return [
            'periodo' => [
                'de'  => $de->format('Y-m-d'),
                'ate' => $ate->format('Y-m-d'),
            ],
            'kpis'      => $this->kpis($de, $ate, $filtroViatura),
            'viaturas'  => $this->viaturas($de, $ate),
            'operacoes' => $this->operacoes($de, $ate, $filtroViatura, $filtroEstado),
            'opcoes'    => [
                'viaturas' => $this->opcoesViaturas(),
                'estados'  => ['Planeada', 'Em curso', 'Concluída', 'Cancelada'],
            ],
        ];
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function kpis(Carbon $de, Carbon $ate, string $filtroViatura): array
    {
        $query = DB::table('patrulhas')
            ->whereBetween('created_at', [$de, $ate]);

        if ($filtroViatura) {
            $query->where('viatura', $filtroViatura);
        }

        // ⭐ 2 queries (total + concluidas) em vez de 3
        $row = (clone $query)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'Concluída' THEN 1 ELSE 0 END) as concluidas
            ")
            ->first();

        $total      = (int) $row->total;
        $concluidas = (int) $row->concluidas;
        $taxa       = $total > 0 ? round(($concluidas / $total) * 100, 1) : 0;

        return [
            'operacoes'     => $total,
            'concluidas'    => $concluidas,
            'taxaConclusao' => $taxa . '%',
            'noPrazo'       => '—',  // sem dados reais
            'distancia'     => '—',  // sem dados reais
            'duracao'       => '—',  // sem dados reais
        ];
    }

    private function viaturas(Carbon $de, Carbon $ate): array
    {
        // ⭐ Aspas simples no CASE (não duplas)
        $viaturas = DB::table('patrulhas')
            ->select(
                'viatura',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN estado = 'Concluída' THEN 1 ELSE 0 END) as concluidas")
            )
            ->whereBetween('created_at', [$de, $ate])
            ->whereNotNull('viatura')
            ->where('viatura', '!=', '')
            ->groupBy('viatura')
            ->orderByDesc('total')
            ->get();

        return $viaturas->map(fn ($v) => [
            'viatura'     => $v->viatura,
            'operacoes'   => "{$v->concluidas}/{$v->total}",
            'gps'         => '—',
            'odometro'    => '—',
            'variacao'    => '—',
            'variacaoNum' => 0,
            'anomalia'    => false,
        ])->toArray();
    }

    private function operacoes(
        Carbon $de,
        Carbon $ate,
        string $filtroViatura,
        string $filtroEstado,
    ): array {
        $query = DB::table('patrulhas')
            ->leftJoin('users', 'users.id', '=', 'patrulhas.agente_id')
            ->select(
                'patrulhas.codigo',
                'patrulhas.turno',
                'patrulhas.viatura',
                'patrulhas.inicio',
                'patrulhas.estado',
                'users.name as responsavel',
            )
            ->whereBetween('patrulhas.created_at', [$de, $ate]);

        if ($filtroViatura) {
            $query->where('patrulhas.viatura', $filtroViatura);
        }
        if ($filtroEstado) {
            $query->where('patrulhas.estado', $filtroEstado);
        }

        return $query
            ->orderByDesc('patrulhas.created_at')
            ->limit(300)
            ->get()
            ->map(function ($p) {
                $inicio = $p->inicio
                    ? Carbon::parse($p->inicio)->format('d/m H:i')
                    : '—';

                return [
                    'codigo'      => $p->codigo,
                    'tipo'        => $p->turno ?? '—',
                    'viatura'     => $p->viatura ?: '—',
                    'agendada'    => $inicio,
                    'distancia'   => '—',
                    'estado'      => $p->estado ?? 'Planeada',
                    'responsavel' => $p->responsavel ?? null,
                ];
            })
            ->toArray();
    }

    private function opcoesViaturas(): array
    {
        return DB::table('patrulhas')
            ->select('viatura')
            ->whereNotNull('viatura')
            ->where('viatura', '!=', '')
            ->distinct()
            ->orderBy('viatura')
            ->pluck('viatura')
            ->toArray();
    }
}