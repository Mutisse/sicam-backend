<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autos_noticia', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('matricula', 20)->index();
            $table->string('infracao_codigo', 40);
            $table->string('infracao_label', 120);
            $table->decimal('valor', 12, 2);
            $table->string('local', 160)->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('agente_id')->constrained('users');
            $table->string('posto', 120);
            $table->enum('estado', ['Emitido', 'Impresso', 'Anulado'])->default('Emitido');
            $table->timestamp('emitido_em')->useCurrent();
            $table->timestamps();

            $table->index(['agente_id', 'emitido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autos_noticia');
    }
};