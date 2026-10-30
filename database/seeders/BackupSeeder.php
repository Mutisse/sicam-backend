<?php

namespace Database\Seeders;

use App\Models\Backup;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BackupSeeder extends Seeder
{
    public function run(): void
    {
        $exemplos = [
            ['sicam_full_2026-10-10_0300.sql.gz',  'Automático', 'Completo',      '1,8 GB', 'Concluído', 'sistema', '4 min 12 s', 5],
            ['sicam_full_2026-10-09_0300.sql.gz',  'Automático', 'Completo',      '1,8 GB', 'Concluído', 'sistema', '4 min 08 s', 6],
            ['sicam_files_2026-10-08_2217.tar.gz', 'Manual',     'Ficheiros',     '942 MB', 'Concluído', 'admin',   '2 min 41 s', 7],
            ['sicam_db_2026-10-08_1830.sql.gz',    'Manual',     'Base de dados', '612 MB', 'Falhou',    'admin',   '—',          7],
            ['sicam_full_2026-10-08_0300.sql.gz',  'Automático', 'Completo',      '1,7 GB', 'Concluído', 'sistema', '4 min 02 s', 8],
        ];

        foreach ($exemplos as $i => $e) {
            [$ficheiro, $tipo, $origem, $tamanho, $estado, $autor, $duracao, $diasAtras] = $e;
            $data = Carbon::now()->subDays($diasAtras)->setTime(3, 0);

            Backup::create([
                'codigo'    => sprintf('BK-%d-%04d', now()->year, $i + 1),
                'ficheiro'  => $ficheiro,
                'tipo'      => $tipo,
                'origem'    => $origem,
                'tamanho'   => $tamanho,
                'estado'    => $estado,
                'autor'     => $autor,
                'duracao'   => $duracao,
                'created_at'=> $data,
                'updated_at'=> $data,
            ]);
        }
    }
}