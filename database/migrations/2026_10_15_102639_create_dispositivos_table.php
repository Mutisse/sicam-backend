<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();

            $table->string('serie', 40)->unique();
            $table->string('modelo', 120);
            $table->string('agente', 160)->nullable();

            $table->enum('estado', ['Ativo', 'Por registar'])->default('Por registar');

            $table->string('versao_app', 20)->default('2.5.0');
            $table->timestamp('ultima_sincronizacao')->nullable();

            $table->text('notas')->nullable();

            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('estado');
            $table->index('agente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos');
    }
};