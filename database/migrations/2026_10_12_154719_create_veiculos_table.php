<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos', function (Blueprint $table) {
            $table->id();
            $table->string('matricula', 20)->unique(); // AGL-482-MC
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->year('ano')->nullable();
            $table->string('cor', 30)->nullable();

            // Proprietário
            $table->string('proprietario_nome')->nullable();
            $table->string('proprietario_numero_id', 40)->nullable();

            // Localização
            $table->foreignId('posto_id')
                ->nullable()
                ->constrained('postos')
                ->nullOnDelete();
            $table->string('provincia')->nullable();

            // Score de regularidade
            $table->unsignedTinyInteger('score')->default(50); // 0-100
            $table->enum('estado', ['critico', 'atencao', 'regular', 'sem-dados'])
                ->default('sem-dados');

            // Última sincronização
            $table->timestamp('ultima_sincronizacao')->nullable();

            $table->timestamps();

            $table->index('estado');
            $table->index('score');
            $table->index('posto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos');
    }
};