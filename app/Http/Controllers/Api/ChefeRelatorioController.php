<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Aprovacao;
use App\Models\Multa;
use App\Models\Patrulha;
use App\Models\User;
use App\Services\SicamIAService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ChefeRelatorioController extends Controller
{
    private const PERFIL_AGENTE = 30003;

    protected SicamIAService $ia;

    public function __construct(SicamIAService $ia)
    {
        $this->ia = $ia;
    }

    /**
     * GET /api/relatorios/chefe
     */
    public function index(Request $request)
    {
        $de  = $request->filled('data_de')  ? Carbon::parse($request->data_de)->startOfDay()  : now()->subDays(30)->startOfDay();
        $ate = $request->filled('data_ate') ? Carbon::parse($request->data_ate)->endOfDay()   : now()->endOfDay();
        $posto = $request->user()?->posto;

        return response()->json([
            'kpis'                => $this->kpis($de, $ate, $posto),
            'consultasPorDia'     => $this->consultasPorDia($de, $ate, $posto),
            'consultasPorAgente'  => $this->consultasPorAgente($de, $ate, $posto),
            'desempenhoPorAgente' => $this->desempenhoPorAgente($de, $ate, $posto),
            'resumoPatrulhas'     => $this->resumoPatrulhas($de, $ate, $posto),
        ]);
    }

    /**
     * GET /api/relatorios/chefe/resumo-ia
     */
    public function resumoIA(Request $request)
    {
        $de  = $request->filled('data_de')  ? Carbon::parse($request->data_de)->startOfDay()  : now()->subDays(30)->startOfDay();
        $ate = $request->filled('data_ate') ? Carbon::parse($request->data_ate)->endOfDay()   : now()->endOfDay();
        $posto = $request->user()?->posto;

        $kpis = $this->kpis($de, $ate, $posto);
        $desempenho = $this->desempenhoPorAgente($de, $ate, $posto);
        $patrulhas = $this->resumoPatrulhas($de, $ate, $posto);

        $dados = [
            'periodo' => [
                'de'  => $de->format('d/m/Y'),
                'ate' => $ate->format('d/m/Y'),
            ],
            'posto' => $posto ?? 'Geral',
            'kpis' => [
                'consultas'    => $kpis['consultas'],
                'autos'        => $kpis['autos'],
                'abordagens'   => $kpis['abordagens'],
                'conformidade' => $kpis['conformidade'],
                'conformes'    => $kpis['conformes'],
                'nao_conformes'=> $kpis['naoConformes'],
            ],
            'desempenho_agentes' => array_map(fn ($d) => [
                'nome'         => $d['nome'],
                'consultas'    => $d['consultas'],
                'autos'        => $d['autos'],
                'abordagens'   => $d['abordagens'],
                'regularidade' => $d['regularidade'],
            ], $desempenho),
            'patrulhas_recentes' => array_map(fn ($p) => [
                'codigo'  => $p['codigo'],
                'agente'  => $p['agente'],
                'estado'  => $p['estado'],
                'duracao' => $p['duracao'],
            ], array_slice($patrulhas, 0, 5)),
        ];

        return response()->json($this->ia->analisarRelatorio($dados));
    }

    /**
     * GET /api/relatorios/chefe/pdf
     */
    public function pdf(Request $request)
    {
        $de  = $request->filled('data_de')  ? Carbon::parse($request->data_de)->startOfDay()  : now()->subDays(30)->startOfDay();
        $ate = $request->filled('data_ate') ? Carbon::parse($request->data_ate)->endOfDay()   : now()->endOfDay();
        $posto = $request->user()?->posto;

        $kpis = $this->kpis($de, $ate, $posto);
        $resumo = [
            'Consultas'     => $kpis['consultas'],
            'Autos'         => $kpis['autos'],
            'Abordagens'    => $kpis['abordagens'],
            'Conformidade'  => $kpis['conformidade'] . '%',
        ];

        $cabecalhos = ['Agente', 'Consultas', 'Autos', 'Abordagens', 'Regularidade'];
        $linhas = [];
        foreach ($this->desempenhoPorAgente($de, $ate, $posto) as $d) {
            $linhas[] = [$d['nome'], $d['consultas'], $d['autos'], $d['abordagens'], $d['regularidade'].'%'];
        }

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — PRM',
            'sigla'       => 'PRM',
            'rodape'      => 'SICAM — Relatório',
            'titulo'      => 'Relatório — ' . ($posto ?? 'Geral'),
            'subtitulo'   => $de->format('d/m/Y') . ' a ' . $ate->format('d/m/Y'),
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => $resumo,
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('relatorio-' . date('Y-m-d') . '.pdf');
    }

    /**
     * GET /api/relatorios/chefe/xlsx
     */
    public function xlsx(Request $request)
    {
        $de  = $request->filled('data_de')  ? Carbon::parse($request->data_de)->startOfDay()  : now()->subDays(30)->startOfDay();
        $ate = $request->filled('data_ate') ? Carbon::parse($request->data_ate)->endOfDay()   : now()->endOfDay();
        $posto = $request->user()?->posto;

        $cabecalhos = ['Agente', 'Consultas', 'Autos', 'Abordagens', 'Regularidade'];
        $linhas = [];
        foreach ($this->desempenhoPorAgente($de, $ate, $posto) as $d) {
            $linhas[] = [$d['nome'], $d['consultas'], $d['autos'], $d['abordagens'], $d['regularidade'].'%'];
        }

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Relatório'),
            'relatorio-' . date('Y-m-d') . '.xlsx',
        );
    }

    // ============================================================
    // Helpers (blindados para não dar 500)
    // ============================================================
    private function kpis(Carbon $de, Carbon $ate, ?string $posto): array
    {
        $totalPatrulhas = 0;
        $concluidas = 0;

        try {
            $q = Patrulha::whereBetween('created_at', [$de, $ate]);
            if ($posto) $q->where('local', 'like', "%{$posto}%");
            $p = $q->selectRaw("COUNT(*) as t, SUM(CASE WHEN estado = 'Concluída' THEN 1 ELSE 0 END) as c")->first();
            $totalPatrulhas = (int) ($p->t ?? 0);
            $concluidas = (int) ($p->c ?? 0);
        } catch (\Throwable $e) {}

        $autos = 0;
        try {
            $q = Multa::whereBetween('created_at', [$de, $ate]);
            if ($posto) {
                $agenteIds = User::where('perfil_id', self::PERFIL_AGENTE)->where('posto', $posto)->pluck('id');
                $q->whereIn('agente_id', $agenteIds);
            }
            $autos = $q->count();
        } catch (\Throwable $e) {}

        $aprovacoes = 0;
        try {
            $q = Aprovacao::whereBetween('created_at', [$de, $ate]);
            if ($posto && \Schema::hasColumn('aprovacoes', 'posto')) $q->where('posto', $posto);
            $aprovacoes = $q->count();
        } catch (\Throwable $e) {}

        $conformidade = $totalPatrulhas > 0 ? round(($concluidas / $totalPatrulhas) * 100, 1) : 0;

        return [
            'consultas'    => $autos + $totalPatrulhas + $aprovacoes,
            'autos'        => $autos,
            'abordagens'   => $totalPatrulhas,
            'conformidade' => $conformidade,
            'conformes'    => $concluidas,
            'naoConformes' => $totalPatrulhas - $concluidas,
        ];
    }

    private function consultasPorDia(Carbon $de, Carbon $ate, ?string $posto): array
    {
        $fim    = $ate->copy();
        $inicio = $fim->copy()->subDays(6)->startOfDay();
        if ($inicio->lt($de)) $inicio = $de->copy();

        $porDia = collect();
        try {
            $q = Multa::whereBetween('created_at', [$inicio, $fim]);
            if ($posto) {
                $ids = User::where('perfil_id', self::PERFIL_AGENTE)->where('posto', $posto)->pluck('id');
                $q->whereIn('agente_id', $ids);
            }
            $porDia = $q->selectRaw("DATE(created_at) as dia, COUNT(*) as total")->groupBy('dia')->pluck('total', 'dia');
        } catch (\Throwable $e) {}

        $linha = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fim)) {
            $chave = $cursor->toDateString();
            $linha[] = ['dia' => $cursor->format('d'), 'consultas' => (int) ($porDia[$chave] ?? 0)];
            $cursor->addDay();
        }
        return $linha;
    }

    private function consultasPorAgente(Carbon $de, Carbon $ate, ?string $posto): array
    {
        try {
            $q = Multa::whereBetween('created_at', [$de, $ate])->whereNotNull('agente_id');
            if ($posto) {
                $ids = User::where('perfil_id', self::PERFIL_AGENTE)->where('posto', $posto)->pluck('id');
                $q->whereIn('agente_id', $ids);
            }
            $rows = $q->leftJoin('users', 'users.id', '=', 'multas.agente_id')
                ->select('multas.agente_id', 'users.name as agente_nome', DB::raw('COUNT(*) as total'))
                ->groupBy('multas.agente_id', 'users.name')
                ->orderByDesc('total')
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        if ($rows->isEmpty()) return [];
        $max = $rows->max('total');
        return $rows->map(fn ($r) => [
            'nome'        => $r->agente_nome ?? 'Agente #' . $r->agente_id,
            'valor'       => (int) $r->total,
            'percentagem' => $max > 0 ? round(($r->total / $max) * 100, 1) : 0,
        ])->toArray();
    }

    private function desempenhoPorAgente(Carbon $de, Carbon $ate, ?string $posto): array
    {
        $agentes = User::where('perfil_id', self::PERFIL_AGENTE)
            ->when($posto, fn ($q) => $q->where('posto', $posto))
            ->get(['id', 'name']);

        if ($agentes->isEmpty()) return [];
        $ids = $agentes->pluck('id')->toArray();

        $multasPorAgente = [];
        try {
            $multasPorAgente = Multa::select('agente_id', DB::raw('COUNT(*) as total'))
                ->whereIn('agente_id', $ids)
                ->whereBetween('created_at', [$de, $ate])
                ->groupBy('agente_id')
                ->pluck('total', 'agente_id')
                ->toArray();
        } catch (\Throwable $e) {}

        $patrulhasPorAgente = collect();
        try {
            $patrulhasPorAgente = Patrulha::selectRaw("agente_id, COUNT(*) as total, SUM(CASE WHEN estado = 'Concluída' THEN 1 ELSE 0 END) as concluidas")
                ->whereIn('agente_id', $ids)
                ->whereBetween('created_at', [$de, $ate])
                ->groupBy('agente_id')
                ->get()
                ->keyBy('agente_id');
        } catch (\Throwable $e) {}

        $resultado = [];
        foreach ($agentes as $a) {
            $multas = (int) ($multasPorAgente[$a->id] ?? 0);
            $patrulhas = (int) ($patrulhasPorAgente[$a->id]->total ?? 0);
            $concluidas = (int) ($patrulhasPorAgente[$a->id]->concluidas ?? 0);
            $regularidade = $patrulhas > 0 ? round(($concluidas / $patrulhas) * 100) : 0;

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

    private function resumoPatrulhas(Carbon $de, Carbon $ate, ?string $posto): array
    {
        try {
            $q = Patrulha::with('agente:id,name')->whereBetween('created_at', [$de, $ate]);
            if ($posto) $q->where('local', 'like', "%{$posto}%");
            return $q->orderByDesc('created_at')->limit(10)->get()->map(function ($p) {
                $inicio = $p->inicio;
                $fim    = $p->fim;
                $duracao = '—';
                if ($inicio && $fim) {
                    $diff = $inicio->diff($fim);
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
            })->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}