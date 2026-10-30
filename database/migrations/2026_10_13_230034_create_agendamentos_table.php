<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 32)->unique()->nullable();

            // Veículo (opcional — se já existir como registo)
            $table->foreignId('veiculo_id')->nullable()
                ->constrained('veiculos')->nullOnDelete();

            // Dados do veículo (denormalizado — permite agendar sem veículo registado)
            $table->string('matricula', 20);
            $table->string('marca', 60);
            $table->string('modelo', 60);

            // Proprietário
            $table->string('proprietario', 120);
            $table->string('nif', 9);
            $table->string('telefone', 30)->nullable();

            // Marcação
            $table->date('data');
            $table->time('hora');
            $table->unsignedSmallInteger('duracao')->default(30);
            $table->string('tipo', 40);

            // Posto + inspector
            $table->foreignId('posto_id')->nullable()
                ->constrained('postos')->nullOnDelete();
            $table->string('posto_nome', 120)->nullable();
            $table->string('inspetor', 120);

            // Estado
            $table->enum('estado', [
                'Pendente',
                'Confirmado',
                'Cancelado',
                'Concluído',
            ])->default('Pendente');

            $table->text('observacoes')->nullable();

            // Meta
            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Índices úteis
            $table->index('matricula');
            $table->index('data');
            $table->index('estado');
            $table->index(['data', 'hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};