<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            // Só adiciona se ainda não existir (evita erro "Duplicate column")

            if (!Schema::hasColumn('veiculos', 'tipo')) {
                $table->string('tipo', 40)->nullable()->after('matricula');
            }

            if (!Schema::hasColumn('veiculos', 'chassis')) {
                $table->string('chassis', 30)->nullable()->after('cor');
            }

            if (!Schema::hasColumn('veiculos', 'combustivel')) {
                $table->string('combustivel', 30)->nullable()->after('chassis');
            }
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'chassis', 'combustivel']);
        });
    }
};