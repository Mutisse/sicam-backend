<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');           // Posto Baixa
            $table->string('codigo', 20)->unique(); // PS-BAIXA
            $table->string('provincia');      // Maputo Cidade
            $table->string('distrito')->nullable();
            $table->string('endereco')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->foreignId('responsavel_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('estado', ['Ativo', 'Inativo'])->default('Ativo');
            $table->timestamps();

            $table->index('provincia');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postos');
    }
};