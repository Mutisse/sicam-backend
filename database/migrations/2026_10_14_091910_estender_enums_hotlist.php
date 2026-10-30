<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Estender `motivo` com os valores que o frontend espera
        DB::statement("
            ALTER TABLE hotlist
            MODIFY COLUMN motivo ENUM(
                'Furtado',
                'Clonagem suspeita',
                'Mandado captura',
                'Suspeito',
                'Outros',
                'Hotlist PRM',
                'Seguro expirado',
                'Inspecção em atraso',
                'Multas em atraso',
                'Documentação irregular',
                'Veículo roubado',
                'Suspeita de clonagem'
            ) NOT NULL
        ");

        // Estender `estado` com 'Em averiguação' e 'Arquivado'
        DB::statement("
            ALTER TABLE hotlist
            MODIFY COLUMN estado ENUM(
                'Activo',
                'Em averiguação',
                'Resolvido',
                'Arquivado'
            ) NOT NULL DEFAULT 'Activo'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE hotlist
            MODIFY COLUMN motivo ENUM(
                'Furtado',
                'Clonagem suspeita',
                'Mandado captura',
                'Suspeito',
                'Outros'
            ) NOT NULL
        ");

        DB::statement("
            ALTER TABLE hotlist
            MODIFY COLUMN estado ENUM(
                'Activo',
                'Resolvido'
            ) NOT NULL DEFAULT 'Activo'
        ");
    }
};