<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Aprovacao;
use App\Models\Multa;
use App\Models\Patrulha;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ChefeRelatorioController extends Controller
{
    private const PERFIL_AGENTE = 30003;

    /**
     * GET /api/relatorios/chefe
     */
    public function index(Request $request)
    {
        $de  = $request->filled('data_de')  ? Carbon::parse($request->data_de)->startOfDay()  : now()->subDays(30)->startOfDay();
        $ate = $request->filled('data_ate') ? Carbon::parse($request->data_ate)->endOfDay()   : now()->endOfDay();

        return response()->json([
            'kpis'                => $this->kpis($de, $ate),
            'consultasPorDia'     => $this->consultasPorDia($de, $ate),
            'consultasPorAgente'  => $this->consultasPorAgente($de, $ate),
            'desempenhoPorAgente' => $this->desempenhoPorAgente($de, $ate),
            'resumoPatrulhas'     => $this->resumoPatrulhas($de, $ate),
        ]);
    }

    /**
     * GET /api/relatorios/chefe/pdf
     */
    public function pdf(Request $request)
    {
        $de  = $request->filled('data_de')  ? Carbon::parse($request->data_de)->startOfDay()  : now()->subDays(30)->startOfDay();
        $ate = $request->filled('data_ate') ? Carbon::parse($request->data_ate)->endOfDay()   : now()->endOfDay();

        $kpis = $this->kpis($de, $ate);

        $resumo = [
            'Consultas'     => $kpis['consultas'],
            'Autos'         => $kpis['autos'],
            'Abordagens'    => $kpis['abordagens'],
            'Conformidade'  => $kpis['conformidade'] . '%',
            'Conformes'     => $kpis['conformes'],
            'Não conformes' => $kpis['naoConformes'],
        ];

        $cabecalhos = ['Agente', 'Consultas', 'Autos', 'Abordagens', 'Regularidade'];
        $linhas = [];

        foreach ($this->desempenhoPorAgente($de, $ate) as $d) {
            $linhas[] = [
                $d['nome'],
                (string) $d['consultas'],
                (string) $d['autos'],
                (string) $d['abordagens'],
                $d['regularidade'] . '%',
            ];
        }

        $linhas[] = ['', '', '', '', ''];
        $linhas[] = ['RESUMO POR PATRULHA', '', '', '', ''];
        $linhas[] = ['Código', 'Data', 'Agente', 'Duração', 'Veículos'];

        foreach ($this->resumoPatrulhas($de, $ate) as $p) {
            $linhas[] = [
                $p['codigo'],
                $p['data'],
                $p['agente'],
                $p['duracao'],
                $p['veiculos'],
            ];
        }

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Polícia da República de Moçambique',
            'sigla'       => 'PRM',
            'rodape'      => 'SICAM — Chefe de Posto · Relatório de Desempenho',
            'titulo'      => 'Relatório de Desempenho',
            'subtitulo'   => 'Período: ' . $de->format('d/m/Y') . ' a ' . $ate->format('d/m/Y'),
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => $resumo,
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('relatorio-chefe-' . date('Y-m-d') . '.pdf');
    }

    /**
     * GET /api/relatorios/chefe/xlsx
     */
    public function xlsx(Request $request)
    {
        $de  = $request->filled('data_de')  ? Carbon::parse($request->data_de)->startOfDay()  : now()->subDays(30)->startOfDay();
        $ate = $request->filled('data_ate') ? Carbon::parse($request->data_ate)->endOfDay()   : now()->endOfDay();

        $cabecalhos = ['Agente', 'Consultas', 'Autos', 'Abordagens', 'Regularidade'];
        $linhas = [];

        foreach ($this->desempenhoPorAgente($de, $ate) as $d) {
            $linhas[] = [
                $d['nome'],
                (string) $d['consultas'],
                (string) $d['autos'],
                (string) $d['abordagens'],
                $d['regularidade'] . '%',
            ];
        }

        $linhas[] = ['', '', '', '', ''];
        $linhas[] = ['RESUMO POR PATRULHA', '', '', '', ''];
        $linhas[] = ['Código', 'Data', 'Agente', 'Duração', 'Veículos'];

        foreach ($this->resumoPatrulhas($de, $ate) as $p) {
            $linhas[] = [
                $p['codigo'],
                $p['data'],
                $p['agente'],
                $p['duracao'],
                $p['veiculos'],
            ];
        }

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Relatório Chefe'),
            'relatorio-chefe-' . date('Y-m-d') . '.xlsx',
        );
    }

    // ============================================================
    // KPIs
    // ============================================================
    private function kpis(Carbon $de, Carbon $ate): array
    {
        // ⭐ 3 queries em 1 (patrulhas + multas + aprovações)
        $p = Patrulha::whereBetween('created_at', [$de, $ate])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'Concluída' THEN 1 ELSE 0 END) as concluidas
            ")
            ->first();

        $totalPatrulhas = (int) $p->total;
        $concluidas     = (int) $p->concluidas;

        $autos = Multa::whereBetween('created_at', [$de, $ate])->count();
        $aprovacoes = Aprovacao::whereBetween('created_at', [$de, $ate])->count();

        $conformidade = $totalPatrulhas > 0
            ? round(($concluidas / $totalPatrulhas) * 100, 1)
            : 0;

        return [
            'consultas'    => $autos + $totalPatrulhas + $aprovacoes,
            'autos'        => $autos,
            'abordagens'   => $totalPatrulhas,
            'conformidade' => $conformidade,
            'conformes'    => $concluidas,
            'naoConformes' => $totalPatrulhas - $concluidas,
        ];
    }

    // ============================================================
    // Consultas por dia
    // ============================================================
    private function consultasPorDia(Carbon $de, Carbon $ate): array
    {
        $fim    = $ate->copy();
        $inicio = $fim->copy()->subDays(6)->startOfDay();
        if ($inicio->lt($de)) $inicio = $de->copy();

        $porDia = Multa::whereBetween('created_at', [$inicio, $fim])
            ->selectRaw("DATE(created_at) as dia, COUNT(*) as total")
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $linha  = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fim)) {
            $chave = $cursor->toDateString();
            $linha[] = [
                'dia'       => $cursor->format('d'),
                'consultas' => (int) ($porDia[$chave] ?? 0),
            ];
            $cursor->addDay();
        }

        return $linha;
    }

    // ============================================================
    // Consultas por agente (barras)
    // ============================================================
    private function consultasPorAgente(Carbon $de, Carbon $ate): array
    {
        $rows = Multa::whereBetween('multas.created_at', [$de, $ate])
            ->whereNotNull('agente_id')
            ->leftJoin('users', 'users.id', '=', 'multas.agente_id')
            ->select('multas.agente_id', 'users.name as agente_nome', DB::raw('COUNT(*) as total'))
            ->groupBy('multas.agente_id', 'users.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) return [];

        $max = $rows->max('total');

        return $rows->map(fn ($r) => [
            'nome'        => $r->agente_nome ?? 'Agente #' . $r->agente_id,
            'valor'       => (int) $r->total,
            'percentagem' => $max > 0 ? round(($r->total / $max) * 100, 1) : 0,
        ])->toArray();
    }

    // ============================================================
    // Desempenho por agente
    // ============================================================
    private function desempenhoPorAgente(Carbon $de, Carbon $ate): array
    {
        $agentes = User::where('perfil_id', self::PERFIL_AGENTE)
            ->get(['id', 'name']);

        if ($agentes->isEmpty()) return [];

        $ids = $agentes->pluck('id')->toArray();

        // ⭐ Batch — 1 query para todas as multas
        $multasPorAgente = Multa::select('agente_id', DB::raw('COUNT(*) as total'))
            ->whereIn('agente_id', $ids)
            ->whereBetween('created_at', [$de, $ate])
            ->groupBy('agente_id')
            ->pluck('total', 'agente_id')
            ->toArray();

        // ⭐ Batch — 1 query para todas as patrulhas (total + concluídas)
        $patrulhasPorAgente = Patrulha::selectRaw("
            agente_id,
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Concluída' THEN 1 ELSE 0 END) as concluidas
        ")
            ->whereIn('agente_id', $ids)
            ->whereBetween('created_at', [$de, $ate])
            ->groupBy('agente_id')
            ->get()
            ->keyBy('agente_id');

        $resultado = [];

        foreach ($agentes as $a) {
            $multas    = (int) ($multasPorAgente[$a->id] ?? 0);
            $patrulhas = (int) ($patrulhasPorAgente[$a->id]->total ?? 0);
            $concluidas = (int) ($patrulhasPorAgente[$a->id]->concluidas ?? 0);

            $regularidade = $patrulhas > 0
                ? round(($concluidas / $patrulhas) * 100)
                : 0;

            $resultado[] = [
                'nome'         => $a->name,
                'consultas'    => $multas + $patrulhas,
                'autos'        => $multas,
                'abordagens'   => $patrulhas,
                'regularidade' => $regularidade,
            ];
        }

        usort($resultado, fn ($x, $y) => $y['consultas'] <=> $x['consultas']);

        return array_slice($resultado, 0, 10);
    }

    // ============================================================
    // Resumo por patrulha
    // ============================================================
    private function resumoPatrulhas(Carbon $de, Carbon $ate): array
    {
        return Patrulha::with('agente:id,name')
            ->whereBetween('created_at', [$de, $ate])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                $inicio = $p->inicio;
                $fim    = $p->fim;

                $duracao = '—';
                if ($inicio && $fim) {
                    $diff = $inicio->diff($fim);
                    $duracao = sprintf('%dh %02dm', $diff->h, $diff->i);
                } elseif ($inicio && $p->estado === 'Em curso') {
                    $diff = $inicio->diff(now());
                    $duracao = sprintf('%dh %02dm', $diff->h, $diff->i);
                }

                $estadoClasse = match ($p->estado) {
                    'Concluída' => 'regular',
                    'Em curso'  => 'atencao',
                    'Cancelada' => 'critico',
                    default     => 'neutral',
                };

                return [
                    'codigo'       => $p->codigo ?? '—',
                    'data'         => $p->inicio?->format('d/m') ?? '—',
                    'agente'       => $p->agente?->name ?? '—',
                    'duracao'      => $duracao,
                    'veiculos'     => $p->pontos_concluidos . '/' . $p->pontos_previstos,
                    'estado'       => $p->estado ?? 'Planeada',
                    'estadoClasse' => $estadoClasse,
                ];
            })
            ->toArray();
    }
}