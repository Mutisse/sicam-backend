<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ⭐ Adiciona o turno padrão (enum)
            $table->enum('turno', ['Manhã', 'Tarde', 'Noite'])
                  ->nullable()
                  ->after('posto');

            // ⭐ Adiciona a viatura padrão
            $table->string('viatura', 20)
                  ->nullable()
                  ->after('turno');

            // ⭐ Adiciona o campo 'activo' para controlar o estado
            // (Ativo, Bloqueado já existem, mas 'Inactivo' também é útil)
            // O enum atual é ('Ativo','Bloqueado')
            // Vamos expandir para incluir 'Inactivo'
            $table->enum('estado', ['Ativo', 'Inactivo', 'Bloqueado'])
                  ->default('Ativo')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['turno', 'viatura']);
            $table->enum('estado', ['Ativo', 'Bloqueado'])
                  ->default('Ativo')
                  ->change();
        });
    }
};