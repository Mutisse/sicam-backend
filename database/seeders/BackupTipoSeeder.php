<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackupTipoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'key' => 'Completo',
                'label' => 'Backup completo',
                'descricao' => 'Base de dados + ficheiros do sistema',
                'icon' => 'inventory_2',
                'extensao' => 'sql.gz',
                'prefixo' => 'sicam_full',
                'tamanho_aproximado' => '1,8 GB',
                'periodicidade' => 'Diário',
                'hora_execucao' => '03:00',
                'ativo' => true,
            ],
            [
                'key' => 'Base de dados',
                'label' => 'Só base de dados',
                'descricao' => 'Apenas as tabelas e registos do sistema',
                'icon' => 'storage',
                'extensao' => 'sql.gz',
                'prefixo' => 'sicam_db',
                'tamanho_aproximado' => '612 MB',
                'periodicidade' => 'Diário',
                'hora_execucao' => '03:30',
                'ativo' => true,
            ],
            [
                'key' => 'Ficheiros',
                'label' => 'Só ficheiros',
                'descricao' => 'Documentos, anexos e imagens',
                'icon' => 'folder_zip',
                'extensao' => 'tar.gz',
                'prefixo' => 'sicam_files',
                'tamanho_aproximado' => '942 MB',
                'periodicidade' => 'Semanal',
                'hora_execucao' => '04:00',
                'ativo' => false,
            ],
        ];

        foreach ($tipos as $t) {
            DB::table('backup_tipos')->updateOrInsert(
                ['key' => $t['key']],
                [
                    ...$t,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ],
            );
        }
    }
}