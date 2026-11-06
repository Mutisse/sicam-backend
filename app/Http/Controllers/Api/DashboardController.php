<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patrulha;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function comum(Request $request)
    {
        $de        = $request->input('de');
        $ate       = $request->input('ate');
        $provincia = $request->input('provincia');
        $posto     = $request->input('posto');

        $cacheKey = "dashboard_comum_" . md5(json_encode([$de, $ate, $provincia, $posto]));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($de, $ate, $provincia, $posto) {
            
            // 1. Veículos
            $veiculosQuery = Veiculo::query();
            if ($provincia && $provincia !== 'Todas') {
                $veiculosQuery->where('provincia', $provincia);
            }
            if ($posto && $posto !== 'Todos') {
                $veiculosQuery->whereHas('posto', fn($q) => $q->where('nome', $posto));
            }
            
            // Verifica se a coluna 'estado' existe em veiculos
            $hasEstadoVeiculo = Schema::hasColumn('veiculos', 'estado');

            $veiculosStats = (clone $veiculosQuery)->selectRaw($hasEstadoVeiculo 
                ? "COUNT(*) as total, SUM(CASE WHEN estado = 'critico' THEN 1 ELSE 0 END) as criticos"
                : "COUNT(*) as total, 0 as criticos"
            )->first();

            // 2. Patrulhas
            $patrulhasQuery = Patrulha::query();
            if ($de) $patrulhasQuery->whereDate('created_at', '>=', $de);
            if ($ate) $patrulhasQuery->whereDate('created_at', '<=', $ate);
            
            $hasEstadoPatrulha = Schema::hasColumn('patrulhas', 'estado');

            $patrulhasStats = (clone $patrulhasQuery)->selectRaw($hasEstadoPatrulha
                ? "COUNT(*) as total, SUM(CASE WHEN estado = 'Em curso' THEN 1 ELSE 0 END) as ativas"
                : "COUNT(*) as total, 0 as ativas"
            )->first();

            // 3. Agentes (Verifica se a coluna 'estado' ou 'activo' existe em users)
            $agentesQuery = User::query();
            if ($provincia && $provincia !== 'Todas') {
                // Nota: Se users não tiver posto_id, isto vai falhar. Verifica o Model User.
                $agentesQuery->whereHas('posto', fn($q) => $q->where('provincia', $provincia));
            }
            
            $hasEstadoUser = Schema::hasColumn('users', 'estado');
            $hasActivoUser = Schema::hasColumn('users', 'activo');

            $agentesQuerySelect = "COUNT(*) as total, 0 as ativos"; // fallback

            if ($hasEstadoUser) {
                $agentesQuerySelect = "COUNT(*) as total, SUM(CASE WHEN estado = 'Ativo' THEN 1 ELSE 0 END) as ativos";
            } elseif ($hasActivoUser) {
                $agentesQuerySelect = "COUNT(*) as total, SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as ativos";
            } else {
                $agentesQuerySelect = "COUNT(*) as total, COUNT(*) as ativos"; // assume todos ativos se não houver coluna
            }

            $agentesStats = (clone $agentesQuery)->selectRaw($agentesQuerySelect)->first();

            $kpis = [
                ['key' => 'veiculos', 'label' => 'Veículos', 'valor' => (int) $veiculosStats->total, 'icon' => 'directions_car'],
                ['key' => 'patrulhas', 'label' => 'Patrulhas', 'valor' => (int) $patrulhasStats->ativas, 'icon' => 'local_police'],
                ['key' => 'agentes', 'label' => 'Agentes', 'valor' => (int) $agentesStats->ativos, 'icon' => 'people'],
                ['key' => 'criticos', 'label' => 'Críticos', 'valor' => (int) $veiculosStats->criticos, 'icon' => 'error'],
            ];

            return [
                'kpis'  => $kpis,
                'total' => (int) $veiculosStats->total,
            ];
        });
    }

    public function catalogos()
    {
        return Cache::remember('dashboard_catalogos', now()->addHour(), function () {
            // Usa pluck('nome') para devolver apenas as strings
            return [
                'provincias' => \App\Models\Provincia::orderBy('nome')->pluck('nome')->toArray(),
                'postos'     => \App\Models\Posto::orderBy('nome')->pluck('nome')->toArray(),
            ];
        });
    }
}