<?php

namespace Database\Seeders;

use App\Models\Apolice;
use App\Models\Sinistro;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SinistroSeeder extends Seeder
{
    public function run(): void
    {
        $exemplos = [
            ['SN-2026-000087', 'AGL-482-MC', 'Carlos Nhaca',    'Seguradora A', 'Colisão',    'Moderado', '2026-09-22', 'Av. Julius Nyerere, Maputo',           'Colisão traseira em fila de trânsito',             85400,  null,   'Aberto',    'Insp. Chissano'],
            ['SN-2026-000086', 'ABR-771-MP', 'Pedro Cumbe',      'Seguradora B', 'Furto',      'Grave',    '2026-09-20', 'Bairro Central, Matola',               'Furto do veículo durante a noite',                 420000, null,   'Em análise','Insp. Nhaca'],
            ['SN-2026-000085', 'AFC-205-MC', 'Rita Tembe',       'Seguradora A', 'Colisão',    'Leve',     '2026-09-18', 'Av. 25 de Setembro, Maputo',           'Colisão lateral em rotunda',                       62800,  58000,  'Resolvido','Insp. Sitoe'],
            ['SN-2026-000084', 'ACD-918-MP', 'Sofia Macuácua',   'Seguradora C', 'Vandalismo', 'Leve',     '2026-09-15', 'Parque de estacionamento, Matola',     'Vidros partidos e riscos na carroçaria',           18200,  18200,  'Resolvido','Insp. Cumbe'],
            ['SN-2026-000083', 'AHN-304-MC', 'Luísa Muianga',    'Seguradora B', 'Colisão',    'Grave',    '2026-09-12', 'EN1, Marracuene',                      'Colisão frontal com capotamento',                  145600, null,   'Em análise','Insp. Tembe'],
            ['SN-2026-000082', 'ABX-556-MP', 'TransNorte Lda',   'Seguradora A', 'Inundação',  'Moderado', '2026-09-08', 'Av. Marginal, Maputo',                 'Veículo submerso após chuvas intensas',            92400,  85000,  'Resolvido','Insp. Chissano'],
            ['SN-2026-000081', 'AJK-120-MC', 'Hélder Sitoe',     'Seguradora C', 'Incêndio',   'Grave',    '2026-09-05', 'Av. Acordos de Lusaka',                'Incêndio no compartimento do motor',               380000, null,   'Aberto',    'Insp. Sitoe'],
            ['SN-2026-000080', 'ADT-873-MP', 'Aníbal Chissano',  'Seguradora B', 'Furto',      'Grave',    '2026-09-01', 'Bairro do Aeroporto, Maputo',          'Furto de peças e acessórios',                      560000, 520000, 'Resolvido','Insp. Nhaca'],
            ['SN-2026-000079', 'AEF-649-MC', 'Fátima Machava',   'Seguradora A', 'Colisão',    'Leve',     '2026-08-28', 'Av. do Trabalho, Maputo',              'Colisão ligeira em semáforo',                      48200,  45000,  'Resolvido','Insp. Cumbe'],
            ['SN-2026-000078', 'AGH-231-MP', 'Rui Ndlovu',       'Seguradora C', 'Vandalismo', 'Moderado', '2026-08-25', 'Av. Vladimir Lenine',                  'Danos provocados em acto de vandalismo',           22600,  null,   'Em análise','Insp. Tembe'],
            ['SN-2026-000077', 'ALM-507-MC', 'Teresa Bila',      'Seguradora B', 'Colisão',    'Moderado', '2026-08-22', 'Av. 24 de Julho, Maputo',              'Colisão com veículo em ultrapassagem',             78900,  null,   'Aberto',    'Insp. Chissano'],
            ['SN-2026-000076', 'AKP-412-MP', 'Jorge Machel',     'Seguradora A', 'Furto',      'Grave',    '2026-08-18', 'Estrada de Catembe',                   'Furto do veículo em viagem',                       395000, 380000, 'Resolvido','Insp. Nhaca'],
        ];

        foreach ($exemplos as $e) {
            // Tentar encontrar apólice pela matrícula
            $apolice = Apolice::whereHas('veiculo', fn ($q) => $q->where('matricula', $e[1]))
                ->first();

            $s = Sinistro::create([
                'numero'          => $e[0],
                'apolice_id'      => $apolice?->id,
                'matricula'       => $e[1],
                'segurado'        => $e[2],
                'seguradora'      => $e[3],
                'tipo'            => $e[4],
                'gravidade'       => $e[5],
                'data'            => $e[6],
                'local'           => $e[7],
                'descricao'       => $e[8],
                'valor_estimado'  => $e[9],
                'valor_aprovado'  => $e[10],
                'estado'          => $e[11],
                'responsavel'     => $e[12],
            ]);
        }
    }
}