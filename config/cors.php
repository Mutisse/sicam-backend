<?php
// config/cors.php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        //'http://localhost:9000',
        //'http://127.0.0.1:9000',
        'https://sicam-sti.onrender.com',   // ← frontend no Render
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,   // ⭐ OBRIGATÓRIO para cookies
];