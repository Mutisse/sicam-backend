<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_tipos', function (Blueprint $table) {
            $table->id();

            $table->string('key', 40)->unique();
            $table->string('label', 120);
            $table->string('descricao', 255);
            $table->string('icon', 60)->default('backup');
            $table->string('extensao', 20)->default('sql.gz');
            $table->string('prefixo', 60);
            $table->string('tamanho_aproximado', 30)->default('—');

            $table->enum('periodicidade', ['Diário', 'Semanal', 'Mensal', 'Nunca'])
                ->default('Diário');

            $table->string('hora_execucao', 5)->default('03:00');
            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_tipos');
    }
};