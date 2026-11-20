<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Abordagem;
use App\Models\Hotlist;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrmController extends Controller
{
    public function dashboard(Request $request)
    {
        // 1) HOTLIST (Estados em minúsculas conforme o controller)
        $h = Hotlist::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'ativo' THEN 1 ELSE 0 END) as activa,
            SUM(CASE WHEN estado = 'resolvido' THEN 1 ELSE 0 END) as resolvidos,
            SUM(CASE WHEN perigo = 'alto' THEN 1 ELSE 0 END) as perigo_alto,
            SUM(CASE WHEN estado = 'ativo' AND perigo = 'alto' THEN 1 ELSE 0 END) as alertas,
            SUM(CASE WHEN motivo = 'Furtado' THEN 1 ELSE 0 END) as furtados,
            SUM(CASE WHEN motivo = 'Clonagem suspeita' THEN 1 ELSE 0 END) as clonagem,
            SUM(CASE WHEN motivo = 'Mandado de captura' THEN 1 ELSE 0 END) as mandados,
            SUM(CASE WHEN motivo IN ('Seguro expirado', 'Multas em atraso') THEN 1 ELSE 0 END) as outros
        ")->first();

        $totalHotlist  = (int) $h->total ?: 1;
        $hotlistActiva = (int) $h->activa;
        $resolvidos    = (int) $h->resolvidos;
        $perigoAlto    = (int) $h->perigo_alto;
        $alertas       = (int) $h->alertas;

        // 2) VEÍCULOS CRÍTICOS (Nova visão)
        $veiculosCriticos = Veiculo::where('estado', 'critico')->count();

        // 3) ABORDAGENS
        $ultimos30d = now()->subDays(30);
        $a = Abordagem::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN data >= ? THEN 1 ELSE 0 END) as ultimos_30d,
            SUM(CASE WHEN resultado = 'Com detenção' THEN 1 ELSE 0 END) as com_detencao,
            SUM(CASE WHEN resultado = 'Sem incidentes' THEN 1 ELSE 0 END) as sem_incidentes
        ", [$ultimos30d])->first();

        $abordagens30d = (int) $a->ultimos_30d;
        $comDetencao   = (int) $a->com_detencao;
        $semIncidentes = (int) $a->sem_incidentes;

        // 4) LINHA (7 dias)
        $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $dataInicio = now()->subDays(6)->startOfDay();
        $porDia = Abordagem::select(DB::raw('DATE(data) as dia'), DB::raw('COUNT(*) as total'))
            ->where('data', '>=', $dataInicio)->groupBy('dia')->pluck('total', 'dia');

        $linha = [];
        for ($i = 6; $i >= 0; $i--) {
            $dia = now()->subDays($i);
            $linha[] = ['label' => $dias[$dia->dayOfWeek], 'valor' => (int) ($porDia[$dia->toDateString()] ?? 0)];
        }

        // 5) BARRAS + TABELA
        $porMotivo = Hotlist::select(
                'motivo',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN estado = 'ativo' THEN 1 ELSE 0 END) as activos"),
                DB::raw("SUM(CASE WHEN estado = 'resolvido' THEN 1 ELSE 0 END) as resolvidos")
            )->groupBy('motivo')->orderByDesc('total')->get();

        $barras = $porMotivo->map(fn ($m) => ['label' => $m->motivo, 'valor' => (int) $m->total])->toArray();
        $tabelaLinhas = $porMotivo->map(fn ($m) => [
            'celulas' => [$m->motivo, (string) $m->activos, (string) $m->resolvidos, (string) $m->total],
        ])->toArray();

        // 6) KPIs
        $kpis = [
            ['key' => 'hotlist',    'label' => 'Hotlist activa',    'valor' => $hotlistActiva, 'total' => $totalHotlist,          'variant' => 'r'],
            ['key' => 'abordagens', 'label' => 'Abordagens (30d)',  'valor' => $abordagens30d, 'total' => max($abordagens30d, 1), 'variant' => 'g'],
            ['key' => 'alertas',    'label' => 'Alertas abertos',   'valor' => $alertas,       'total' => $totalHotlist,          'variant' => 'y'],
            ['key' => 'criticos',   'label' => 'Veículos Críticos', 'valor' => $veiculosCriticos, 'total' => $totalHotlist,       'variant' => 'r'],
        ];

        // 7) Donut
        $donut = [
            ['label' => 'Furtados', 'valor' => (int) $h->furtados, 'cor' => '#dc2626'],
            ['label' => 'Clonagem', 'valor' => (int) $h->clonagem, 'cor' => '#d97706'],
            ['label' => 'Mandados', 'valor' => (int) $h->mandados, 'cor' => '#1d4ed8'],
            ['label' => 'Outros',   'valor' => (int) $h->outros,   'cor' => '#64748b'],
        ];

        // 8) Cards
        $taxa = $totalHotlist > 0 ? round(($resolvidos / $totalHotlist) * 100, 1) : 0;
        $cards = [
            [
                'titulo' => 'Hotlist',
                'linhas' => [
                    ['label' => 'Furtados',          'valor' => (string) $h->furtados],
                    ['label' => 'Clonagem suspeita', 'valor' => (string) $h->clonagem],
                    ['label' => 'Mandados captura',  'valor' => (string) $h->mandados],
                ],
            ],
            [
                'titulo'      => 'Taxa de resolução',
                'valorGrande' => $taxa . '%',
                'linhas'      => [
                    ['label' => 'Activos',    'valor' => (string) $hotlistActiva],
                    ['label' => 'Resolvidos', 'valor' => (string) $resolvidos],
                ],
                'botao' => 'Detalhes',
            ],
            [
                'titulo' => 'Abordagens (30d)',
                'linhas' => [
                    ['label' => 'Realizadas',     'valor' => (string) $abordagens30d],
                    ['label' => 'Com detenção',   'valor' => (string) $comDetencao],
                    ['label' => 'Sem incidentes', 'valor' => (string) $semIncidentes],
                ],
            ],
        ];

        return response()->json([
            'kpis'   => $kpis,
            'linha'  => $linha,
            'barras' => $barras,
            'donut'  => $donut,
            'cards'  => $cards,
            'tabela' => [
                'titulo'  => 'Hotlist por motivo',
                'colunas' => ['Motivo', 'Activos', 'Resolvidos', 'Total'],
                'linhas'  => $tabelaLinhas,
            ],
        ]);
    }
}