<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agente_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('posto', 120);
            $table->string('semana', 60); // "dd/mm/aaaa — dd/mm/aaaa"
            $table->enum('estado', ['Rascunho', 'Publicada'])->default('Rascunho');

            // Grelha semanal — 7 dias, cada com {dia, ativo, turno}
            $table->json('atribuicoes')->nullable();

            $table->text('notas')->nullable();

            // Quem criou
            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('agente_id');
            $table->index('estado');
            $table->index('semana');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalas');
    }
};