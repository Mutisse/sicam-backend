<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patrulha;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

            // 3. Agentes
            $agentesQuery = User::query();
            if ($provincia && $provincia !== 'Todas') {
                $agentesQuery->whereHas('posto', fn($q) => $q->where('provincia', $provincia));
            }
            
            $hasEstadoUser = Schema::hasColumn('users', 'estado');
            $hasActivoUser = Schema::hasColumn('users', 'activo');

            if ($hasEstadoUser) {
                $agentesQuerySelect = "COUNT(*) as total, SUM(CASE WHEN estado = 'Ativo' THEN 1 ELSE 0 END) as ativos";
            } elseif ($hasActivoUser) {
                $agentesQuerySelect = "COUNT(*) as total, SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as ativos";
            } else {
                $agentesQuerySelect = "COUNT(*) as total, COUNT(*) as ativos";
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
            return [
                'provincias' => \App\Models\Provincia::orderBy('nome')->pluck('nome')->toArray(),
                'postos'     => \App\Models\Posto::orderBy('nome')->pluck('nome')->toArray(),
            ];
        });
    }

    // ────────────────────────────────────────────────────────
    // GET /api/dashboard/chefe/resumo-ia
    // ────────────────────────────────────────────────────────
    public function resumoIAChefe(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['resumo' => 'Não autenticado.'], 401);
        }

        // 1. Recolhe os dados reais para a IA
        $veiculos = Veiculo::count();
        
        // Considera crítico se score < 30 (ajusta conforme a tua regra de negócio)
        $criticos = Veiculo::where('score', '<', 30)->count(); 
        
        $patrulhasAtivas = Patrulha::where('estado', 'Em curso')->count();
        $agentesAtivos = User::where('estado', 'Ativo')->count();
        
        // Tenta buscar abordagens dos últimos 7 dias (com tratamento de erro se a tabela não existir)
        $abordagens = 0;
        try {
            $abordagens = \DB::table('abordagens')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
        } catch (\Throwable $e) {
            Log::warning('[SICAM-IA] Tabela abordagens não encontrada.');
        }

        // 2. Envia para o Python (Gemini)
        try {
            $url = env('SICAM_IA_URL', 'http://127.0.0.1:8001') . '/api/resumo-chefe';

            $response = Http::timeout(30)->post($url, [
                'payload' => [
                    'posto'            => $user->posto ?? 'Posto',
                    'veiculos'         => $veiculos,
                    'criticos'         => $criticos,
                    'patrulhas_ativas' => $patrulhasAtivas,
                    'agentes_ativos'   => $agentesAtivos,
                    'abordagens'       => $abordagens,
                ],
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['resumo' => 'A IA não conseguiu gerar o resumo operacional.']);
        } catch (\Throwable $e) {
            Log::error('[SICAM-IA] Erro ao gerar resumo do chefe', ['erro' => $e->getMessage()]);
            return response()->json(['resumo' => 'Erro de comunicação com o servidor de IA.']);
        }
    }
}