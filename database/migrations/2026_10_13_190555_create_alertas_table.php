<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->enum('nivel', ['critico', 'atencao', 'info']);
            $table->enum('tipo', ['hotlist', 'clonagem', 'sinistro', 'sistema']);
            $table->string('titulo');
            $table->text('mensagem');
            $table->string('referencia', 40)->nullable();
            $table->string('origem', 120)->nullable();
            $table->boolean('lido')->default(false);
            $table->timestamp('lido_em')->nullable();
            $table->timestamps();

            $table->index('nivel');
            $table->index('tipo');
            $table->index('lido');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};