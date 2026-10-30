<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas_agente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agente_id')->constrained('users');
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->string('matricula', 20)->index(); // denormalizado p/ rapidez
            $table->string('posto', 120)->nullable();
            $table->integer('score')->nullable();     // score no momento da consulta
            $table->string('estado', 20)->nullable(); // critico | atencao | regular | sem-dados
            $table->string('origem', 20)->default('manual'); // manual | camera | historico
            $table->timestamp('consultado_em')->useCurrent();
            $table->timestamps();

            $table->index(['agente_id', 'consultado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas_agente');
    }
};