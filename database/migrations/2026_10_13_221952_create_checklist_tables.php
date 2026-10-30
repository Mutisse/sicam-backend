<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------
        // SECÇÕES
        // ------------------------------------------------------
        Schema::create('checklist_seccoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 60)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('ativo');
            $table->index('ordem');
        });

        // ------------------------------------------------------
        // CAMPOS
        // ------------------------------------------------------
        Schema::create('checklist_campos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccao_id')
                ->constrained('checklist_seccoes')
                ->cascadeOnDelete();
            $table->string('chave', 60);
            $table->string('label');

            // Tipo do campo
            $table->enum('tipo', [
                'booleano',   // OK / FALHA
                'decimal',    // 2.8 kN, 4.5 mm
                'numero',     // 145320 km
                'texto',      // VIN, marca/modelo
                'opcoes',     // Gasolina / Diesel / ...
            ])->default('booleano');

            // Regras
            $table->json('opcoes')->nullable();         // para tipo=opcoes
            $table->decimal('min', 10, 3)->nullable();  // limite mínimo
            $table->decimal('max', 10, 3)->nullable();  // limite máximo
            $table->boolean('invertido')->default(false); // true = valor true é FALHA
            $table->boolean('so_se_gasolina')->default(false);
            $table->boolean('so_se_diesel')->default(false);

            // Meta
            $table->string('unidade', 20)->nullable();  // kN, mm, %, Lux, km
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['seccao_id', 'chave']);
            $table->index('ativo');
            $table->index('ordem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_campos');
        Schema::dropIfExists('checklist_seccoes');
    }
};