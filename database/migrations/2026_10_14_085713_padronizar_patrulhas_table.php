<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // 1) Adicionar colunas novas
        // =====================================================
        Schema::table('patrulhas', function (Blueprint $table) {
            if (!Schema::hasColumn('patrulhas', 'nome')) {
                $table->string('nome', 120)->nullable()->after('codigo');
            }
            if (!Schema::hasColumn('patrulhas', 'prioridade')) {
                $table->string('prioridade', 20)->default('Média')->after('local');
            }
            if (!Schema::hasColumn('patrulhas', 'pontos_previstos')) {
                $table->unsignedSmallInteger('pontos_previstos')->default(10)->after('prioridade');
            }
            if (!Schema::hasColumn('patrulhas', 'pontos_concluidos')) {
                $table->unsignedSmallInteger('pontos_concluidos')->default(0)->after('pontos_previstos');
            }
            if (!Schema::hasColumn('patrulhas', 'paragens')) {
                $table->json('paragens')->nullable()->after('pontos_concluidos');
            }
            if (!Schema::hasColumn('patrulhas', 'notas')) {
                $table->text('notas')->nullable()->after('paragens');
            }
        });

        // =====================================================
        // 2) ALTERAR os ENUMs PRIMEIRO (para aceitarem os novos valores)
        // =====================================================
        DB::statement("
            ALTER TABLE patrulhas
            MODIFY COLUMN turno ENUM('Manhã', 'Tarde', 'Noite', 'Matinal', 'Nocturna') NOT NULL
        ");

        DB::statement("
            ALTER TABLE patrulhas
            MODIFY COLUMN estado ENUM(
                'Planeada', 'Em curso', 'Concluída', 'Cancelada', 'Pendente'
            ) NOT NULL DEFAULT 'Planeada'
        ");

        // =====================================================
        // 3) AGORA sim, atualizar os valores
        // =====================================================
        DB::table('patrulhas')->where('turno', 'Matinal')->update(['turno' => 'Manhã']);
        DB::table('patrulhas')->where('turno', 'Nocturna')->update(['turno' => 'Noite']);

        DB::table('patrulhas')->where('estado', 'Pendente')->update(['estado' => 'Planeada']);

        // =====================================================
        // 4) Preencher `nome` dos registos existentes
        // =====================================================
        DB::table('patrulhas')->orderBy('id')->each(function ($p) {
            $nome = sprintf(
                'Patrulha %s — %s',
                $p->local ?? '—',
                $p->turno ?? '—',
            );
            DB::table('patrulhas')->where('id', $p->id)->update(['nome' => $nome]);
        });

        // =====================================================
        // 5) Reduzir os ENUMs aos valores finais
        // =====================================================
        DB::statement("
            ALTER TABLE patrulhas
            MODIFY COLUMN turno ENUM('Manhã', 'Tarde', 'Noite') NOT NULL
        ");

        DB::statement("
            ALTER TABLE patrulhas
            MODIFY COLUMN estado ENUM(
                'Planeada', 'Em curso', 'Concluída', 'Cancelada'
            ) NOT NULL DEFAULT 'Planeada'
        ");
    }

    public function down(): void
    {
        // Reverter valores
        DB::table('patrulhas')->where('turno', 'Manhã')->update(['turno' => 'Matinal']);
        DB::table('patrulhas')->where('turno', 'Noite')->update(['turno' => 'Nocturna']);
        DB::table('patrulhas')->where('estado', 'Planeada')->update(['estado' => 'Pendente']);

        Schema::table('patrulhas', function (Blueprint $table) {
            $table->dropColumn([
                'nome',
                'prioridade',
                'pontos_previstos',
                'pontos_concluidos',
                'paragens',
                'notas',
            ]);
        });
    }
};