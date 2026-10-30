<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Multa;
use App\Models\User;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AgenteInicioController extends Controller
{
    /**
     * GET /api/agente/inicio
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        return response()->json([
            'kpis'          => $this->kpis($user),
            'veiculos'      => $this->ultimasConsultas($user),
            'requerAtencao' => $this->requerAtencao($user),
            'patrulha'      => $this->patrulhaEmCurso($user),
            'dataHora'      => now()->format('d/m H:i'),
        ]);
    }

    /**
     * POST /api/agente/consultas/registar
     * Body: { matricula, posto?, score? }
     */
    public function registarConsulta(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $dados = $request->validate([
            'matricula' => 'required|string|max:20',
            'posto'     => 'nullable|string|max:120',
            'score'     => 'nullable|integer|min:0|max:100',
        ]);

        $matricula = strtoupper(trim($dados['matricula']));
        $hoje = now()->toDateString();

        // 1. Incrementa contador diário
        $chaveContador = "agente:{$user->id}:consultas:{$hoje}";
        $contador = (int) Cache::get($chaveContador, 0) + 1;
        Cache::put($chaveContador, $contador, now()->endOfDay());

        // 2. Guarda no histórico (últimas 10)
        $chaveHistorico = "agente:{$user->id}:ultimas_consultas";
        $historico = Cache::get($chaveHistorico, []);

        $veiculo = Veiculo::where('matricula', $matricula)->first();

        $historico = array_values(array_filter(
            $historico,
            fn ($h) => ($h['matricula'] ?? '') !== $matricula,
        ));

        array_unshift($historico, [
            'matricula' => $matricula,
            'posto'     => $dados['posto'] ?? ($veiculo?->provincia ?? '—'),
            'ultima'    => now()->format('d/m H:i'),
            'timestamp' => now()->timestamp,
            'score'     => $dados['score'] ?? 0,
        ]);

        $historico = array_slice($historico, 0, 10);
        Cache::put($chaveHistorico, $historico, now()->addDays(7));

        return response()->json([
            'ok'        => true,
            'matricula' => $matricula,
            'contador'  => $contador,
        ]);
    }

    // ============================================================
    // Helpers — SÓ DADOS REAIS DO UTILIZADOR
    // ============================================================

    private function kpis(User $user): array
    {
        $hoje = now()->toDateString();

        // Consultas — do cache (incrementado por registarConsulta)
        $consultas = (int) Cache::get(
            "agente:{$user->id}:consultas:{$hoje}",
            0,
        );

        // Críticos — hotlist activa (global, é o universo real)
        $criticos = DB::table('hotlist')
            ->where('estado', 'Activo')
            ->count();

        // Autos — multas do agente hoje
        $autos = Multa::query()
            ->where('agente_id', $user->id)
            ->whereDate('created_at', $hoje)
            ->count();

        return [
            'consultas' => $consultas,
            'criticos'  => $criticos,
            'autos'     => $autos,
        ];
    }

    private function ultimasConsultas(User $user): array
    {
        $historico = Cache::get(
            "agente:{$user->id}:ultimas_consultas",
            [],
        );

        return array_map(function ($h) {
            $ts = $h['timestamp'] ?? now()->timestamp;
            $tempo = Carbon::createFromTimestamp($ts)->diffForHumans(null, true);

            return [
                'matricula' => $h['matricula'] ?? '—',
                'posto'     => $h['posto'] ?? '—',
                'ultima'    => $h['ultima'] ?? '—',
                'tempo'     => $tempo,
                'score'     => (int) ($h['score'] ?? 0),
            ];
        }, $historico);
    }

    private function requerAtencao(User $user): array
    {
        // Hotlist activa do POSTO do utilizador
        $posto = $user->posto;

        $query = DB::table('hotlist')
            ->leftJoin('veiculos', 'veiculos.id', '=', 'hotlist.veiculo_id')
            ->select(
                'hotlist.id',
                'hotlist.motivo',
                'hotlist.perigo',
                'hotlist.prioridade',
                'hotlist.posto',
                'hotlist.zona',
                'hotlist.detectado_em',
                'veiculos.matricula',
            )
            ->where('hotlist.estado', 'Activo');

        // Filtra pelo posto do utilizador, se tiver
        if ($posto) {
            $query->where('hotlist.posto', $posto);
        }

        $linhas = $query
            ->orderByRaw("FIELD(prioridade, 'Urgente', 'Alta', 'Média', 'Baixa')")
            ->limit(10)
            ->get();

        return $linhas->map(function ($l) {
            $score = match ($l->prioridade) {
                'Urgente' => 90,
                'Alta'    => 70,
                'Média'   => 50,
                'Baixa'   => 30,
                default   => 50,
            };

            return [
                'matricula' => $l->matricula ?? '—',
                'posto'     => $l->posto ?? $l->zona ?? '—',
                'ultima'    => $l->detectado_em
                    ? Carbon::parse($l->detectado_em)->format('d/m H:i')
                    : '—',
                'tempo'     => $l->detectado_em
                    ? Carbon::parse($l->detectado_em)->diffForHumans(null, true)
                    : '—',
                'score'     => $score,
                'motivo'    => $l->motivo,
                'perigo'    => $l->perigo,
            ];
        })->toArray();
    }

    private function patrulhaEmCurso(User $user): ?array
    {
        // Só a patrulha do próprio utilizador, em curso
        $p = DB::table('patrulhas')
            ->where('agente_id', $user->id)
            ->where('estado', 'Em curso')
            ->orderByDesc('inicio')
            ->first();

        if (!$p) {
            return null;
        }

        $inicio = $p->inicio ? Carbon::parse($p->inicio) : null;
        $tempoPatrulha = $inicio
            ? $this->formatarDuracao($inicio->diffInMinutes(now()))
            : '—';

        $nome = $p->nome ?? 'Patrulha';
        if ($p->viatura) {
            $nome .= " · {$p->viatura}";
        }

        return [
            'codigo'    => $p->codigo,
            'nome'      => $nome,
            'total'     => (int) ($p->pontos_previstos ?? 0),
            'progresso' => (int) ($p->pontos_concluidos ?? 0),
            'tempo'     => $tempoPatrulha,
        ];
    }

    private function formatarDuracao(int $minutos): string
    {
        if ($minutos < 60) return "{$minutos} min";
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;
        return "{$h} h {$m} min";
    }
}