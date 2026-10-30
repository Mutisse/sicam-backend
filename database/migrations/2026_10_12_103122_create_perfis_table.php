<?php
// database/migrations/xxxx_create_perfis_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // PERFIS
        // ============================================================
        Schema::create('perfis', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();        // admin, chefe, inatro, ...
            $table->string('label');                 // Administrador, Chefe de Posto, ...
            $table->text('descricao')->nullable();
            $table->string('icon')->default('shield');
            $table->string('cor', 7)->default('#065f46');
            $table->enum('estado', ['Ativo', 'Bloqueado'])->default('Ativo');
            $table->boolean('sistema')->default(false);   // não pode ser removido
            $table->timestamps();
        });

        // ============================================================
        // PERMISSÕES POR PERFIL (matriz)
        // ============================================================
        Schema::create('perfil_permissoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_id')->constrained('perfis')->cascadeOnDelete();

            $table->string('modulo');                 // dashboard, utilizadores, ...
            $table->boolean('ver')->default(false);
            $table->boolean('criar')->default(false);
            $table->boolean('editar')->default(false);
            $table->boolean('apagar')->default(false);

            $table->timestamps();

            $table->unique(['perfil_id', 'modulo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfil_permissoes');
        Schema::dropIfExists('perfis');
    }
};