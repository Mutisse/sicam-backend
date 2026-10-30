<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contestacoes', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 30)->unique();
            $table->string('multa_codigo', 30);
            $table->string('matricula', 20);
            $table->string('infracao', 160);
            $table->decimal('valor_multa', 12, 2)->default(0);

            $table->string('contestante', 160);
            $table->string('nif', 9);
            $table->string('contacto', 30)->nullable();

            $table->string('motivo', 120);
            $table->text('descricao');

            $table->enum('prioridade', ['Baixa', 'Média', 'Alta'])->default('Média');

            $table->date('data_submissao');
            $table->date('prazo_limite');

            $table->enum('estado', [
                'Pendente', 'Em análise', 'Aceite', 'Rejeitada', 'Cancelada',
            ])->default('Pendente');

            $table->text('decisao')->nullable();
            $table->timestamp('decisao_em')->nullable();
            $table->string('decisao_por', 160)->nullable();

            $table->text('observacoes')->nullable();

            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('estado');
            $table->index('motivo');
            $table->index('prioridade');
            $table->index('matricula');
            $table->index('prazo_limite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contestacoes');
    }
};