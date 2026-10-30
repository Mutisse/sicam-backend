<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartas_conducao', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 20)->unique();       // 9 dígitos
            $table->string('nomes', 200);                 // "JOHN CITIZEN" (campo 1)
            $table->string('nome_completo', 200);         // (campo 2)
            $table->string('nif', 20)->nullable();        // BI/NIF do titular

            $table->date('data_nascimento');
            $table->date('data_emissao');
            $table->date('data_validade');
            $table->date('data_primeira_emissao')->nullable();

            $table->string('codigo_carta', 10)->nullable(); // "A0", "B0", "C0"
            $table->json('categorias')->nullable();         // ["A1","B","C1"]
            $table->string('restricoes', 60)->nullable();
            $table->string('restricoes_condutor', 60)->nullable();

            $table->enum('sexo', ['M', 'F'])->nullable();
            $table->string('nacionalidade', 60)->default('Moçambicana');

            $table->string('foto_url', 255)->nullable();
            $table->string('assinatura_url', 255)->nullable();

            $table->enum('estado', ['Válida', 'Expirada', 'Suspensa', 'Cancelada'])
                ->default('Válida');

            $table->timestamps();

            $table->index('nif');
            $table->index('estado');
            $table->index('data_validade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartas_conducao');
    }
};