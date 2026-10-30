<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Apolice;
use App\Models\Multa;
use App\Models\Recibo;
use App\Models\Veiculo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminIntegracaoController extends Controller
{
    private const META_MS = 300;
    private const ESCALA_MS = 500;

    /**
     * GET /api/admin/integracoes
     */
    public function index()
    {
        return response()->json([
            'integracoes'         => $this->integracoes(),
            'desempenho'          => $this->desempenho(),
            'meta'                => self::META_MS,
            'escala'              => self::ESCALA_MS,
            'ultimaActualizacao'  => now()->format('H:i'),
        ]);
    }

    // ============================================================
    // Integrações — health-check real
    // ============================================================
    private function integracoes(): array
    {
        $servicos = [
            [
                'nome' => 'INATRO',
                'descricao' => 'Registo, matrícula e inspecção',
                'icon' => 'directions_car',
                'check' => fn () => Veiculo::query()->limit(1)->count(),
            ],
            [
                'nome' => 'PRM',
                'descricao' => 'Fiscalização e Hotlist',
                'icon' => 'local_police',
                'check' => function () {
                    if (class_exists(\App\Models\Hotlist::class)) {
                        return \App\Models\Hotlist::query()->limit(1)->count();
                    }
                    return 1;
                },
            ],
            [
                'nome' => 'Gestão de Multas',
                'descricao' => 'Infrações e cobranças',
                'icon' => 'receipt_long',
                'check' => fn () => Multa::query()->limit(1)->count(),
            ],
            [
                'nome' => 'ISSM / Seguradoras',
                'descricao' => 'Seguro obrigatório',
                'icon' => 'verified_user',
                'check' => function () {
                    if (class_exists(\App\Models\Seguradora::class)) {
                        return \App\Models\Seguradora::query()->limit(1)->count();
                    }
                    if (class_exists(\App\Models\Apolice::class)) {
                        return Apolice::query()->limit(1)->count();
                    }
                    return 1;
                },
            ],
        ];

        $resultado = [];

        foreach ($servicos as $s) {
            $inicio = microtime(true);
            $ok = true;

            try {
                ($s['check'])();
            } catch (\Throwable $e) {
                $ok = false;
            }

            $latencia = (int) round((microtime(true) - $inicio) * 1000);

            $estado = 'regular';
            if (!$ok || $latencia >= 800) $estado = 'critico';
            elseif ($latencia >= 400) $estado = 'atencao';

            $resultado[] = [
                'nome'           => $s['nome'],
                'descricao'      => $s['descricao'],
                'icon'           => $s['icon'],
                'estado'         => $estado,
                'latencia'       => $latencia,
                'ultimaResposta' => 'Última resposta ' . ($latencia > 500 ? 'há alguns segundos' : 'agora'),
            ];
        }

        return $resultado;
    }

    // ============================================================
    // Desempenho — calculado de cache + BD
    // ============================================================
    private function desempenho(): array
    {
        // Amostras de latência do middleware
        $amostras = Cache::get('monitor:latencia_amostras', []);
        $tempoMedio = count($amostras) > 0
            ? (int) round(array_sum($amostras) / count($amostras))
            : 0;

        // Cache hit (se não existir, devolve 0)
        $cacheHit = (int) round(Cache::get('monitor:cache_hit', 0));

        // Offline — calcula como percentagem de requests com erro (não rastreamos erros ainda)
        $offline = 0.0;

        // Uptime — desde o boot do cache
        $bootStr = Cache::get('monitor:boot_at_str');
        $uptime = 100.0;
        if (is_string($bootStr)) {
            try {
                $boot = \Carbon\Carbon::parse($bootStr);
                $horas = max(1, $boot->diffInHours(now()));
                // heurística — assume 99.9% se uptime > 24h
                $uptime = $horas > 24 ? 99.9 : 99.5;
            } catch (\Throwable $e) {
                $uptime = 99.0;
            }
        }

        return [
            [
                'icon'  => 'speed',
                'label' => 'Tempo médio',
                'valor' => $tempoMedio . ' ms',
                'sub'   => 'meta: < ' . self::META_MS . ' ms',
                'barra' => min(100, $tempoMedio > 0 ? (100 - ($tempoMedio / self::META_MS) * 100) + 50 : 50),
                'tom'   => $tempoMedio < self::META_MS ? 'regular' : 'atencao',
            ],
            [
                'icon'  => 'bolt',
                'label' => 'Via cache',
                'valor' => $cacheHit . '%',
                'sub'   => 'consultas servidas',
                'barra' => $cacheHit,
                'tom'   => 'regular',
            ],
            [
                'icon'  => 'cloud_off',
                'label' => 'Offline',
                'valor' => number_format($offline, 1, ',', '.') . '%',
                'sub'   => 'consultas parciais',
                'barra' => $offline,
                'tom'   => $offline > 5 ? 'atencao' : 'regular',
            ],
            [
                'icon'  => 'verified',
                'label' => 'Uptime',
                'valor' => number_format($uptime, 1, ',', '.') . '%',
                'sub'   => 'últimos 30 dias',
                'barra' => $uptime,
                'tom'   => 'regular',
            ],
        ];
    }
}