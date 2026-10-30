<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Adicionar valores ao ENUM 'resultado' + colunas novas
        DB::statement("
            ALTER TABLE inspecoes
            MODIFY COLUMN resultado ENUM(
                'Aprovada',
                'Aprovada com observações',
                'Reprovada',
                'Pendente'
            ) NOT NULL DEFAULT 'Pendente'
        ");

        Schema::table('inspecoes', function (Blueprint $table) {
            $table->string('codigo', 32)->nullable()->unique()->after('id');
            $table->string('tipo', 40)->nullable()->after('estado');
            $table->json('itens')->nullable()->after('observacoes');
            $table->date('proxima_inspecao')->nullable()->after('validade');
        });

        // 2) Popular registos existentes (código + tipo + proxima_inspecao)
        DB::table('inspecoes')
            ->orderBy('id')
            ->each(function ($row) {
                $ano = $row->data ? date('Y', strtotime($row->data)) : date('Y');
                $data = $row->data ? date('Y-m-d', strtotime($row->data)) : date('Y-m-d');

                $validade = $row->validade
                    ? date('Y-m-d', strtotime($row->validade))
                    : date('Y-m-d', strtotime($data . ' +1 year'));

                DB::table('inspecoes')
                    ->where('id', $row->id)
                    ->update([
                        'codigo'             => sprintf('INS-%s-%06d', $ano, $row->id),
                        'tipo'               => 'Periódica',
                        'proxima_inspecao'   => $validade,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('inspecoes', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn(['codigo', 'tipo', 'itens', 'proxima_inspecao']);
        });

        DB::statement("
            ALTER TABLE inspecoes
            MODIFY COLUMN resultado ENUM(
                'Aprovada',
                'Reprovada',
                'Pendente'
            ) NOT NULL DEFAULT 'Pendente'
        ");
    }
};