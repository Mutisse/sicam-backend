<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aprovacoes', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 32)->unique()->nullable();

            $table->enum('tipo', ['auto', 'dados-sensiveis', 'apreensao']);
            $table->string('titulo', 160);
            $table->string('matricula', 20)->nullable();
            $table->string('agente', 120);
            $table->string('posto', 120);

            $table->enum('prioridade', ['Baixa', 'Média', 'Alta', 'Urgente'])
                ->default('Média');

            $table->enum('estado', ['pendente', 'aprovado', 'rejeitado'])
                ->default('pendente');

            $table->json('detalhes')->nullable();

            $table->text('motivo_rejeicao')->nullable();
            $table->string('decidido_por', 120)->nullable();
            $table->timestamp('decidido_em')->nullable();

            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('tipo');
            $table->index('estado');
            $table->index('prioridade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aprovacoes');
    }
};