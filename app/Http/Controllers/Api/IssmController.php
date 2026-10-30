<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Apolice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IssmController extends Controller
{
    /**
     * GET /api/dashboard/issm
     */
    public function dashboard(Request $request)
    {
        // ⭐ 1 query — lista com veículo
        $apolices = Apolice::with('veiculo:id,matricula')
            ->orderByDesc('validade')
            ->get();

        // ⭐ Contagens em memória (0 queries)
        $hoje = now();
        $activas   = 0;
        $expiradas = 0;
        $sinistros = 0;

        $items = $apolices->map(function ($a) use ($hoje, &$activas, &$expiradas, &$sinistros) {
            $estado = $a->estado;

            // Recalcular estado com base na validade
            if ($a->validade && $a->validade->isPast()) {
                $estado = 'Expirada';
            }

            // Contagens
            if ($estado === 'Activa')     $activas++;
            if ($estado === 'Expirada')   $expiradas++;
            if ($estado === 'Sinistro')   $sinistros++;

            return [
                'id'         => $a->numero,
                'seguradora' => $a->seguradora,
                'viatura'    => $a->veiculo?->matricula ?? '—',
                'estado'     => $estado,
                'validade'   => $a->validade
                    ? $a->validade->format('m/Y')
                    : '—',
            ];
        });

        $total = $apolices->count();
        $taxa  = $total > 0 ? round(($activas / $total) * 100, 1) : 0;

        // ⭐ Distribuição por seguradora (1 query só)
        $barras = $apolices
            ->groupBy('seguradora')
            ->map(fn ($grupo, $nome) => [
                'label' => $nome,
                'valor' => $grupo->count(),
            ])
            ->sortByDesc('valor')
            ->values()
            ->toArray();

        return response()->json([
            'titulo'   => 'As minhas apólices',
            'icon'     => 'description',
            'kpis'     => [
                ['key' => 'total',     'label' => 'Total apólices', 'valor' => $total,      'total' => max(1, $total), 'variant' => 'n'],
                ['key' => 'activas',   'label' => 'Activas',        'valor' => $activas,    'total' => max(1, $total), 'variant' => 'g'],
                ['key' => 'expiradas', 'label' => 'Expiradas',      'valor' => $expiradas,  'total' => max(1, $total), 'variant' => 'r'],
                ['key' => 'sinistros', 'label' => 'Sinistros',      'valor' => $sinistros,  'total' => max(1, $total), 'variant' => 'y'],
            ],
            'donut'    => [
                ['label' => 'Activas',   'valor' => $activas,   'cor' => '#16a34a'],
                ['label' => 'Expiradas', 'valor' => $expiradas, 'cor' => '#dc2626'],
                ['label' => 'Sinistros', 'valor' => $sinistros, 'cor' => '#d97706'],
            ],
            'taxa'     => $taxa,
            'barras'   => $barras,
            'apolices' => $items,
        ]);
    }
}