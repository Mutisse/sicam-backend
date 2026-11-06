<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inspecao;
use App\Models\Veiculo;
use Illuminate\Support\Facades\DB;

class InatroController extends Controller
{
    /**
     * GET /api/dashboard/inatro
     *
     * Dashboard de estado actual (sem filtro de período).
     * Inspecções são contadas por veículo único (COUNT DISTINCT veiculo_id),
     * porque um veículo pode ter várias inspecções ao longo do tempo.
     */
    public function dashboard()
    {
        // ═══════════════════════════════════════════════════════
        // 1) KPIs + cards — 1 query por tabela
        // ═══════════════════════════════════════════════════════

        $totalRegistados = Veiculo::count();

        // Inspeção: conta VEÍCULOS ÚNICOS por estado (não inspecções)
        $ins = Inspecao::selectRaw("
            COUNT(*) as total,
            COUNT(DISTINCT CASE WHEN estado = 'valida'   THEN veiculo_id END) as ok,
            COUNT(DISTINCT CASE WHEN estado = 'expirada' THEN veiculo_id END) as expiradas,
            COUNT(DISTINCT CASE WHEN estado = 'pendente' THEN veiculo_id END) as pendentes,
            SUM(CASE WHEN resultado = 'Aprovada'  THEN 1 ELSE 0 END) as aprovadas,
            SUM(CASE WHEN resultado = 'Reprovada' THEN 1 ELSE 0 END) as reprovadas
        ")->first();

        $inspecaoOk  = (int) $ins->ok;
        $inspecaoExp = (int) $ins->expiradas;
        $pendentes   = (int) $ins->pendentes;
        $aprovadas   = (int) $ins->aprovadas;
        $reprovadas  = (int) $ins->reprovadas;
        $totalIns    = (int) $ins->total;

        // KPIs — valor nunca excede o total (garantido pelo COUNT DISTINCT)
        $kpis = [
            ['key' => 'registados',  'label' => 'Registados',         'valor' => $totalRegistados, 'total' => max($totalRegistados, 1), 'variant' => 'g'],
            ['key' => 'inspecaoOk',  'label' => 'Inspecção válida',   'valor' => $inspecaoOk,      'total' => max($totalRegistados, 1), 'variant' => 'g'],
            ['key' => 'inspecaoExp', 'label' => 'Inspecção expirada', 'valor' => $inspecaoExp,     'total' => max($totalRegistados, 1), 'variant' => 'r'],
            ['key' => 'pendentes',   'label' => 'Pendentes',          'valor' => $pendentes,       'total' => max($totalRegistados, 1), 'variant' => 'y'],
        ];

        // ═══════════════════════════════════════════════════════
        // 2) Linha (últimos 7 dias) — 1 query com GROUP BY
        // ═══════════════════════════════════════════════════════
        $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $dataInicio = now()->subDays(6)->startOfDay();

        $porDia = Inspecao::select(
                DB::raw('DATE(data) as dia'),
                DB::raw('COUNT(*) as total')
            )
            ->where('data', '>=', $dataInicio)
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $linha = [];
        for ($i = 6; $i >= 0; $i--) {
            $dia   = now()->subDays($i);
            $chave = $dia->toDateString();
            $linha[] = [
                'label' => $diasSemana[$dia->dayOfWeek],
                'valor' => (int) ($porDia[$chave] ?? 0),
            ];
        }

        // ═══════════════════════════════════════════════════════
        // 3) Barras por província — 1 query
        // ═══════════════════════════════════════════════════════
        $barras = Veiculo::select('provincia', DB::raw('COUNT(*) as total'))
            ->whereNotNull('provincia')
            ->groupBy('provincia')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($v) => [
                'label' => $v->provincia,
                'valor' => (int) $v->total,
            ])
            ->toArray();

        // ═══════════════════════════════════════════════════════
        // 4) Donut — "Outros" calculado (não hardcoded)
        // ═══════════════════════════════════════════════════════
        $outros = max($totalIns - $inspecaoOk - $inspecaoExp - $pendentes, 0);

        $donut = [
            ['label' => 'Válidas',   'valor' => $inspecaoOk,  'cor' => '#16a34a'],
            ['label' => 'Expiradas', 'valor' => $inspecaoExp, 'cor' => '#dc2626'],
            ['label' => 'Pendentes', 'valor' => $pendentes,   'cor' => '#d97706'],
            ['label' => 'Outros',    'valor' => $outros,      'cor' => '#64748b'],
        ];

        // ═══════════════════════════════════════════════════════
        // 5) Cards
        // ═══════════════════════════════════════════════════════
        $taxa = ($aprovadas + $reprovadas) > 0
            ? round(($aprovadas / ($aprovadas + $reprovadas)) * 100, 1)
            : 0;

        $cards = [
            [
                'titulo' => 'Inspecções (histórico)',
                'linhas' => [
                    ['label' => 'Aprovadas',  'valor' => (string) $aprovadas],
                    ['label' => 'Reprovadas', 'valor' => (string) $reprovadas],
                    ['label' => 'Pendentes',  'valor' => (string) $pendentes],
                ],
            ],
            [
                'titulo'      => 'Taxa de aprovação',
                'valorGrande' => $taxa . '%',
                'linhas'      => [
                    ['label' => 'Aprovadas',  'valor' => (string) $aprovadas],
                    ['label' => 'Reprovadas', 'valor' => (string) $reprovadas],
                ],
                'botao' => 'Detalhes',
            ],
            [
                'titulo' => 'Registos',
                'linhas' => [
                    ['label' => 'Total de veículos',   'valor' => (string) $totalRegistados],
                    ['label' => 'Pendentes validação', 'valor' => (string) $pendentes],
                ],
            ],
        ];

        // ═══════════════════════════════════════════════════════
        // 6) Tabela por província — conta VEÍCULOS ÚNICOS por estado
        //    (COUNT DISTINCT garante taxa ≤ 100%)
        // ═══════════════════════════════════════════════════════
        $tabelaRaw = DB::table('veiculos as v')
            ->leftJoin('inspecoes as i', 'i.veiculo_id', '=', 'v.id')
            ->select(
                'v.provincia',
                DB::raw('COUNT(DISTINCT v.id) as total_veiculos'),
                DB::raw("COUNT(DISTINCT CASE WHEN i.estado = 'valida'   THEN v.id END) as ok"),
                DB::raw("COUNT(DISTINCT CASE WHEN i.estado = 'expirada' THEN v.id END) as expiradas")
            )
            ->whereNotNull('v.provincia')
            ->groupBy('v.provincia')
            ->orderByDesc('total_veiculos')
            ->get();

        $tabela = $tabelaRaw->map(function ($row) {
            $total    = (int) $row->total_veiculos;
            $ok       = (int) $row->ok;
            $exp      = (int) $row->expiradas;
            $taxaProv = $total > 0 ? round(($ok / $total) * 100) : 0;

            return [
                'celulas' => [
                    $row->provincia,
                    (string) $total,
                    (string) $ok,
                    (string) $exp,
                    $taxaProv . '%',
                ],
            ];
        })->toArray();

        // ═══════════════════════════════════════════════════════
        // Resposta
        // ═══════════════════════════════════════════════════════
        return response()->json([
            'kpis'   => $kpis,
            'linha'  => $linha,
            'barras' => $barras,
            'donut'  => $donut,
            'cards'  => $cards,
            'tabela' => [
                'titulo'  => 'Por província',
                'colunas' => ['Província', 'Veículos', 'Inspecções OK', 'Expiradas', 'Taxa'],
                'linhas'  => $tabela,
            ],
        ]);
    }
}