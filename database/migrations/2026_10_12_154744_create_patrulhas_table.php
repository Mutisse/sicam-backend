<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrulhas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // PT-001
            $table->enum('turno', ['Matinal', 'Tarde', 'Nocturna']);

            $table->foreignId('posto_id')
                ->constrained('postos')
                ->cascadeOnDelete();

            $table->foreignId('agente_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('viatura', 20)->nullable(); // MAP-2290
            $table->string('local')->nullable();       // Baixa, EN4...

            $table->enum('estado', ['Em curso', 'Concluída', 'Pendente'])
                ->default('Pendente');

            $table->timestamp('inicio')->nullable();
            $table->timestamp('fim')->nullable();

            $table->timestamps();

            $table->index('estado');
            $table->index('agente_id');
            $table->index('posto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrulhas');
    }
};