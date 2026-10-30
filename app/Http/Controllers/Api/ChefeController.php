<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patrulha;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChefeController extends Controller
{
    /**
     * GET /api/dashboard/chefe
     */
    public function dashboard(Request $request)
    {
        // ═══════════════════════════════════════════════════════
        // 1) VEÍCULOS — 1 query com SUM(CASE WHEN)
        // ═══════════════════════════════════════════════════════
        $v = Veiculo::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'critico'   THEN 1 ELSE 0 END) as critico,
            SUM(CASE WHEN estado = 'atencao'   THEN 1 ELSE 0 END) as atencao,
            SUM(CASE WHEN estado = 'regular'   THEN 1 ELSE 0 END) as regular,
            SUM(CASE WHEN estado = 'sem-dados' THEN 1 ELSE 0 END) as sem_dados
        ")->first();

        $total    = (int) $v->total ?: 1;
        $critico  = (int) $v->critico;
        $atencao  = (int) $v->atencao;
        $regular  = (int) $v->regular;
        $semDados = (int) $v->sem_dados;

        $kpis = [
            ['key' => 'critico',  'label' => 'Crítico',   'valor' => $critico,  'total' => $total, 'variant' => 'r'],
            ['key' => 'atencao',  'label' => 'Atenção',   'valor' => $atencao,  'total' => $total, 'variant' => 'y'],
            ['key' => 'regular',  'label' => 'Regular',   'valor' => $regular,  'total' => $total, 'variant' => 'g'],
            ['key' => 'semDados', 'label' => 'Sem dados', 'valor' => $semDados, 'total' => $total, 'variant' => 'n'],
        ];

        // ═══════════════════════════════════════════════════════
        // 2) PATRULHAS — 1 query com SUM(CASE WHEN)
        // ═══════════════════════════════════════════════════════
        $patrulhasHoje = now()->startOfDay();

        $p = Patrulha::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Em curso'  THEN 1 ELSE 0 END) as em_curso,
            SUM(CASE WHEN estado = 'Concluída' THEN 1 ELSE 0 END) as concluidas,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as hoje
        ", [$patrulhasHoje])->first();

        $patrulhasCurso     = (int) $p->em_curso;
        $patrulhasConc      = (int) $p->concluidas;
        $patrulhasPlaneadas = (int) $p->hoje;

        // ═══════════════════════════════════════════════════════
        // 3) LINHA (7 dias) — 1 query com GROUP BY DATE
        // ═══════════════════════════════════════════════════════
        $dias       = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $dataInicio = now()->subDays(6)->startOfDay();

        $porDia = Patrulha::select(
                DB::raw('DATE(created_at) as dia'),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', $dataInicio)
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
        // 4) AGENTES — 1 query só (barras + cards)
        // ═══════════════════════════════════════════════════════
        $totalAgentes  = User::where('perfil_id', 8)->count();
        $agentesOnline = User::where('perfil_id', 8)->where('estado', 'Ativo')->count();

        // ═══════════════════════════════════════════════════════
        // 5) BARRAS (top 5 agentes) — 1 query com JOIN
        // ═══════════════════════════════════════════════════════
        $agentes = DB::table('patrulhas as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.agente_id')
            ->select('p.agente_id', DB::raw('COUNT(*) as total'), 'u.name as agente_nome')
            ->whereNotNull('p.agente_id')
            ->groupBy('p.agente_id', 'u.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'label' => $r->agente_nome ?? 'Agente #' . $r->agente_id,
                'valor' => (int) $r->total,
            ])
            ->toArray();

        // ═══════════════════════════════════════════════════════
        // 6) Donut — 0 queries
        // ═══════════════════════════════════════════════════════
        $donut = [
            ['label' => 'Regulares', 'valor' => $regular,  'cor' => '#16a34a'],
            ['label' => 'Atenção',   'valor' => $atencao,  'cor' => '#d97706'],
            ['label' => 'Críticos',  'valor' => $critico,  'cor' => '#dc2626'],
            ['label' => 'Sem dados', 'valor' => $semDados, 'cor' => '#64748b'],
        ];

        // ═══════════════════════════════════════════════════════
        // 7) Cards
        // ═══════════════════════════════════════════════════════
        $regularidade = $total > 0 ? round(($regular / $total) * 100, 1) : 0;

        $cards = [
            [
                'titulo' => 'Patrulhas de hoje',
                'linhas' => [
                    ['label' => 'Planeadas',  'valor' => (string) $patrulhasPlaneadas],
                    ['label' => 'Em curso',   'valor' => (string) $patrulhasCurso],
                    ['label' => 'Concluídas', 'valor' => (string) $patrulhasConc],
                ],
            ],
            [
                'titulo'      => 'Regularidade do posto',
                'valorGrande' => $regularidade . '%',
                'linhas'      => [
                    ['label' => 'Veículos',  'valor' => (string) $total],
                    ['label' => 'Regulares', 'valor' => (string) $regular],
                ],
                'botao' => 'Detalhes',
            ],
            [
                'titulo' => 'Agentes',
                'linhas' => [
                    ['label' => 'Total',    'valor' => (string) $totalAgentes],
                    ['label' => 'On-line',  'valor' => (string) $agentesOnline],
                    ['label' => 'Off-line', 'valor' => (string) max(0, $totalAgentes - $agentesOnline)],
                ],
            ],
        ];

        // ═══════════════════════════════════════════════════════
        // 8) Tabela por agente — 1 query com JOIN
        // ═══════════════════════════════════════════════════════
        $tabelaRaw = DB::table('patrulhas as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.agente_id')
            ->select(
                'p.agente_id',
                'u.name as agente_nome',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN p.estado = 'Concluída' THEN 1 ELSE 0 END) as concluidas"),
                DB::raw("SUM(CASE WHEN p.estado = 'Em curso'  THEN 1 ELSE 0 END) as emcurso")
            )
            ->whereNotNull('p.agente_id')
            ->groupBy('p.agente_id', 'u.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $tabelaLinhas = $tabelaRaw->map(function ($r) {
            $t    = (int) $r->total;
            $conc = (int) $r->concluidas;
            $taxa = $t > 0 ? round(($conc / $t) * 100) : 0;

            return [
                'celulas' => [
                    $r->agente_nome ?? 'Agente #' . $r->agente_id,
                    (string) $t,
                    (string) $conc,
                    (string) $r->emcurso,
                    $taxa . '%',
                ],
            ];
        })->toArray();

        // ═══════════════════════════════════════════════════════
        // Resposta
        // ═══════════════════════════════════════════════════════
        return response()->json([
            'kpis'   => $kpis,
            'linha'  => $linha,
            'barras' => $agentes,
            'donut'  => $donut,
            'cards'  => $cards,
            'tabela' => [
                'titulo'  => 'Por agente',
                'colunas' => ['Agente', 'Patrulhas', 'Concluídas', 'Em curso', 'Regularidade'],
                'linhas'  => $tabelaLinhas,
            ],
        ]);
    }
}