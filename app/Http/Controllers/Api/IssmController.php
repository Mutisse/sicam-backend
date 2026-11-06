<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Apolice;
use App\Models\Sinistro;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IssmController extends Controller
{
    /**
     * GET /api/dashboard/issm
     *
     * Dashboard global de apólices (visão ISSM).
     * Lista limitada a 100; contagens via SUM(CASE) na BD.
     */
    public function dashboard(Request $request)
    {
        $hoje = Carbon::today();

        // ═══════════════════════════════════════════════════════
        // 1) Contagens agregadas — 1 query
        // ═══════════════════════════════════════════════════════
        $k = Apolice::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Anulada' THEN 1 ELSE 0 END) as anuladas,
            SUM(CASE WHEN estado != 'Anulada' AND validade >= ? AND DATEDIFF(validade, ?) > 30 THEN 1 ELSE 0 END) as activas,
            SUM(CASE WHEN estado != 'Anulada' AND validade >= ? AND DATEDIFF(validade, ?) BETWEEN 0 AND 30 THEN 1 ELSE 0 END) as a_expirar,
            SUM(CASE WHEN estado != 'Anulada' AND validade <  ? THEN 1 ELSE 0 END) as expiradas
        ", [$hoje, $hoje, $hoje, $hoje, $hoje])->first();

        $total     = (int) $k->total;
        $activas   = (int) $k->activas;
        $aExpirar  = (int) $k->a_expirar;
        $expiradas = (int) $k->expiradas;
        $anuladas  = (int) $k->anuladas;

        // ═══════════════════════════════════════════════════════
        // 2) Sinistros — 1 query (tabela separada)
        // ═══════════════════════════════════════════════════════
        $sinistros = Sinistro::query()
            ->whereIn('estado', ['Aberto', 'Em análise'])
            ->count();

        // ═══════════════════════════════════════════════════════
        // 3) Lista (limitada) — 1 query + eager load veículo
        // ═══════════════════════════════════════════════════════
        $items = Apolice::with('veiculo:id,matricula')
            ->orderByDesc('validade')
            ->limit(100)
            ->get()
            ->map(function ($a) use ($hoje) {
                return [
                    'id'         => (string) $a->id,
                    'numero'     => $a->numero,
                    'seguradora' => $a->seguradora,
                    'viatura'    => $a->veiculo?->matricula ?? '—',
                    'estado'     => $this->estadoCalculado($a, $hoje),
                    'validade'   => $a->validade
                        ? $a->validade->format('m/Y')
                        : '—',
                ];
            });

        // ═══════════════════════════════════════════════════════
        // 4) Barras por seguradora — 1 query (GROUP BY)
        // ═══════════════════════════════════════════════════════
        $barras = Apolice::query()
            ->select('seguradora')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('seguradora')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($b) => [
                'label' => $b->seguradora,
                'valor' => (int) $b->total,
            ])
            ->toArray();

        // ═══════════════════════════════════════════════════════
        // 5) Taxa de actividade
        // ═══════════════════════════════════════════════════════
        $taxa = $total > 0 ? round(($activas / $total) * 100, 1) : 0;

        // ═══════════════════════════════════════════════════════
        // Resposta
        // ═══════════════════════════════════════════════════════
        return response()->json([
            'titulo'   => 'Apólices registadas',
            'icon'     => 'description',
            'kpis'     => [
                ['key' => 'total',     'label' => 'Total apólices', 'valor' => $total,     'total' => max(1, $total), 'variant' => 'n'],
                ['key' => 'activas',   'label' => 'Activas',        'valor' => $activas,   'total' => max(1, $total), 'variant' => 'g'],
                ['key' => 'expiradas', 'label' => 'Expiradas',      'valor' => $expiradas, 'total' => max(1, $total), 'variant' => 'r'],
                ['key' => 'sinistros', 'label' => 'Sinistros abertos', 'valor' => $sinistros, 'total' => max(1, $total), 'variant' => 'y'],
            ],
            'donut'    => [
                ['label' => 'Activas',   'valor' => $activas,   'cor' => '#16a34a'],
                ['label' => 'A expirar', 'valor' => $aExpirar,  'cor' => '#f59e0b'],
                ['label' => 'Expiradas', 'valor' => $expiradas, 'cor' => '#dc2626'],
                ['label' => 'Anuladas',  'valor' => $anuladas,  'cor' => '#64748b'],
            ],
            'taxa'     => $taxa,
            'barras'   => $barras,
            'apolices' => $items,
        ]);
    }

    /**
     * Estado calculado (alinhado com IssmApoliceController::calcularEstado).
     */
    private function estadoCalculado(Apolice $a, Carbon $hoje): string
    {
        if ($a->estado === 'Anulada') {
            return 'Anulada';
        }
        if (!$a->validade) {
            return $a->estado ?? '—';
        }

        $dias = $hoje->diffInDays($a->validade, false);

        if ($dias < 0) {
            return 'Expirada';
        }
        if ($dias <= 30) {
            return 'A expirar';
        }
        return 'Activa';
    }
}