<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noticias', function (Blueprint $table) {
            $table->id();

            $table->string('titulo', 200);
            $table->string('resumo', 400);
            $table->text('conteudo')->nullable();
            $table->string('categoria', 60)->default('Geral');
            $table->string('autor', 120)->nullable();
            $table->string('imagem_url', 255)->nullable();

            $table->date('data_publicacao');
            $table->boolean('destaque')->default(false);
            $table->boolean('publicada')->default(true);

            $table->timestamps();

            $table->index('data_publicacao');
            $table->index('destaque');
            $table->index('publicada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noticias');
    }
};