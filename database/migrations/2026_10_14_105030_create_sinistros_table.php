<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sinistros', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 40)->unique();

            $table->foreignId('apolice_id')->nullable()
                ->constrained('apolices')->nullOnDelete();

            $table->string('matricula', 20);
            $table->string('segurado', 120);
            $table->string('seguradora', 120);

            $table->enum('tipo', [
                'Colisão', 'Furto', 'Incêndio', 'Inundação', 'Vandalismo',
            ]);

            $table->enum('gravidade', ['Leve', 'Moderado', 'Grave'])
                ->default('Moderado');

            $table->date('data');
            $table->string('local', 200);
            $table->text('descricao');

            $table->unsignedBigInteger('valor_estimado')->default(0);
            $table->unsignedBigInteger('valor_aprovado')->nullable();

            $table->enum('estado', ['Aberto', 'Em análise', 'Resolvido', 'Recusado'])
                ->default('Aberto');

            $table->string('responsavel', 120);

            $table->text('observacoes')->nullable();

            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('matricula');
            $table->index('estado');
            $table->index('tipo');
            $table->index('gravidade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sinistros');
    }
};