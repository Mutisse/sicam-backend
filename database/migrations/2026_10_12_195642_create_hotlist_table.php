<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotlist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('veiculo_id');
            $table->enum('motivo', ['Furtado', 'Clonagem suspeita', 'Mandado captura', 'Suspeito', 'Outros']);
            $table->enum('perigo', ['alto', 'medio', 'baixo'])->default('medio');
            $table->enum('estado', ['Activo', 'Resolvido'])->default('Activo');
            $table->text('descricao')->nullable();
            $table->timestamp('detectado_em')->nullable();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();

            $table->index('veiculo_id');
            $table->index('motivo');
            $table->index('estado');
            $table->index('perigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotlist');
    }
};