<?php
// database/migrations/xxxx_add_sicam_fields_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ─── Identificação ─────────────────────────────
            $table->string('numero_id')->nullable()->after('name');
            $table->string('telefone', 30)->nullable()->after('email');

            // ─── Perfil (FK) ───────────────────────────────
            $table->foreignId('perfil_id')->nullable()->after('telefone')
                  ->constrained('perfis')->nullOnDelete();

            // ─── Posto / função ────────────────────────────
            $table->string('posto')->nullable()->after('perfil_id');
            $table->string('funcao')->nullable()->after('posto');

            // ─── Estado ────────────────────────────────────
            $table->enum('estado', ['Ativo', 'Bloqueado'])->default('Ativo')->after('funcao');
            $table->timestamp('ultimo_acesso')->nullable()->after('estado');

            // ─── Foto ──────────────────────────────────────
            $table->string('foto_url')->nullable()->after('ultimo_acesso');

            // ─── Preferências (JSON) ───────────────────────
            $table->json('preferencias')->nullable()->after('foto_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['perfil_id']);
            $table->dropColumn([
                'numero_id',
                'telefone',
                'perfil_id',
                'posto',
                'funcao',
                'estado',
                'ultimo_acesso',
                'foto_url',
                'preferencias',
            ]);
        });
    }
};