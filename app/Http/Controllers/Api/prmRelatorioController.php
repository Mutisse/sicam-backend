<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Abordagem;
use App\Models\Hotlist;
use App\Models\Posto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrmRelatorioController extends Controller
{
    /**
     * GET /api/relatorios/prm
     * Produz exactamente o contrato RelatorioPrm do frontend.
     */
    public function prm(Request $request)
    {
        // Frontend envia ?de=YYYY-MM-DD&ate=YYYY-MM-DD
        $inicio = $request->input('de',  now()->subDays(29)->toDateString());
        $fim    = $request->input('ate', now()->toDateString());

        // ─────────────────────────────────────────────
        // 1) KPIs agregados
        // ─────────────────────────────────────────────
        $hotlistQuery = Hotlist::query();
        $abordQuery   = Abordagem::whereBetween('data', [$inicio, $fim]);

        $hotlistActiva = (clone $hotlistQuery)->where('estado', 'ativo')->count();
        $resolvidos    = (clone $hotlistQuery)->where('estado', 'resolvido')->count();
        $emAberto      = (clone $hotlistQuery)->where('estado', 'ativo')->count();
        $totalHotlist  = (clone $hotlistQuery)->count();

        $abordagens30d = (clone $abordQuery)->count();
        $detencoes     = (clone $abordQuery)->where('resultado', 'Com detenção')->count();

        $taxaResolucao = $totalHotlist > 0
            ? round(($resolvidos / $totalHotlist) * 100, 1)
            : 0;

        // Tempo médio (dias) entre detecção e resolução
        $tempoMedio = (clone $hotlistQuery)
            ->whereNotNull('resolvido_em')
            ->whereNotNull('detectado_em')
            ->selectRaw('AVG(DATEDIFF(resolvido_em, detectado_em)) as media')
            ->value('media');
        $tempoMedio = $tempoMedio ? round((float) $tempoMedio, 1) : 0;

        $kpis = [
            'hotlistActiva'  => $hotlistActiva,
            'abordagens30d'  => $abordagens30d,
            'detencoes'      => $detencoes,
            'resolvidos'     => $resolvidos,
            'emAberto'       => $emAberto,
            'taxaResolucao'  => $taxaResolucao,
            'tempoMedio'     => $tempoMedio,
        ];

        // ─────────────────────────────────────────────
        // 2) Cards visíveis (kpisVisiveis)
        // ─────────────────────────────────────────────
        $kpisVisiveis = [
            [
                'variant'  => 'critico',
                'icon'     => 'local_police',
                'valor'    => $hotlistActiva,
                'label'    => 'Hotlist activa',
                'sub'      => 'veículos sinalizados',
            ],
            [
                'variant'  => 'info',
                'icon'     => 'fact_check',
                'valor'    => $abordagens30d,
                'label'    => 'Abordagens (30d)',
                'sub'      => 'no período selecionado',
            ],
            [
                'variant'  => 'atencao',
                'icon'     => 'gavel',
                'valor'    => $detencoes,
                'label'    => 'Detenções',
                'sub'      => 'com detenção registada',
            ],
            [
                'variant'      => 'regular',
                'icon'         => 'check_circle',
                'valor'        => $taxaResolucao,
                'unidade'      => '%',
                'label'        => 'Taxa de resolução',
                'percentagem'  => $taxaResolucao,
            ],
        ];

        // ─────────────────────────────────────────────
        // 3) Linha (últimos 30 dias)
        // ─────────────────────────────────────────────
        $porDia = (clone $abordQuery)
            ->select(DB::raw('DATE(data) as dia'), DB::raw('COUNT(*) as total'))
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $valores = [];
        $labels  = [];
        $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        for ($i = 29; $i >= 0; $i--) {
            $dia = now()->subDays($i);
            $valores[] = (int) ($porDia[$dia->toDateString()] ?? 0);
            $labels[]  = $diasSemana[$dia->dayOfWeek];
        }

        $linha = ['valores' => $valores, 'labels' => $labels];

        // ─────────────────────────────────────────────
        // 4) Anel
        // ─────────────────────────────────────────────
        $anel = [
            'taxa'      => $taxaResolucao,
            'resolvidos' => $resolvidos,
            'emAberto'   => $emAberto,
        ];

        // ─────────────────────────────────────────────
        // 5) Hotlist por motivo
        // ─────────────────────────────────────────────
        $motivosRaw = (clone $hotlistQuery)
            ->select('motivo', DB::raw('COUNT(*) as total'))
            ->groupBy('motivo')
            ->orderByDesc('total')
            ->get();

        $totalMotivos = $motivosRaw->sum('total') ?: 1;
        $coresMotivo  = ['#dc2626', '#d97706', '#2563eb', '#64748b', '#16a34a'];

        $hotlistPorMotivo = $motivosRaw->values()->map(function ($m, $i) use ($totalMotivos, $coresMotivo) {
            return [
                'nome'         => $m->motivo,
                'valor'        => (int) $m->total,
                'cor'          => $coresMotivo[$i % count($coresMotivo)],
                'percentagem'  => round(($m->total / $totalMotivos) * 100, 1),
            ];
        })->toArray();

        // ─────────────────────────────────────────────
        // 6) Desempenho por posto
        // ─────────────────────────────────────────────
        $porPosto = DB::table('abordagens as a')
            ->leftJoin('postos as p', 'p.id', '=', 'a.posto_id')
            ->whereBetween('a.data', [$inicio, $fim])
            ->select(
                'p.nome as posto',
                'p.provincia',
                DB::raw('COUNT(a.id) as abordagens'),
                DB::raw("SUM(CASE WHEN a.resultado = 'Com detenção' THEN 1 ELSE 0 END) as detencoes"),
            )
            ->groupBy('p.id', 'p.nome', 'p.provincia')
            ->orderByDesc('abordagens')
            ->limit(10)
            ->get();

        $desempenhoPorPosto = $porPosto->map(function ($p) {
            $autos = 0; // se tiveres tabela de autos, ajusta aqui
            $total = (int) $p->abordagens;
            $det   = (int) $p->detencoes;
            return [
                'posto'      => $p->posto ?? '—',
                'provincia'  => $p->provincia ?? '—',
                'abordagens' => $total,
                'detencoes'  => $det,
                'autos'      => $autos,
                'taxa'       => $total > 0 ? round((($total - $det) / $total) * 100, 1) : 0,
            ];
        })->toArray();

        // ─────────────────────────────────────────────
        // 7) Top matrículas abordadas
        // ─────────────────────────────────────────────
        $topRaw = DB::table('abordagens as a')
            ->join('veiculos as v', 'v.id', '=', 'a.veiculo_id')
            ->whereBetween('a.data', [$inicio, $fim])
            ->select(
                'v.matricula',
                DB::raw('COUNT(a.id) as total'),
                DB::raw("MAX(a.resultado) as ultimo_resultado")
            )
            ->groupBy('v.id', 'v.matricula')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topMatriculas = $topRaw->map(function ($t) {
            $nivel = $t->total >= 5 ? 'alto' : ($t->total >= 3 ? 'medio' : 'baixo');
            return [
                'matricula'  => $t->matricula,
                'motivo'     => $t->ultimo_resultado ?? 'Abordagem múltipla',
                'abordagens' => (int) $t->total,
                'nivel'      => $nivel,
            ];
        })->toArray();

        // ─────────────────────────────────────────────
        // Resposta final — contrato RelatorioPrm
        // ─────────────────────────────────────────────
        return response()->json([
            'kpis'              => $kpis,
            'kpisVisiveis'      => $kpisVisiveis,
            'linha'             => $linha,
            'anel'              => $anel,
            'hotlistPorMotivo'  => $hotlistPorMotivo,
            'desempenhoPorPosto' => $desempenhoPorPosto,
            'topMatriculas'     => $topMatriculas,
        ]);
    }

    // ============================================================
    // ⭐ IA DO RELATÓRIO
    // ============================================================
    public function analisarIA(Request $request)
    {
        $request->validate([
            'pergunta' => 'nullable|string|max:500',
            'de'       => 'nullable|date',
            'ate'      => 'nullable|date',
        ]);

        try {
            $ia = app(\App\Services\SicamIAService::class);

            // Reutiliza a lógica do prm() para ter os dados agregados
            $inicio = $request->input('de',  now()->subDays(29)->toDateString());
            $fim    = $request->input('ate', now()->toDateString());

            $relatorio = $this->prm(new Request(['de' => $inicio, 'ate' => $fim]))->getData(true);

            // Dados "reais" que a IA vê (KPIs + tabelas resumidas)
            $dadosReais = [
                'kpis'               => $relatorio['kpis'],
                'linha_valores'      => $relatorio['linha']['valores'],
                'anel'               => $relatorio['anel'],
                'hotlist_por_motivo' => $relatorio['hotlistPorMotivo'],
                'desempenho_postos'  => $relatorio['desempenhoPorPosto'],
                'top_matriculas'     => $relatorio['topMatriculas'],
            ];

            if (!empty($request->pergunta)) {
                $resposta = $ia->conversar([
                    'perfil'      => 'agente_prm',
                    'pergunta'    => $request->pergunta,
                    'contexto'    => [
                        'tipo'   => 'relatorio',
                        'de'     => $inicio,
                        'ate'    => $fim,
                    ],
                    'dados_reais' => $dadosReais,
                ]);
            } else {
                $resposta = $ia->analisarRelatorio([
                    'perfil'      => 'agente_prm',
                    'dados_reais' => $dadosReais,
                    'contexto'    => ['de' => $inicio, 'ate' => $fim],
                ]);
            }

            return response()->json([
                'resposta'        => $resposta['resposta'] ?? $resposta['resumo'] ?? 'Sem resposta da IA.',
                'sugestoes'       => $resposta['sugestoes'] ?? [
                    'Onde há mais detenções?',
                    'Que postos precisam de reforço?',
                    'Resumo executivo do período',
                ],
                'total_analisado' => count($relatorio['desempenhoPorPosto']) + count($relatorio['topMatriculas']),
            ]);
        } catch (\Throwable $e) {
            \Log::error('[PRM-REL-IA] Falha', ['erro' => $e->getMessage()]);
            return response()->json([
                'resposta'  => 'Não foi possível analisar o relatório agora.',
                'sugestoes' => [],
            ], 500);
        }
    }
}
