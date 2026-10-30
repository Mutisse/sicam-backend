<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patrulha;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/comum
     */
    public function comum(Request $request)
    {
        // ⭐ 1 query — Veiculo (total + críticos)
        $veiculoRow = Veiculo::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'critico' THEN 1 ELSE 0 END) as criticos
        ")->first();

        $totalVeiculos = (int) $veiculoRow->total;
        $criticos      = (int) $veiculoRow->criticos;

        // ⭐ 1 query — Patrulhas em curso
        $patrulhasAtivas = Patrulha::where('estado', 'Em curso')->count();

        // ⭐ 1 query — Agentes ativos
        $agentesAtivos = User::where('estado', 'Ativo')->count();

        $kpis = [
            [
                'key'   => 'veiculos',
                'label' => 'Veículos',
                'valor' => $totalVeiculos,
                'icon'  => 'directions_car',
            ],
            [
                'key'   => 'patrulhas',
                'label' => 'Patrulhas',
                'valor' => $patrulhasAtivas,
                'icon'  => 'local_police',
            ],
            [
                'key'   => 'agentes',
                'label' => 'Agentes',
                'valor' => $agentesAtivos,
                'icon'  => 'people',
            ],
            [
                'key'   => 'criticos',
                'label' => 'Críticos',
                'valor' => $criticos,
                'icon'  => 'error',
            ],
        ];

        return response()->json([
            'kpis'  => $kpis,
            'total' => $totalVeiculos,
        ]);
    }
}