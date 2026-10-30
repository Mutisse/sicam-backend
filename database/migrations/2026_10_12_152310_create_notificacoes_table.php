<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('mensagem');
            $table->string('tipo', 30)->default('info'); // info | sucesso | aviso | erro
            $table->string('url', 255)->nullable();
            $table->boolean('lida')->default(false);
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};