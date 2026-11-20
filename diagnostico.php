<?php
// diagnostico.php

echo "=== INÍCIO DO DIAGNÓSTICO ===\n\n";

// 1. Verificar se o autoload do Composer funciona
echo "1. A carregar o autoload do Composer...\n";
require __DIR__.'/vendor/autoload.php';
echo "   ✅ Autoload carregado.\n\n";

// 2. Verificar se os ficheiros base do Laravel existem
echo "2. A verificar ficheiros do Laravel...\n";
$ficheiros = [
    'bootstrap/app.php',
    'config/app.php',
    'routes/api.php',
    'routes/channels.php',
];
foreach ($ficheiros as $f) {
    if (file_exists(__DIR__.'/'.$f)) {
        echo "   ✅ {$f} existe.\n";
    } else {
        echo "   ❌ {$f} NÃO EXISTE!\n";
    }
}
echo "\n";

// 3. Tentar criar a aplicação Laravel
echo "3. A tentar arrancar a aplicação Laravel...\n";
try {
    $app = require_once __DIR__.'/bootstrap/app.php';
    echo "   ✅ Aplicação criada com sucesso.\n\n";

    // 4. Tentar arrancar o Kernel (é aqui que o ExceptionHandler costuma falhar)
    echo "4. A tentar arrancar o Kernel...\n";
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    echo "   ✅ Kernel criado.\n";
    
    $kernel->bootstrap();
    echo "   ✅ Kernel arrancado com sucesso!\n\n";

    echo "=== TUDO FUNCIONA! O problema deve estar na cache do Laravel. ===\n";
    echo "Corre: php artisan optimize:clear\n";

} catch (\Throwable $e) {
    echo "\n   ❌ ERRO DETECTADO!\n";
    echo "   Mensagem: " . $e->getMessage() . "\n";
    echo "   Ficheiro: " . $e->getFile() . " (linha " . $e->getLine() . ")\n\n";
    
    echo "=== STACK TRACE ===\n";
    echo $e->getTraceAsString() . "\n";
}