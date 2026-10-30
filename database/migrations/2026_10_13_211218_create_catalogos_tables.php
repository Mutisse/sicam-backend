<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ------------------------------------------------------
        // MARCAS
        // ------------------------------------------------------
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('ativo');
        });

        // ------------------------------------------------------
        // MODELOS
        // ------------------------------------------------------
        Schema::create('modelos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')
                ->constrained('marcas')
                ->cascadeOnDelete();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['marca_id', 'nome']);
            $table->index('ativo');
        });

        // ------------------------------------------------------
        // CORES
        // ------------------------------------------------------
        Schema::create('cores', function (Blueprint $table) {
            $table->id();
            $table->string('categoria');
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['categoria', 'nome']);
            $table->index('categoria');
            $table->index('ativo');
        });

        // ------------------------------------------------------
        // PROVÍNCIAS
        // ------------------------------------------------------
        Schema::create('provincias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 4)->unique();
            $table->string('nome')->unique();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('ativo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ordem inversa por causa das foreign keys
        Schema::dropIfExists('modelos');
        Schema::dropIfExists('marcas');
        Schema::dropIfExists('cores');
        Schema::dropIfExists('provincias');
    }
};