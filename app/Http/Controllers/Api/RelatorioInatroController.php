<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inspecao;
use App\Models\Multa;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RelatorioInatroController extends Controller
{
    /**
     * GET /api/relatorios/inatro/dashboard
     *
     * Agregação para a página de Relatórios INATRO.
     */
    public function dashboard(Request $request)
    {
        $dataDe    = $request->filled('data_de')   ? Carbon::parse($request->data_de)->startOfDay()   : null;
        $dataAte   = $request->filled('data_ate')  ? Carbon::parse($request->data_ate)->endOfDay()    : null;
        $provincia = $request->filled('provincia') ? $request->provincia : null;
        $posto     = $request->filled('posto')     ? $request->posto     : null;

        return response()->json([
            'kpis'                 => $this->kpis($provincia, $posto),
            'registosPorMes'       => $this->registosPorMes($dataDe, $dataAte, $provincia),
            'registosPorProvincia' => $this->registosPorProvincia(),
            'topMarcas'            => $this->topMarcas($provincia, $posto),
            'topInfracoes'         => $this->topInfracoes($dataDe, $dataAte),
            'inspecoesPorPosto'    => $this->inspecoesPorPosto($dataDe, $dataAte, $provincia),
        ]);
    }

    // ============================================================
    // KPIs
    // ============================================================
    private function kpis(?string $provincia, ?string $posto): array
    {
        $qV = Veiculo::query();
        if ($provincia) $qV->where('provincia', $provincia);
        if ($posto)     $qV->whereHas('posto', fn ($p) => $p->where('nome', $posto));

        $total = (clone $qV)->count();

        $qI = Inspecao::query();
        if ($provincia) {
            $qI->whereHas('veiculo', fn ($v) => $v->where('provincia', $provincia));
        }

        $validas   = (clone $qI)->where('estado', 'valida')->count();
        $expiradas = (clone $qI)->where('estado', 'expirada')->count();
        $pendentes = (clone $qI)->where('estado', 'pendente')->count();

        $aExpirar = Inspecao::where('estado', 'valida')
            ->whereBetween('validade', [now(), now()->addDays(30)])
            ->count();

        $taxa = $total > 0 ? round(($validas / $total) * 100, 1) : 0;

        return [
            'total'      => $total,
            'validas'    => $validas,
            'expiradas'  => $expiradas,
            'pendentes'  => $pendentes,
            'aExpirar'   => $aExpirar,
            'taxa'       => $taxa,
            'tempoMedio' => 30,
        ];
    }

    // ============================================================
    // Registos por mês (últimos 6 meses)
    // ============================================================
    private function registosPorMes(?Carbon $de, ?Carbon $ate, ?string $provincia): array
    {
        $inicio = $de ?? now()->subMonths(5)->startOfMonth();
        $fim    = $ate ?? now()->endOfMonth();

        $q = Veiculo::query()->whereBetween('created_at', [$inicio, $fim]);
        if ($provincia) $q->where('provincia', $provincia);

        $registos = $q
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $meses  = [];
        $cursor = $inicio->copy()->startOfMonth();

        while ($cursor->lte($fim)) {
            $ym = $cursor->format('Y-m');
            $meses[] = [
                'mes'   => $cursor->translatedFormat('M'),
                'ym'    => $ym,
                'valor' => (int) ($registos[$ym] ?? 0),
            ];
            $cursor->addMonth();
        }

        return $meses;
    }

    // ============================================================
    // Registos por província
    // ============================================================
    private function registosPorProvincia(): array
    {
        return Veiculo::query()
            ->selectRaw("COALESCE(provincia, 'Desconhecida') as nome, COUNT(*) as valor")
            ->groupBy('nome')
            ->orderByDesc('valor')
            ->limit(11)
            ->get()
            ->map(fn ($r) => ['nome' => $r->nome, 'valor' => (int) $r->valor])
            ->toArray();
    }

    // ============================================================
    // Top marcas
    // ============================================================
    private function topMarcas(?string $provincia, ?string $posto): array
    {
        $q = Veiculo::query();
        if ($provincia) $q->where('provincia', $provincia);
        if ($posto)     $q->whereHas('posto', fn ($p) => $p->where('nome', $posto));

        $total = (clone $q)->count();

        return (clone $q)
            ->selectRaw("COALESCE(marca, 'Desconhecida') as marca, COUNT(*) as veiculos")
            ->groupBy('marca')
            ->orderByDesc('veiculos')
            ->limit(5)
            ->get()
            ->map(fn ($m) => [
                'marca'       => $m->marca,
                'veiculos'    => (int) $m->veiculos,
                'percentagem' => $total > 0 ? round(($m->veiculos / $total) * 100, 1) : 0,
            ])
            ->toArray();
    }

    // ============================================================
    // Top infrações (multas)
    // ============================================================
   private function topInfracoes(?Carbon $de, ?Carbon $ate): array
{
    if (!class_exists(Multa::class)) return [];

    $q = Multa::query();
    if ($de)  $q->where('created_at', '>=', $de);
    if ($ate) $q->where('created_at', '<=', $ate);

    $total = (clone $q)->count();
    if ($total === 0) return [];

    // Descobrir a coluna automaticamente (a tabela usa 'infracao')
    $coluna = null;
    foreach (['infracao', 'tipo', 'motivo', 'descricao'] as $c) {
        if (Schema::hasColumn('multas', $c)) {
            $coluna = $c;
            break;
        }
    }
    if (!$coluna) return [];

    return $q
        ->selectRaw("COALESCE({$coluna}, 'Outra') as tipo, COUNT(*) as total")
        ->groupBy('tipo')
        ->orderByDesc('total')
        ->limit(5)
        ->get()
        ->map(fn ($m) => [
            'tipo'        => $m->tipo,
            'total'       => (int) $m->total,
            'percentagem' => round(($m->total / $total) * 100, 1),
        ])
        ->toArray();
}

    // ============================================================
    // Inspecções por posto
    // ============================================================
    private function inspecoesPorPosto(?Carbon $de, ?Carbon $ate, ?string $provincia): array
    {
        $q = Inspecao::query()->with('posto:id,nome,provincia');

        if ($de) $q->where('data', '>=', $de);
        if ($ate) $q->where('data', '<=', $ate);

        if ($provincia) {
            $q->whereHas('posto', fn ($p) => $p->where('provincia', $provincia));
        }

        return $q
            ->select('posto_id')
            ->selectRaw("COUNT(*) as realizadas")
            ->selectRaw("SUM(CASE WHEN resultado = 'Aprovada' THEN 1 ELSE 0 END) as aprovadas")
            ->selectRaw("SUM(CASE WHEN resultado = 'Reprovada' THEN 1 ELSE 0 END) as reprovadas")
            ->groupBy('posto_id')
            ->orderByDesc('realizadas')
            ->get()
            ->map(function ($r) {
                $posto = $r->posto;
                $taxa  = $r->realizadas > 0
                    ? round(($r->aprovadas / $r->realizadas) * 100, 1)
                    : 0;

                return [
                    'posto'      => $posto?->nome ?? '—',
                    'provincia'  => $posto?->provincia ?? '—',
                    'realizadas' => (int) $r->realizadas,
                    'aprovadas'  => (int) $r->aprovadas,
                    'reprovadas' => (int) $r->reprovadas,
                    'taxa'       => $taxa,
                ];
            })
            ->toArray();
    }
}