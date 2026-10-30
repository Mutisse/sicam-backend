<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patrulha;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/dashboard/admin
     */
    public function admin(Request $request)
    {
        // ═══════════════════════════════════════════════════════
        // ⭐ KPIs — 1 query só com SUM(CASE WHEN)
        // ═══════════════════════════════════════════════════════
        $k = Veiculo::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'critico'   THEN 1 ELSE 0 END) as critico,
            SUM(CASE WHEN estado = 'atencao'   THEN 1 ELSE 0 END) as atencao,
            SUM(CASE WHEN estado = 'regular'   THEN 1 ELSE 0 END) as regular,
            SUM(CASE WHEN estado = 'sem-dados' THEN 1 ELSE 0 END) as sem_dados,
            SUM(CASE WHEN ultima_sincronizacao IS NULL
                       OR ultima_sincronizacao < ? THEN 1 ELSE 0 END) as sem_sincronizar
        ", [now()->subHours(24)])->first();

        $total    = (int) $k->total;
        $critico  = (int) $k->critico;
        $atencao  = (int) $k->atencao;
        $regular  = (int) $k->regular;
        $semDados = (int) $k->sem_dados;
        $semSinc  = (int) $k->sem_sincronizar;

        // ⭐ 1 query — agentes offline
        $agentesOffline = User::where('estado', 'Offline')->count();

        // ═══════════════════════════════════════════════════════
        // ⭐ Requer intervenção — 1 query
        // ═══════════════════════════════════════════════════════
        $requerIntervencao = Veiculo::with('posto:id,nome')
            ->whereNotNull('score')
            ->orderBy('score')
            ->limit(10)
            ->get()
            ->map(fn ($v) => [
                'matricula' => $v->matricula,
                'score'     => (int) $v->score,
                'posto'     => $v->posto?->nome ?? '—',
                'provincia' => $v->provincia ?? '—',
            ]);

        // ═══════════════════════════════════════════════════════
        // ⭐ Agentes em patrulha — 1 query
        // ═══════════════════════════════════════════════════════
        $agentes = Patrulha::with([
                'agente:id,name,estado',
                'posto:id,nome',
            ])
            ->where('estado', 'Em curso')
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'viatura' => $p->viatura ?? '—',
                'agente'  => $p->agente?->name ?? '—',
                'local'   => $p->local ?? $p->posto?->nome ?? '—',
                'online'  => $p->agente?->estado === 'Ativo',
            ]);

        // ═══════════════════════════════════════════════════════
        // Taxa de conformidade
        // ═══════════════════════════════════════════════════════
        $taxa = $total > 0
            ? round((($regular + $atencao * 0.5) / $total) * 100, 1)
            : 0;

        return response()->json([
            'alertas' => [
                'sem_sincronizar' => $semSinc,
                'agentes_offline' => $agentesOffline,
            ],
            'total_veiculos' => $total,
            'kpis' => [
                ['key' => 'critico',   'label' => 'Crítico',   'valor' => $critico,  'icon' => 'error'],
                ['key' => 'atencao',   'label' => 'Atenção',   'valor' => $atencao,  'icon' => 'warning'],
                ['key' => 'regular',   'label' => 'Regular',   'valor' => $regular,  'icon' => 'check_circle'],
                ['key' => 'sem-dados', 'label' => 'Sem dados', 'valor' => $semDados, 'icon' => 'help'],
            ],
            'requer_intervencao' => $requerIntervencao,
            'agentes' => $agentes,
            'taxa' => $taxa,
        ]);
    }
}