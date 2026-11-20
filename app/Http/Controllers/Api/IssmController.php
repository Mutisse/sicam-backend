<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Apolice;
use App\Models\Sinistro;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IssmController extends Controller
{
    public function dashboard(Request $request)
    {
        // 1) Contagens agregadas por ESTADO (não por data - é mais rápido e fiável)
        $k = Apolice::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Activa'    THEN 1 ELSE 0 END) as activas,
            SUM(CASE WHEN estado = 'A expirar' THEN 1 ELSE 0 END) as a_expirar,
            SUM(CASE WHEN estado = 'Expirada'  THEN 1 ELSE 0 END) as expiradas,
            SUM(CASE WHEN estado = 'Anulada'   THEN 1 ELSE 0 END) as anuladas
        ")->first();

        $total     = (int) $k->total;
        $activas   = (int) $k->activas;
        $aExpirar  = (int) $k->a_expirar;
        $expiradas = (int) $k->expiradas;
        $anuladas  = (int) $k->anuladas;

        // 2) Sinistros
        $sinistros = Sinistro::whereIn('estado', ['Aberto', 'Em análise'])->count();

        // 3) Lista
        $items = Apolice::with('veiculo:id,matricula')
            ->orderByDesc('validade')
            ->limit(100)
            ->get()
            ->map(fn ($a) => [
                'id'         => (string) $a->id,
                'numero'     => $a->numero,
                'seguradora' => $a->seguradora,
                'viatura'    => $a->veiculo?->matricula ?? '—',
                'estado'     => $a->estado, // Usa diretamente o estado da BD
                'validade'   => $a->validade ? $a->validade->format('m/Y') : '—',
            ]);

        // 4) Barras por seguradora
        $barras = Apolice::select('seguradora', \DB::raw('COUNT(*) as total'))
            ->groupBy('seguradora')->orderByDesc('total')
            ->get()->map(fn ($b) => ['label' => $b->seguradora, 'valor' => (int) $b->total])->toArray();

        // 5) Taxa de actividade
        $taxa = $total > 0 ? round(($activas / $total) * 100, 1) : 0;

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
}