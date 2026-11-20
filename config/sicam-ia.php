<?php
// config/sicam-ia.php

return [
    /*
    |--------------------------------------------------------------------------
    | URL do Microserviço de IA
    |--------------------------------------------------------------------------
    |
    | Endereço onde o cérebro Python está a correr.
    | Em produção (Render), substitui pelo URL público.
    |
    */
    'url' => env('SICAM_IA_URL', 'http://127.0.0.1:8001'),

    /*
    |--------------------------------------------------------------------------
    | Timeout (segundos)
    |--------------------------------------------------------------------------
    |
    | Tempo máximo de espera pela resposta da IA.
    | O Gemini pode demorar 5-10 segundos.
    |
    */
    'timeout' => env('SICAM_IA_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'analisar' => '/api/analisar-veiculo',
        'chat'     => '/api/chat-agente',
    ],
];