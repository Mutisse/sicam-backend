<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos_catalogo', function (Blueprint $table) {
            $table->id();

            $table->string('marca', 60);
            $table->string('modelo', 80);
            $table->string('categoria', 40);       // sedan/hatch, suv, pickup, comercial, citadino, desportivo
            $table->string('combustivel', 40)->nullable();
            $table->string('origem', 40)->nullable();
            $table->string('popularidade', 20)->nullable();

            // mapeamento do tipo usado em `veiculos.tipo`
            $table->string('tipo_veiculo', 40)->default('Ligeiro');

            $table->timestamps();

            $table->unique(['marca', 'modelo']);
            $table->index('categoria');
            $table->index('tipo_veiculo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos_catalogo');
    }
};