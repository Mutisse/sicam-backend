<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patrulhas', function (Blueprint $table) {
            // ⭐ Garante que o 'posto_id' é nullable (pode ser null se for uma patrulha geral)
            $table->unsignedBigInteger('posto_id')->nullable()->change();

            // ⭐ Garante que o 'agente_id' é obrigatório
            $table->unsignedBigInteger('agente_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('patrulhas', function (Blueprint $table) {
            $table->unsignedBigInteger('posto_id')->nullable(false)->change();
            $table->unsignedBigInteger('agente_id')->nullable(false)->change();
        });
    }
};