<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultaAgente;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgenteHistoricoController extends Controller
{
    /**
     * GET /api/agente/historico
     * Query params opcionais: periodo=hoje|7d|30d|tudo, estado=critico|atencao|regular, q=busca
     */
    public function index(Request $request)
    {
        $eu = auth()->user();
        if (!$eu) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $query = ConsultaAgente::query()
            ->where('agente_id', $eu->id)
            ->orderByDesc('consultado_em');

        // Filtro de período
        $periodo = $request->get('periodo', 'tudo');
        $desde = match ($periodo) {
            'hoje'   => Carbon::today(),
            '7d'     => Carbon::now()->subDays(7),
            '30d'    => Carbon::now()->subDays(30),
            default  => null,
        };
        if ($desde) {
            $query->where('consultado_em', '>=', $desde);
        }

        // Filtro de estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Busca
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($s) use ($q) {
                $s->where('matricula', 'like', "%{$q}%")
                  ->orWhere('posto', 'like', "%{$q}%");
            });
        }

        $items = $query->limit(500)->get()->map(fn ($c) => $this->formatar($c));

        return response()->json([
            'items' => $items,
            'total' => $items->count(),
            'kpis'  => $this->kpis($eu->id),
        ]);
    }

    /**
     * POST /api/agente/historico
     * Regista uma consulta (chamado quando o agente consulta um veículo).
     */
    public function store(Request $request)
    {
        $eu = auth()->user();
        if (!$eu) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $dados = $request->validate([
            'matricula' => 'required|string|max:20',
            'origem'    => 'nullable|string|in:manual,camera,historico',
        ]);

        $veiculo = Veiculo::where('matricula', strtoupper($dados['matricula']))->first();

        $consulta = ConsultaAgente::create([
            'agente_id'     => $eu->id,
            'veiculo_id'    => $veiculo?->id,
            'matricula'     => strtoupper($dados['matricula']),
            'posto'         => $eu->posto ?? null,
            'score'         => $veiculo?->score,
            'estado'        => $this->estadoDoScore($veiculo?->score),
            'origem'        => $dados['origem'] ?? 'manual',
            'consultado_em' => now(),
        ]);

        return response()->json([
            'consulta' => $this->formatar($consulta),
        ], 201);
    }

    /**
     * GET /api/agente/historico/xlsx
     */
    public function xlsx(Request $request)
    {
        $eu = auth()->user();

        $items = ConsultaAgente::where('agente_id', $eu->id)
            ->orderByDesc('consultado_em')
            ->limit(2000)
            ->get();

        $csv = "Matrícula,Posto,Score,Estado,Data,Origem\n";
        foreach ($items as $c) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $c->matricula,
                $c->posto ?? '—',
                $c->score ?? '—',
                $c->estado ?? '—',
                $c->consultado_em?->format('d/m/Y H:i') ?? '—',
                $c->origem ?? '—',
            );
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="historico-' . date('Y-m-d') . '.csv"',
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(ConsultaAgente $c): array
    {
        return [
            'id'        => (string) $c->id,
            'matricula' => $c->matricula,
            'posto'     => $c->posto ?? '—',
            'score'     => $c->score,
            'estado'    => $c->estado,
            'origem'    => $c->origem,
            'consultadoEm' => $c->consultado_em?->toIso8601String(),
            'hora'      => $c->consultado_em?->format('H:i') ?? '—',
            'data'      => $c->consultado_em?->format('d/m/Y') ?? '—',
            'dataHora'  => $c->consultado_em?->format('d/m/Y H:i') ?? '—',
            'diasAtras' => $c->consultado_em ? $c->consultado_em->diffInDays(now()) : 999,
        ];
    }

    private function estadoDoScore(?int $score): string
    {
        if ($score === null) return 'sem-dados';
        if ($score < 30) return 'critico';
        if ($score < 60) return 'atencao';
        return 'regular';
    }

    private function kpis(int $agenteId): array
    {
        $base = ConsultaAgente::where('agente_id', $agenteId);

        return [
            'total'     => (clone $base)->count(),
            'hoje'      => (clone $base)->where('consultado_em', '>=', Carbon::today())->count(),
            'criticos'  => (clone $base)->where('estado', 'critico')->count(),
            'atencao'   => (clone $base)->where('estado', 'atencao')->count(),
            'regulares' => (clone $base)->where('estado', 'regular')->count(),
        ];
    }
}