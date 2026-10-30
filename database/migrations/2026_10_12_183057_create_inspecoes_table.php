<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspecoes', function (Blueprint $table) {
            $table->id();

            // ⚠️ Sem constrained() — TiDB Cloud corta SSL em ALTER TABLE
            $table->unsignedBigInteger('veiculo_id');
            $table->unsignedBigInteger('posto_id')->nullable();

            $table->date('data');
            $table->date('validade')->nullable();       // próxima inspecção
            $table->enum('resultado', ['Aprovada', 'Reprovada', 'Pendente'])
                ->default('Pendente');
            $table->enum('estado', ['valida', 'expirada', 'pendente'])
                ->default('pendente');

            $table->text('observacoes')->nullable();
            $table->string('inspetor', 120)->nullable();

            $table->timestamps();

            // Índices em vez de FKs
            $table->index('veiculo_id');
            $table->index('posto_id');
            $table->index('estado');
            $table->index('data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspecoes');
    }
};