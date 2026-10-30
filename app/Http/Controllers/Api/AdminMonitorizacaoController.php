<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Models\Contestacao;
use App\Models\Multa;
use App\Models\Recibo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminMonitorizacaoController extends Controller
{
    /**
     * GET /api/admin/monitorizacao
     */
    public function index()
    {
        $recursos     = $this->recursos();
        $integracoes  = $this->integracoes();
        $metricas     = $this->metricas();
        $alertas      = $this->alertas();

        return response()->json([
            'recursos'    => $recursos,
            'integracoes' => $integracoes,
            'metricas'    => $metricas,
            'alertas'     => $alertas,
            'estadoGeral' => $this->estadoGeral($recursos, $integracoes, $alertas),
            'actualizadoEm' => now()->format('H:i:s'),
        ]);
    }

    /**
     * PUT /api/admin/monitorizacao/alertas/{id}/silenciar
     */
    public function silenciarAlerta(int $id)
    {
        $a = Alerta::findOrFail($id);
        $a->update([
            'lido'    => true,
            'lido_em' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => (string) $a->id]);
    }

    /**
     * DELETE /api/admin/monitorizacao/alertas/silenciados
     */
    public function limparSilenciados()
    {
        Alerta::where('lido', true)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/admin/monitorizacao/integracoes/{nome}/reiniciar
     */
    public function reiniciarIntegracao(string $nome)
    {
        // Limpa a cache de health-check deste serviço
        Cache::forget("monitor:ping:{$nome}");
        Cache::forget("monitor:latencia:{$nome}");

        return response()->json(['ok' => true, 'nome' => $nome]);
    }

    // ============================================================
    // RECURSOS REAIS DO SISTEMA
    // ============================================================

    private function recursos(): array
    {
        return [
            'cpu'      => $this->cpu(),
            'ram'      => $this->ram(),
            'disco'    => $this->disco(),
            'latencia' => $this->latencia(),
        ];
    }

    private function cpu(): array
    {
        $nivel = 'regular';

        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $load1 = $load[0] ?? 0;

            // assume 1 core (ajusta se souberes o nº de cores)
            $cores = 1;
            if (PHP_OS_FAMILY === 'Windows') {
                $cores = (int) (shell_exec('echo %NUMBER_OF_PROCESSORS%') ?? 1);
            } else {
                $cores = (int) trim(shell_exec('nproc') ?? 1);
            }
            $cores = max(1, $cores);

            $percent = (int) round(($load1 / $cores) * 100);

            if ($percent >= 90) $nivel = 'critico';
            elseif ($percent >= 70) $nivel = 'atencao';

            return [
                'key'           => 'cpu',
                'label'         => 'CPU',
                'icon'          => 'memory',
                'valor'         => $percent,
                'unidade'       => '%',
                'percentagem'   => $percent,
                'nivel'         => $nivel,
                'detalhe'       => "{$cores} vCPU · load avg {$load1}",
                'limiarAtencao' => 70,
                'limiarCritico' => 90,
                'historico'     => $this->historico('cpu', $percent),
            ];
        }

        // fallback — Windows sem sys_getloadavg
        return [
            'key'           => 'cpu',
            'label'         => 'CPU',
            'icon'          => 'memory',
            'valor'         => 0,
            'unidade'       => '%',
            'percentagem'   => 0,
            'nivel'         => 'regular',
            'detalhe'       => 'Indisponível em ' . PHP_OS,
            'limiarAtencao' => 70,
            'limiarCritico' => 90,
            'historico'     => $this->historico('cpu', 0),
        ];
    }

    private function ram(): array
    {
        $nivel = 'regular';

        // PHP memory_get_usage devolve memória DO PROCESSO, não do sistema todo
        // Para RAM do sistema, usa OS-specific:
        $usadoMb = 0;
        $totalMb = 0;

        if (PHP_OS_FAMILY === 'Windows') {
            $out = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>NUL');
            if ($out) {
                if (preg_match('/FreePhysicalMemory=(\d+)/', $out, $m)) {
                    $livreKb = (int) $m[1];
                }
                if (preg_match('/TotalVisibleMemorySize=(\d+)/', $out, $m)) {
                    $totalKb = (int) $m[1];
                }
                if (isset($livreKb, $totalKb) && $totalKb > 0) {
                    $usadoMb = (int) round(($totalKb - $livreKb) / 1024);
                    $totalMb = (int) round($totalKb / 1024);
                }
            }
        } else {
            $out = @file_get_contents('/proc/meminfo');
            if ($out) {
                if (preg_match('/MemTotal:\s+(\d+)/', $out, $m))   $totalKb = (int) $m[1];
                if (preg_match('/MemAvailable:\s+(\d+)/', $out, $m)) $livreKb = (int) $m[1];
                if (isset($livreKb, $totalKb) && $totalKb > 0) {
                    $usadoMb = (int) round(($totalKb - $livreKb) / 1024);
                    $totalMb = (int) round($totalKb / 1024);
                }
            }
        }

        $percent = $totalMb > 0 ? (int) round(($usadoMb / $totalMb) * 100) : 0;

        if ($percent >= 90) $nivel = 'critico';
        elseif ($percent >= 75) $nivel = 'atencao';

        return [
            'key'           => 'ram',
            'label'         => 'Memória',
            'icon'          => 'developer_board',
            'valor'         => $percent,
            'unidade'       => '%',
            'percentagem'   => $percent,
            'nivel'         => $nivel,
            'detalhe'       => $totalMb > 0 ? "{$usadoMb} MB de {$totalMb} MB" : 'Indisponível',
            'limiarAtencao' => 75,
            'limiarCritico' => 90,
            'historico'     => $this->historico('ram', $percent),
        ];
    }

    private function disco(): array
    {
        $nivel = 'regular';

        $total = @disk_total_space('/');
        $livre = @disk_free_space('/');

        // Windows: usa a drive onde está o projecto
        if ((!$total || !$livre) && PHP_OS_FAMILY === 'Windows') {
            $total = @disk_total_space('C:');
            $livre = @disk_free_space('C:');
        }

        $percent = 0;
        $usadoGb = 0;
        $totalGb = 0;

        if ($total > 0 && $livre !== false) {
            $usadoGb = round(($total - $livre) / 1024 ** 3);
            $totalGb = round($total / 1024 ** 3);
            $percent = (int) round((($total - $livre) / $total) * 100);
        }

        if ($percent >= 90) $nivel = 'critico';
        elseif ($percent >= 75) $nivel = 'atencao';

        return [
            'key'           => 'disco',
            'label'         => 'Disco',
            'icon'          => 'storage',
            'valor'         => $percent,
            'unidade'       => '%',
            'percentagem'   => $percent,
            'nivel'         => $nivel,
            'detalhe'       => $totalGb > 0 ? "{$usadoGb} GB de {$totalGb} GB" : 'Indisponível',
            'limiarAtencao' => 75,
            'limiarCritico' => 90,
            'historico'     => $this->historico('disco', $percent),
        ];
    }

    private function latencia(): array
    {
        // Latência = média do tempo de resposta das últimas X requests
        // Guardamos em cache no middleware (ver nota abaixo)
        $amostras = Cache::get('monitor:latencia_amostras', []);
        $media = count($amostras) > 0 ? (int) round(array_sum($amostras) / count($amostras)) : 0;

        $nivel = 'regular';
        // limiares em ms
        if ($media >= 800) $nivel = 'critico';
        elseif ($media >= 400) $nivel = 'atencao';

        $percent = min(100, (int) round($media / 10)); // escala ms→% (1000ms = 100%)

        return [
            'key'           => 'latencia',
            'label'         => 'Latência',
            'icon'          => 'speed',
            'valor'         => $media,
            'unidade'       => 'ms',
            'percentagem'   => $percent,
            'nivel'         => $nivel,
            'detalhe'       => 'meta: < 300 ms',
            'limiarAtencao' => 400,
            'limiarCritico' => 800,
            'historico'     => $this->historico('latencia', $media),
        ];
    }

    // Guarda histórico (últimos 10 valores) em cache
    private function historico(string $key, int $valor): array
    {
        $cacheKey = "monitor:hist:{$key}";
        $hist = Cache::get($cacheKey, []);

        $hist[] = $valor;
        if (count($hist) > 10) {
            $hist = array_slice($hist, -10);
        }
        Cache::put($cacheKey, $hist, 3600);

        return $hist;
    }

    // ============================================================
    // INTEGRAÇÕES — health-check real
    // ============================================================

    private function integracoes(): array
    {
        $servicos = [
            ['nome' => 'INATRO',             'descricao' => 'Registo, matrícula e inspecção', 'icon' => 'directions_car', 'rota' => '/api/dashboard/inatro'],
            ['nome' => 'PRM',                'descricao' => 'Fiscalização e Hotlist',          'icon' => 'local_police',   'rota' => '/api/dashboard/prm'],
            ['nome' => 'Gestão de Multas',   'descricao' => 'Infrações e cobranças',           'icon' => 'receipt_long',   'rota' => '/api/gestmul/multas'],
            ['nome' => 'ISSM / Seguradoras', 'descricao' => 'Seguro obrigatório',              'icon' => 'verified_user',  'rota' => '/api/relatorios/issm'],
        ];

        // Filtra serviços internos cujo controller/módulo pode não existir
        // Neste caso, em vez de fazer ping HTTP (bloqueante), usamos a BD como "proxy de saúde"
        $resultado = [];

        foreach ($servicos as $s) {
            $cacheKey = "monitor:ping:{$s['nome']}";
            $inicio = microtime(true);

            $ok = true;
            $latencia = 0;

            try {
                // Verifica se o recurso responde consultando BD do módulo
                switch ($s['nome']) {
                    case 'INATRO':
                        \App\Models\Veiculo::query()->limit(1)->count();
                        break;
                    case 'PRM':
                        // Se existir modelo Hotlist — senão considera OK
                        if (class_exists(\App\Models\Hotlist::class)) {
                            \App\Models\Hotlist::query()->limit(1)->count();
                        }
                        break;
                    case 'Gestão de Multas':
                        Multa::query()->limit(1)->count();
                        break;
                    case 'ISSM / Seguradoras':
                        if (class_exists(\App\Models\Seguradora::class)) {
                            \App\Models\Seguradora::query()->limit(1)->count();
                        } elseif (class_exists(\App\Models\Apolice::class)) {
                            \App\Models\Apolice::query()->limit(1)->count();
                        }
                        break;
                }
                $latencia = (int) round((microtime(true) - $inicio) * 1000);
            } catch (\Throwable $e) {
                $ok = false;
                $latencia = 0;
            }

            $estado = 'regular';
            if (!$ok || $latencia >= 800) $estado = 'critico';
            elseif ($latencia >= 400) $estado = 'atencao';

            $resultado[] = [
                'nome'           => $s['nome'],
                'descricao'      => $s['descricao'],
                'icon'           => $s['icon'],
                'estado'         => $estado,
                'latencia'       => $latencia,
                'ultimaResposta' => 'agora',
                'paradoDesde'    => null,
            ];
        }

        return $resultado;
    }

    // ============================================================
    // MÉTRICAS DE ACTIVIDADE
    // ============================================================

    private function metricas(): array
{
    // Guardar como STRING (não como objeto Carbon) para evitar __PHP_Incomplete_Class
    $inicioStr = Cache::get('monitor:boot_at_str');

    if (!$inicioStr || !is_string($inicioStr)) {
        $inicioStr = now()->toIso8601String();
        Cache::put('monitor:boot_at_str', $inicioStr, 86400);
    }

    try {
        $inicio = Carbon::parse($inicioStr);
    } catch (\Throwable $e) {
        // fallback — se algo falhar, usa agora
        $inicio = now();
        Cache::put('monitor:boot_at_str', $inicio->toIso8601String(), 86400);
    }

    $seg   = max(0, $inicio->diffInSeconds(now(), false) * -1);
    $dias  = floor($seg / 86400);
    $horas = floor(($seg % 86400) / 3600);
    $min   = floor(($seg % 3600) / 60);

    return [
        'uptime'       => "{$dias}d {$horas}h {$min}m",
        'uptimeDesde'  => $inicio->format('d/m H:i'),
        'pedidosMin'   => (int) Cache::get('monitor:req_last_min', 0),
        'picoPedidos'  => (int) Cache::get('monitor:req_peak', 0),
        'tempoMedio'   => $this->tempoMedio(),
        'cacheHit'     => (int) round(Cache::get('monitor:cache_hit', 0)),
    ];
}

    private function tempoMedio(): int
    {
        $amostras = Cache::get('monitor:latencia_amostras', []);
        if (count($amostras) === 0) return 0;
        return (int) round(array_sum($amostras) / count($amostras));
    }

    // ============================================================
    // ALERTAS — tabela `alertas` real
    // ============================================================

    private function alertas(): array
    {
        return Alerta::query()
            ->where('lido', false)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($a) {
                return [
                    'id'         => (string) $a->id,
                    'nivel'      => $this->mapearNivel($a->nivel),
                    'icon'       => $this->iconeAlerta($a->tipo),
                    'titulo'     => $a->titulo,
                    'descricao'  => $a->mensagem,
                    'hora'       => $a->created_at?->diffForHumans() ?? '—',
                    'silenciado' => (bool) $a->lido,
                    'origem'     => $a->origem,
                    'referencia' => $a->referencia,
                ];
            })
            ->toArray();
    }

    private function mapearNivel(string $nivel): string
    {
        // A BD pode ter 'critico' | 'atencao' | 'info'
        if (in_array($nivel, ['critico', 'atencao', 'info'])) return $nivel;
        if ($nivel === 'warning') return 'atencao';
        if ($nivel === 'error') return 'critico';
        return 'info';
    }

    private function iconeAlerta(string $tipo): string
    {
        return match ($tipo) {
            'hotlist'   => 'warning',
            'clonagem'  => 'content_copy',
            'sinistro'  => 'report_problem',
            'multa'     => 'receipt_long',
            'infracao'  => 'gavel',
            default     => 'info',
        };
    }

    // ============================================================
    // ESTADO GERAL
    // ============================================================

    private function estadoGeral(array $recursos, array $integracoes, array $alertas): string
    {
        $temCritico = false;
        $temAtencao = false;

        foreach ($recursos as $r) {
            if ($r['nivel'] === 'critico') $temCritico = true;
            if ($r['nivel'] === 'atencao') $temAtencao = true;
        }

        foreach ($integracoes as $i) {
            if ($i['estado'] === 'critico') $temCritico = true;
            if ($i['estado'] === 'atencao') $temAtencao = true;
        }

        foreach ($alertas as $a) {
            if ($a['nivel'] === 'critico' && !$a['silenciado']) $temCritico = true;
            if ($a['nivel'] === 'atencao' && !$a['silenciado']) $temAtencao = true;
        }

        if ($temCritico) return 'critico';
        if ($temAtencao) return 'atencao';
        return 'regular';
    }
}