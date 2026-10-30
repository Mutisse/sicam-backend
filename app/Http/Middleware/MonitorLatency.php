<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MonitorLatency
{
    public function handle(Request $request, Closure $next)
    {
        $inicio = microtime(true);

        $response = $next($request);

        $ms = (int) round((microtime(true) - $inicio) * 1000);

        // Amostra das últimas 100 requests
        $amostras = Cache::get('monitor:latencia_amostras', []);
        $amostras[] = $ms;
        if (count($amostras) > 100) {
            $amostras = array_slice($amostras, -100);
        }
        Cache::put('monitor:latencia_amostras', $amostras, 3600);

        // Contador de pedidos/min
        Cache::increment('monitor:req_last_min', 1, 60);

        return $response;
    }
}