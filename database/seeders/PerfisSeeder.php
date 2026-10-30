<?php
// database/seeders/PerfisSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerfisSeeder extends Seeder
{
    private const MODULOS = [
        'dashboard', 'relatorios', 'hotlist', 'abordagens', 'alertas', 'mapa',
        'utilizadores', 'perfis', 'postos', 'dispositivos',
        'integracoes', 'auditoria', 'backups', 'monitorizacao',
        'agentes', 'escalas', 'patrulhas', 'veiculos_criticos', 'aprovacoes',
        'veiculos', 'inspecoes', 'agendamentos', 'exportacao',
        'multas', 'pagamentos', 'contestacoes',
        'apolices', 'sinistros', 'validacao', 'seguradoras',
    ];

    private const PERMISSOES = [
        'admin'   => '*',
        'chefe'   => [
            'dashboard' => ['ver','criar','editar','apagar'],
            'agentes' => ['ver','criar','editar','apagar'],
            'escalas' => ['ver','criar','editar','apagar'],
            'patrulhas' => ['ver','criar','editar','apagar'],
            'veiculos_criticos' => ['ver','criar','editar'],
            'aprovacoes' => ['ver','criar','editar'],
            'relatorios' => ['ver'],
            'hotlist' => ['ver'],
            'abordagens' => ['ver'],
            'alertas' => ['ver'],
            'mapa' => ['ver'],
            'dispositivos' => ['ver'],
        ],
        'agente'  => [
            'dashboard' => ['ver'],
            'hotlist' => ['ver','criar'],
            'abordagens' => ['ver','criar'],
            'alertas' => ['ver','criar'],
            'mapa' => ['ver'],
        ],
        'inatro'  => [
            'dashboard' => ['ver'],
            'veiculos' => ['ver','criar','editar'],
            'inspecoes' => ['ver','criar','editar'],
            'agendamentos' => ['ver','criar','editar'],
            'exportacao' => ['ver','criar'],
            'relatorios' => ['ver'],
        ],
        'gestmul' => [
            'dashboard' => ['ver'],
            'multas' => ['ver','criar','editar'],
            'pagamentos' => ['ver','criar','editar'],
            'contestacoes' => ['ver','criar','editar'],
            'relatorios' => ['ver'],
            'exportacao' => ['ver','criar'],
        ],
        'issm'    => [
            'dashboard' => ['ver'],
            'apolices' => ['ver','criar','editar'],
            'sinistros' => ['ver','criar','editar'],
            'validacao' => ['ver','criar','editar'],
            'seguradoras' => ['ver'],
            'relatorios' => ['ver'],
        ],
        'prm'     => [
            'dashboard' => ['ver'],
            'hotlist' => ['ver','criar','editar'],
            'abordagens' => ['ver','criar','editar'],
            'mapa' => ['ver'],
            'alertas' => ['ver','criar'],
            'relatorios' => ['ver'],
        ],
        'cidadao' => [],
    ];

    private const PERFIS = [
        ['key' => 'admin',   'label' => 'Administrador',  'descricao' => 'Acesso total ao sistema',            'icon' => 'admin_panel_settings', 'cor' => '#dc2626', 'sistema' => true],
        ['key' => 'chefe',   'label' => 'Chefe de Posto', 'descricao' => 'Gestão do posto e dos agentes',      'icon' => 'military_tech',        'cor' => '#f59e0b', 'sistema' => true],
        ['key' => 'agente',  'label' => 'Agente',         'descricao' => 'Operações de rua',                   'icon' => 'badge',                'cor' => '#065f46', 'sistema' => true],
        ['key' => 'inatro',  'label' => 'INATRO',         'descricao' => 'Registo e inspecção de veículos',    'icon' => 'directions_car',       'cor' => '#1d4ed8', 'sistema' => false],
        ['key' => 'gestmul', 'label' => 'GESTMUL',        'descricao' => 'Gestão de multas',                   'icon' => 'receipt_long',         'cor' => '#6d28d9', 'sistema' => false],
        ['key' => 'issm',    'label' => 'ISSM',           'descricao' => 'Seguro obrigatório',                 'icon' => 'verified_user',        'cor' => '#059669', 'sistema' => false],
        ['key' => 'prm',     'label' => 'PRM',            'descricao' => 'Polícia da República de Moçambique', 'icon' => 'local_police',         'cor' => '#1e40af', 'sistema' => false],
        ['key' => 'cidadao', 'label' => 'Cidadão',        'descricao' => 'Consulta pública',                   'icon' => 'person',               'cor' => '#64748b', 'sistema' => true],
    ];

    public function run(): void
    {
        $agora = now();

        // ═══════════════════════════════════════════════════════════
        // 1. Limpar tudo (1 query cada)
        // ═══════════════════════════════════════════════════════════
        DB::table('perfil_permissoes')->delete();
        DB::table('perfis')->delete();

        // ═══════════════════════════════════════════════════════════
        // 2. Inserir todos os perfis de uma vez (bulk insert)
        // ═══════════════════════════════════════════════════════════
        $perfisData = [];
        foreach (self::PERFIS as $p) {
            $perfisData[] = [
                'key'        => $p['key'],
                'label'      => $p['label'],
                'descricao'  => $p['descricao'],
                'icon'       => $p['icon'],
                'cor'        => $p['cor'],
                'estado'     => 'Ativo',
                'sistema'    => $p['sistema'],
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }
        DB::table('perfis')->insert($perfisData);

        // ═══════════════════════════════════════════════════════════
        // 3. Ler os IDs criados
        // ═══════════════════════════════════════════════════════════
        $perfisDb = DB::table('perfis')->pluck('id', 'key')->toArray();

        // ═══════════════════════════════════════════════════════════
        // 4. Construir TODAS as permissões e inserir de uma vez
        // ═══════════════════════════════════════════════════════════
        $permissoesData = [];

        foreach (self::PERFIS as $p) {
            $perfilId = $perfisDb[$p['key']];
            $regra    = self::PERMISSOES[$p['key']] ?? [];

            foreach (self::MODULOS as $modulo) {
                if ($regra === '*') {
                    $ver = $criar = $editar = $apagar = true;
                } else {
                    $acoes = $regra[$modulo] ?? [];
                    $ver    = in_array('ver', $acoes, true);
                    $criar  = in_array('criar', $acoes, true);
                    $editar = in_array('editar', $acoes, true);
                    $apagar = in_array('apagar', $acoes, true);
                }

                $permissoesData[] = [
                    'perfil_id'  => $perfilId,
                    'modulo'     => $modulo,
                    'ver'        => $ver,
                    'criar'      => $criar,
                    'editar'     => $editar,
                    'apagar'     => $apagar,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }
        }

        // Inserir em blocos de 100 (evita query demasiado grande)
        foreach (array_chunk($permissoesData, 100) as $chunk) {
            DB::table('perfil_permissoes')->insert($chunk);
        }
    }
}