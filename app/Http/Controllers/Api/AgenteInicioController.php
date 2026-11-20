<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Escala;
use App\Models\Multa;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\SicamIAService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AgenteInicioController extends Controller
{
    /**
     * GET /api/agente/inicio
     */
    public function index(Request $request, SicamIAService $ia)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $veiculos = $this->ultimasConsultas($user);
        $analiseIA = null;

        // ⭐ INTEGRAÇÃO IA: Se houver última consulta, pede a análise
        if (!empty($veiculos) && isset($veiculos[0]['matricula'])) {
            $ultimaMatricula = $veiculos[0]['matricula'];
            $veiculo = Veiculo::where('matricula', $ultimaMatricula)->first();

            if ($veiculo) {
                $multas = DB::table('multas')->where('veiculo_id', $veiculo->id)->where('estado', 'Pendente');
                $apolice = DB::table('apolices')->where('veiculo_id', $veiculo->id)->orderByDesc('validade')->first();
                $inspecao = DB::table('inspecoes')->where('veiculo_id', $veiculo->id)->orderByDesc('data')->first();
                $hotlist = DB::table('hotlist')->where('veiculo_id', $veiculo->id)->where('estado', 'ativo')->first();
                $provincias = DB::table('multas')->where('veiculo_id', $veiculo->id)->whereNotNull('provincia')->pluck('provincia')->unique()->toArray();

                try {
                    $analiseIA = $ia->analisarVeiculo([
                        'matricula'         => $veiculo->matricula,
                        'marca'             => $veiculo->marca,
                        'modelo'            => $veiculo->modelo,
                        'score'             => (int) $veiculo->score,
                        'estado'            => $veiculo->estado,
                        'multas_pendentes'  => $multas->count(),
                        'valor_divida'      => (float) $multas->sum('valor'),
                        'seguro_valido'     => $apolice && Carbon::parse($apolice->validade)->isFuture(),
                        'inspecao_valida'   => $inspecao && Carbon::parse($inspecao->data)->isFuture(),
                        'na_hotlist'        => $hotlist !== null,
                        'motivo_hotlist'    => $hotlist?->motivo,
                        'provincias_vistas' => $provincias,
                    ]);
                } catch (\Throwable $e) {
                    $analiseIA = null;
                }
            }
        }

        return response()->json([
            'kpis'          => $this->kpis($user),
            'veiculos'      => $veiculos,
            'requerAtencao' => $this->requerAtencao($user),
            'patrulha'      => $this->patrulhaEmCurso($user),
            'escala'        => $this->escalaAtual($user), // ⭐ NOVO
            'dataHora'      => now()->format('d/m H:i'),
            'analiseIA'     => $analiseIA,
        ]);
    }

    /**
     * POST /api/agente/consultas/registar
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

        $chaveContador = "agente:{$user->id}:consultas:{$hoje}";
        $contador = (int) Cache::get($chaveContador, 0) + 1;
        Cache::put($chaveContador, $contador, now()->endOfDay());

        $chaveHistorico = "agente:{$user->id}:ultimas_consultas";
        $historico = Cache::get($chaveHistorico, []);

        $veiculo = Veiculo::where('matricula', $matricula)->first();

        $historico = array_values(array_filter(
            $historico,
            fn($h) => ($h['matricula'] ?? '') !== $matricula,
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
    // Helpers
    // ============================================================

    private function kpis(User $user): array
    {
        $hoje = now()->toDateString();
        $consultas = (int) Cache::get("agente:{$user->id}:consultas:{$hoje}", 0);
        $criticos = DB::table('hotlist')->where('estado', 'Activo')->count();
        $autos = Multa::query()->where('agente_id', $user->id)->whereDate('created_at', $hoje)->count();

        return [
            'consultas' => $consultas,
            'criticos'  => $criticos,
            'autos'     => $autos,
        ];
    }

    private function ultimasConsultas(User $user): array
    {
        $historico = Cache::get("agente:{$user->id}:ultimas_consultas", []);

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
        $posto = $user->posto;
        $query = DB::table('hotlist')
            ->leftJoin('veiculos', 'veiculos.id', '=', 'hotlist.veiculo_id')
            ->select('hotlist.id', 'hotlist.motivo', 'hotlist.perigo', 'hotlist.prioridade', 'hotlist.posto', 'hotlist.zona', 'hotlist.detectado_em', 'veiculos.matricula')
            ->where('hotlist.estado', 'Activo');

        if ($posto) {
            $query->where('hotlist.posto', $posto);
        }

        $linhas = $query->orderByRaw("FIELD(prioridade, 'Urgente', 'Alta', 'Média', 'Baixa')")->limit(10)->get();

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
                'ultima'    => $l->detectado_em ? Carbon::parse($l->detectado_em)->format('d/m H:i') : '—',
                'tempo'     => $l->detectado_em ? Carbon::parse($l->detectado_em)->diffForHumans(null, true) : '—',
                'score'     => $score,
                'motivo'    => $l->motivo,
                'perigo'    => $l->perigo,
            ];
        })->toArray();
    }

    private function patrulhaEmCurso(User $user): ?array
    {
        $p = DB::table('patrulhas')->where('agente_id', $user->id)->where('estado', 'Em curso')->orderByDesc('inicio')->first();
        if (!$p) return null;

        $inicio = $p->inicio ? Carbon::parse($p->inicio) : null;
        $tempoPatrulha = $inicio ? $this->formatarDuracao($inicio->diffInMinutes(now())) : '—';
        $nome = $p->nome ?? 'Patrulha';
        if ($p->viatura) $nome .= " · {$p->viatura}";

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

    /**
     * ⭐ NOVO: Devolve a escala da semana atual do agente
     */
    /**
     * ⭐ Devolve a escala da semana atual do agente
     */
    private function escalaAtual(User $user): ?array
    {
        try {
            // Procura a escala mais recente do agente
            $escala = Escala::where('agente_id', $user->id)
                ->orderByDesc('created_at')
                ->first();

            if (!$escala) return null;

            // Trata o campo atribuicoes (pode vir como string JSON ou já como array)
            $atribuicoes = $escala->atribuicoes;
            if (is_string($atribuicoes)) {
                $atribuicoes = json_decode($atribuicoes, true);
            }

            if (!is_array($atribuicoes)) return null;

            // Descobre o dia da semana atual (0=Domingo, 1=Segunda, ... 6=Sábado)
            $diaSemana = now()->dayOfWeek;

            $mapaDias = [
                1 => 'Seg',
                2 => 'Ter',
                3 => 'Qua',
                4 => 'Qui',
                5 => 'Sex',
                6 => 'Sáb',
                0 => 'Dom',
            ];

            $diaHoje = $mapaDias[$diaSemana] ?? 'Seg';

            // Encontra a atribuição de hoje
            $hoje = collect($atribuicoes)->firstWhere('dia', $diaHoje);

            return [
                'semana'      => $escala->semana,
                'posto'       => $escala->posto,
                'estado'      => $escala->estado,
                'dia_hoje'    => $diaHoje,
                'ativo_hoje'  => $hoje['ativo'] ?? false,
                'turno_hoje'  => $hoje['turno'] ?? null,
                'atribuicoes' => $atribuicoes,
            ];
        } catch (\Throwable $e) {
            // Se falhar (ex: coluna não existe, erro no cast), regista o erro mas não bloqueia a app
            \Illuminate\Support\Facades\Log::error('[SICAM-AGENTE] Falha ao carregar escala', [
                'user_id' => $user->id,
                'erro' => $e->getMessage()
            ]);
            return null;
        }
    }
}
