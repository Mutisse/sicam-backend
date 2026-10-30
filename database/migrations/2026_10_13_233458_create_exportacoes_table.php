<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exportacoes', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 32)->unique()->nullable();

            // Tipo e formato
            $table->string('tipo', 40);              // veiculos | inspecoes | agendamentos | completo
            $table->string('formato', 20);           // csv | json | xlsx | pdf

            // Título/detalhe legível (o que aparece na lista)
            $table->string('titulo');
            $table->string('detalhe')->nullable();

            // Período aplicado
            $table->date('data_de')->nullable();
            $table->date('data_ate')->nullable();

            // Opções usadas
            $table->boolean('incluir_cabecalho')->default(true);
            $table->boolean('incluir_resumo')->default(true);
            $table->boolean('apenas_ativos')->default(false);

            // Estatísticas
            $table->unsignedInteger('total_registos')->default(0);
            $table->unsignedBigInteger('tamanho_bytes')->default(0);

            // Meta
            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('tipo');
            $table->index('formato');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exportacoes');
    }
};