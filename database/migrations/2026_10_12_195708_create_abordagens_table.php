<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abordagens', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // AB-001
            $table->unsignedBigInteger('veiculo_id');
            $table->unsignedBigInteger('agente_id')->nullable();
            $table->unsignedBigInteger('posto_id')->nullable();
            $table->string('local')->nullable();
            $table->enum('resultado', ['Sem incidentes', 'Com detenção', 'Em curso'])
                ->default('Sem incidentes');
            $table->date('data');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('veiculo_id');
            $table->index('agente_id');
            $table->index('data');
            $table->index('resultado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abordagens');
    }
};