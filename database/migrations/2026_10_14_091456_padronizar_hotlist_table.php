<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotlist', function (Blueprint $table) {
            if (!Schema::hasColumn('hotlist', 'prioridade')) {
                $table->enum('prioridade', ['Baixa', 'Média', 'Alta', 'Urgente'])
                    ->default('Média')
                    ->after('perigo');
            }
            if (!Schema::hasColumn('hotlist', 'agente')) {
                $table->string('agente', 120)->nullable()->after('prioridade');
            }
            if (!Schema::hasColumn('hotlist', 'posto')) {
                $table->string('posto', 120)->nullable()->after('agente');
            }
            if (!Schema::hasColumn('hotlist', 'zona')) {
                $table->string('zona', 120)->nullable()->after('posto');
            }
            if (!Schema::hasColumn('hotlist', 'multas_pendentes')) {
                $table->unsignedInteger('multas_pendentes')->default(0)->after('zona');
            }
            if (!Schema::hasColumn('hotlist', 'valor_multas')) {
                $table->unsignedBigInteger('valor_multas')->default(0)->after('multas_pendentes');
            }
            if (!Schema::hasColumn('hotlist', 'historico')) {
                $table->json('historico')->nullable()->after('valor_multas');
            }
        });

        // Preencher `prioridade` com base em `perigo` (bom ponto de partida)
        DB::table('hotlist')->where('perigo', 'alto')->update(['prioridade' => 'Alta']);
        DB::table('hotlist')->where('perigo', 'medio')->update(['prioridade' => 'Média']);
        DB::table('hotlist')->where('perigo', 'baixo')->update(['prioridade' => 'Baixa']);

        // Preencher `historico` mínimo para registos existentes
        DB::table('hotlist')->orderBy('id')->each(function ($h) {
            $historico = [
                [
                    'data'        => $h->detectado_em
                        ? date('d/m/Y H:i', strtotime($h->detectado_em))
                        : '—',
                    'acao'        => 'Registo importado',
                    'responsavel' => 'Sistema',
                ],
            ];

            if ($h->estado === 'Resolvido' && $h->resolvido_em) {
                $historico[] = [
                    'data'        => date('d/m/Y H:i', strtotime($h->resolvido_em)),
                    'acao'        => 'Resolvido',
                    'responsavel' => 'Sistema',
                ];
            }

            DB::table('hotlist')->where('id', $h->id)->update([
                'historico' => json_encode($historico),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('hotlist', function (Blueprint $table) {
            $table->dropColumn([
                'prioridade', 'agente', 'posto', 'zona',
                'multas_pendentes', 'valor_multas', 'historico',
            ]);
        });
    }
};