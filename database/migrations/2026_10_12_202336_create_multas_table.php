<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();  // ML-001

            $table->unsignedBigInteger('veiculo_id');
            $table->unsignedBigInteger('agente_id')->nullable();
            $table->unsignedBigInteger('posto_id')->nullable();

            $table->enum('infracao', [
                'Excesso velocidade',
                'Seguro expirado',
                'Inspecção atraso',
                'Documentos',
                'Outros',
            ]);

            $table->string('provincia', 80)->nullable();
            $table->decimal('valor', 12, 2);

            $table->enum('estado', ['Pendente', 'Paga', 'Contestada'])
                ->default('Pendente');

            $table->date('data');
            $table->date('data_pagamento')->nullable();
            $table->text('observacoes')->nullable();

            $table->timestamps();

            $table->index('veiculo_id');
            $table->index('agente_id');
            $table->index('infracao');
            $table->index('estado');
            $table->index('data');
            $table->index('provincia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multas');
    }
};