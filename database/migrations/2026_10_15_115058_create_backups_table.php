<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 30)->unique();
            $table->string('ficheiro', 200);
            $table->enum('tipo', ['Automático', 'Manual'])->default('Manual');
            $table->enum('origem', ['Completo', 'Base de dados', 'Ficheiros']);
            $table->string('tamanho', 30)->default('—');
            $table->enum('estado', ['Concluído', 'Em curso', 'Falhou'])->default('Em curso');
            $table->string('autor', 120)->default('sistema');
            $table->string('duracao', 30)->default('—');

            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('estado');
            $table->index('tipo');
            $table->index('origem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};