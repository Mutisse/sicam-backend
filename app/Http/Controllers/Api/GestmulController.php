<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Multa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GestmulController extends Controller
{
    /**
     * GET /api/dashboard/gestmul
     */
    public function dashboard(Request $request)
    {
        // ═══════════════════════════════════════════════════════
        // 1) KPIs + valores — 1 query
        // ═══════════════════════════════════════════════════════
        $m = Multa::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Paga'       THEN 1 ELSE 0 END) as pagas,
            SUM(CASE WHEN estado = 'Pendente'   THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN estado = 'Contestada' THEN 1 ELSE 0 END) as contestadas,
            SUM(valor) as valor_total,
            SUM(CASE WHEN estado = 'Paga'     THEN valor ELSE 0 END) as valor_pago,
            SUM(CASE WHEN estado = 'Pendente' THEN valor ELSE 0 END) as valor_pendente
        ")->first();

        $total       = (int) $m->total;
        $pagas       = (int) $m->pagas;
        $pendentes   = (int) $m->pendentes;
        $contestadas = (int) $m->contestadas;
        $valorTotal  = (float) $m->valor_total;
        $valorPago   = (float) $m->valor_pago;
        $valorPend   = (float) $m->valor_pendente;
        $ticketMedio = $total > 0 ? $valorTotal / $total : 0;
        $taxa        = $total > 0 ? round(($pagas / $total) * 100, 1) : 0;

        $kpis = [
            ['key' => 'emitidas',   'label' => 'Emitidas',    'valor' => $total,       'total' => $total ?: 1, 'variant' => 'g'],
            ['key' => 'pagas',      'label' => 'Pagas',       'valor' => $pagas,       'total' => $total ?: 1, 'variant' => 'g'],
            ['key' => 'pendentes',  'label' => 'Pendentes',   'valor' => $pendentes,   'total' => $total ?: 1, 'variant' => 'y'],
            ['key' => 'contesta',   'label' => 'Contestadas', 'valor' => $contestadas, 'total' => $total ?: 1, 'variant' => 'r'],
        ];

        // ═══════════════════════════════════════════════════════
        // 2) LINHA (7 dias) — 1 query
        // ═══════════════════════════════════════════════════════
        $dias       = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $dataInicio = now()->subDays(6)->startOfDay();

        $porDia = Multa::select(
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
                'label' => $dias[$dia->dayOfWeek],
                'valor' => (int) ($porDia[$chave] ?? 0),
            ];
        }

        // ═══════════════════════════════════════════════════════
        // 3) BARRAS (top infracções) — 1 query
        // ═══════════════════════════════════════════════════════
        $barras = Multa::select('infracao', DB::raw('COUNT(*) as total'))
            ->groupBy('infracao')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($m) => [
                'label' => $m->infracao,
                'valor' => (int) $m->total,
            ])
            ->toArray();

        // ═══════════════════════════════════════════════════════
        // 4) Donut — 0 queries (usa valores do KPI)
        // ═══════════════════════════════════════════════════════
        $donut = [
            ['label' => 'Pagas',       'valor' => $pagas,       'cor' => '#16a34a'],
            ['label' => 'Pendentes',   'valor' => $pendentes,   'cor' => '#d97706'],
            ['label' => 'Contestadas', 'valor' => $contestadas, 'cor' => '#dc2626'],
        ];

        // ═══════════════════════════════════════════════════════
        // 5) Cards — 0 queries
        // ═══════════════════════════════════════════════════════
        $cards = [
            [
                'titulo' => 'Valores (este mês)',
                'linhas' => [
                    ['label' => 'Cobrado',      'valor' => number_format($valorPago,  0, ',', ' ') . ' MT'],
                    ['label' => 'Pendente',     'valor' => number_format($valorPend,  0, ',', ' ') . ' MT'],
                    ['label' => 'Ticket médio', 'valor' => number_format($ticketMedio, 0, ',', ' ') . ' MT'],
                ],
            ],
            [
                'titulo'      => 'Taxa de cobrança',
                'valorGrande' => $taxa . '%',
                'linhas'      => [
                    ['label' => 'Emitidas', 'valor' => (string) $total],
                    ['label' => 'Pagas',    'valor' => (string) $pagas],
                ],
                'botao' => 'Detalhes',
            ],
            [
                'titulo' => 'Top infracções',
                'linhas' => collect($barras)->take(3)->map(function ($b) use ($total) {
                    $percent = $total > 0 ? round(($b['valor'] / $total) * 100) : 0;
                    return [
                        'label' => $b['label'],
                        'valor' => $percent . '%',
                    ];
                })->toArray(),
            ],
        ];

        // ═══════════════════════════════════════════════════════
        // 6) Tabela por província — 1 query (já estava OK)
        // ═══════════════════════════════════════════════════════
        $porProvincia = Multa::select(
                'provincia',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN estado = 'Paga' THEN 1 ELSE 0 END) as pagas"),
                DB::raw("SUM(CASE WHEN estado = 'Paga' THEN valor ELSE 0 END) as cobrado")
            )
            ->whereNotNull('provincia')
            ->groupBy('provincia')
            ->orderByDesc('total')
            ->get();

        $tabela = [
            'titulo'  => 'Por província',
            'colunas' => ['Província', 'Emitidas', 'Pagas', 'Cobrado (MT)', 'Taxa'],
            'linhas'  => $porProvincia->map(function ($p) {
                $t = (int) $p->total;
                $pg = (int) $p->pagas;
                $tx = $t > 0 ? round(($pg / $t) * 100) : 0;

                return [
                    'celulas' => [
                        $p->provincia,
                        (string) $t,
                        (string) $pg,
                        number_format((float) $p->cobrado, 0, ',', ' ') . ' MT',
                        $tx . '%',
                    ],
                ];
            })->toArray(),
        ];

        // ═══════════════════════════════════════════════════════
        // Resposta
        // ═══════════════════════════════════════════════════════
        return response()->json([
            'kpis'   => $kpis,
            'linha'  => $linha,
            'barras' => $barras,
            'donut'  => $donut,
            'cards'  => $cards,
            'tabela' => $tabela,
        ]);
    }
}