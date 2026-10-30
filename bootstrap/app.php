<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Não redirecionar para route('login') — devolver 401
        $middleware->redirectGuestsTo(fn () => null);

        // Sanctum SPA (frontend em localhost:9000)
        $middleware->statefulApi();

        // Middleware de monitorização (medir latência de todas as requests API)
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\MonitorLatency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Devolver JSON em vez de HTML para pedidos de API
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();