<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Limpar a tabela para não haver duplicados
        DB::table('perfil_permissoes')->truncate();

        // 2. Lista de todos os módulos do sistema
        $modulos = [
            'dashboard', 'relatorios', 'hotlist', 'abordagens', 'alertas', 'mapa',
            'utilizadores', 'perfis', 'postos', 'dispositivos', 'integracoes',
            'auditoria', 'backups', 'monitorizacao', 'agentes', 'escalas',
            'patrulhas', 'veiculos_criticos', 'aprovacoes', 'veiculos', 'inspecoes',
            'agendamentos', 'exportacao', 'multas', 'pagamentos', 'contestacoes',
            'apolices', 'sinistros', 'validacao', 'seguradoras'
        ];

        $perfis = DB::table('perfis')->pluck('key', 'id')->toArray();

        // 3. Iterar cada perfil e cada módulo para atribuir permissões
        foreach ($perfis as $id => $key) {
            foreach ($modulos as $modulo) {
                
                $ver = 0; $criar = 0; $editar = 0; $apagar = 0;

                // ==========================================
                // ADMIN (Acesso Total a tudo)
                // ==========================================
                if ($key === 'admin') {
                    $ver = 1; $criar = 1; $editar = 1; $apagar = 1;
                }
                
                // ==========================================
                // CHEFE DE POSTO
                // ==========================================
                elseif ($key === 'chefe') {
                    if (in_array($modulo, ['dashboard', 'agentes', 'escalas', 'patrulhas', 'veiculos_criticos', 'aprovacoes', 'relatorios'])) {
                        $ver = 1;
                        if (in_array($modulo, ['agentes', 'escalas', 'patrulhas'])) {
                            $criar = 1; $editar = 1;
                        }
                    }
                }

                // ==========================================
                // PRM
                // ==========================================
                elseif ($key === 'prm') {
                    if (in_array($modulo, ['dashboard', 'hotlist', 'abordagens', 'alertas', 'mapa', 'relatorios'])) {
                        $ver = 1;
                        if (in_array($modulo, ['hotlist', 'abordagens', 'alertas'])) {
                            $criar = 1; $editar = 1;
                        }
                    }
                }

                // ==========================================
                // ISSM
                // ==========================================
                elseif ($key === 'issm') {
                    if (in_array($modulo, ['dashboard', 'apolices', 'sinistros', 'validacao', 'seguradoras', 'relatorios'])) {
                        $ver = 1;
                        if (in_array($modulo, ['apolices', 'sinistros', 'validacao'])) {
                            $criar = 1; $editar = 1;
                        }
                    }
                }

                // ==========================================
                // GESTMUL
                // ==========================================
                elseif ($key === 'gestmul') {
                    if (in_array($modulo, ['dashboard', 'multas', 'pagamentos', 'contestacoes', 'exportacao', 'relatorios'])) {
                        $ver = 1;
                        if (in_array($modulo, ['multas', 'pagamentos', 'contestacoes'])) {
                            $criar = 1; $editar = 1;
                        }
                    }
                }

                // ==========================================
                // INATRO
                // ==========================================
                elseif ($key === 'inatro') {
                    if (in_array($modulo, ['dashboard', 'veiculos', 'inspecoes', 'agendamentos', 'exportacao', 'relatorios'])) {
                        $ver = 1;
                        if (in_array($modulo, ['veiculos', 'inspecoes', 'agendamentos'])) {
                            $criar = 1; $editar = 1;
                        }
                    }
                }

                // ==========================================
                // AGENTE
                // ==========================================
                elseif ($key === 'agente') {
                    if (in_array($modulo, ['veiculos', 'inspecoes', 'multas'])) {
                        $ver = 1; $criar = 1;
                    }
                }

                // Inserir na base de dados
                DB::table('perfil_permissoes')->insert([
                    'perfil_id'  => $id,
                    'modulo'     => $modulo,
                    'ver'        => $ver,
                    'criar'      => $criar,
                    'editar'     => $editar,
                    'apagar'     => $apagar,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};