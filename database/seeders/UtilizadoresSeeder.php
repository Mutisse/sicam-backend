<?php
// database/seeders/UtilizadoresSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UtilizadoresSeeder extends Seeder
{
    /**
     * Utilizadores com password individual.
     *
     * Formato: [nome, email, perfil_key, posto, funcao, numero_id, password]
     */
    private const UTILIZADORES = [
        ['Administrador do Sistema', 'admin@sicam.gov.mz',   'admin',   'Sede',        'Administrador',      'SICAM-ADM-001', 'admin@sicam'],
        ['Chefe Mabunda',            'chefe@sicam.gov.mz',   'chefe',   'Posto Baixa', 'Chefe de Posto',     'PRM-CHF-001',   'chefe@sicam'],
        ['Agente Chissano',          'agente@sicam.gov.mz',  'agente',  'Posto Baixa', 'Agente de Trânsito', 'PRM-AT-2290',   'agente@sicam'],
        ['INATRO Operações',         'inatro@sicam.gov.mz',  'inatro',  'Sede',        'Operador INATRO',    'INATRO-001',    'inatro@sicam'],
        ['GESTMUL Central',          'gestmul@sicam.gov.mz', 'gestmul', 'Sede',        'Operador GESTMUL',   'GESTMUL-001',   'gestmul@sicam'],
        ['ISSM Supervisor',          'issm@sicam.gov.mz',    'issm',    'Sede',        'Supervisor ISSM',    'ISSM-001',      'issm@sicam'],
        ['PRM Central',              'prm@sicam.gov.mz',     'prm',     'Sede',        'Operador PRM',       'PRM-001',       'prm@sicam'],
        ['Cidadão Comum',            'cidadao@sicam.gov.mz', 'cidadao', null,          null,                 null,            'cidadao@sicam'],
    ];

    public function run(): void
    {
        $agora = now();

        // Limpar utilizadores
        DB::table('users')->delete();

        // Ler IDs dos perfis
        $perfisDb = DB::table('perfis')->pluck('id', 'key')->toArray();

        $preferencias = json_encode([
            'tema'         => 'auto',
            'idioma'       => 'pt',
            'densidade'    => 'normal',
            'modo_offline' => false,
            'notif_push'   => true,
            'notif_som'    => true,
            'notif_email'  => false,
        ]);

        $utilizadoresData = [];
        foreach (self::UTILIZADORES as [$nome, $email, $perfilKey, $posto, $funcao, $numeroId, $password]) {
            $utilizadoresData[] = [
                'name'          => $nome,
                'email'         => $email,
                'password'      => Hash::make($password),   // ⭐ password individual
                'perfil_id'     => $perfisDb[$perfilKey] ?? null,
                'numero_id'     => $numeroId,
                'telefone'      => '+258 84 000 0000',
                'posto'         => $posto,
                'funcao'        => $funcao,
                'estado'        => 'Ativo',
                'ultimo_acesso' => null,
                'preferencias'  => $preferencias,
                'created_at'    => $agora,
                'updated_at'    => $agora,
            ];
        }

        // 1 query só
        DB::table('users')->insert($utilizadoresData);
    }
}