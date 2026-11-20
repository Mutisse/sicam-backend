<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            // Só adiciona 'categoria' se ela ainda não existir
            if (!Schema::hasColumn('notificacoes', 'categoria')) {
                $table->string('categoria')->nullable()->after('tipo');
            }

            // Só adiciona 'referencia' se ela ainda não existir
            if (!Schema::hasColumn('notificacoes', 'referencia')) {
                $table->string('referencia')->nullable()->after('categoria');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            if (Schema::hasColumn('notificacoes', 'categoria')) {
                $table->dropColumn('categoria');
            }
            if (Schema::hasColumn('notificacoes', 'referencia')) {
                $table->dropColumn('referencia');
            }
        });
    }
};