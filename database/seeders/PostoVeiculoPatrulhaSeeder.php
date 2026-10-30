<?php

namespace Database\Seeders;

use App\Models\Posto;
use App\Models\Veiculo;
use App\Models\Patrulha;
use App\Models\User;
use Illuminate\Database\Seeder;

class PostoVeiculoPatrulhaSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Postos ─────────────────────────────────────
        $postos = [
            ['nome' => 'Posto Baixa',     'codigo' => 'PS-BAIXA',   'provincia' => 'Maputo Cidade',    'distrito' => 'KaMpfumo'],
            ['nome' => 'Posto Matola',    'codigo' => 'PS-MATOLA',  'provincia' => 'Maputo Província', 'distrito' => 'Matola'],
            ['nome' => 'Posto KaMpfumo',  'codigo' => 'PS-KAMF',    'provincia' => 'Maputo Cidade',    'distrito' => 'KaMpfumo'],
            ['nome' => 'Posto Machava',   'codigo' => 'PS-MACHAVA', 'provincia' => 'Maputo Província', 'distrito' => 'Machava'],
            ['nome' => 'Posto Namaacha',  'codigo' => 'PS-NAMA',    'provincia' => 'Maputo Província', 'distrito' => 'Namaacha'],
        ];

        foreach ($postos as $p) {
            Posto::updateOrCreate(['codigo' => $p['codigo']], $p);
        }

        // ─── Veículos ───────────────────────────────────
        $veiculos = [
            ['matricula' => 'AGL-482-MC', 'marca' => 'Toyota',  'modelo' => 'Hilux',    'score' => 22, 'estado' => 'critico',   'provincia' => 'Maputo Cidade'],
            ['matricula' => 'ABR-771-MP', 'marca' => 'Nissan',  'modelo' => 'Navara',   'score' => 31, 'estado' => 'critico',   'provincia' => 'Maputo Província'],
            ['matricula' => 'AFC-205-MC', 'marca' => 'Ford',    'modelo' => 'Ranger',   'score' => 36, 'estado' => 'critico',   'provincia' => 'Maputo Cidade'],
            ['matricula' => 'ACD-918-MP', 'marca' => 'Mazda',   'modelo' => 'BT-50',    'score' => 41, 'estado' => 'atencao',   'provincia' => 'Maputo Província'],
            ['matricula' => 'AHN-304-MC', 'marca' => 'Toyota',  'modelo' => 'Corolla',  'score' => 44, 'estado' => 'atencao',   'provincia' => 'Maputo Cidade'],
            ['matricula' => 'ABX-556-MP', 'marca' => 'VW',      'modelo' => 'Polo',     'score' => 48, 'estado' => 'atencao',   'provincia' => 'Maputo Província'],
            ['matricula' => 'AXX-001-MC', 'marca' => 'Honda',   'modelo' => 'Civic',    'score' => 72, 'estado' => 'regular',   'provincia' => 'Maputo Cidade'],
            ['matricula' => 'AYY-002-MP', 'marca' => 'Hyundai', 'modelo' => 'i20',      'score' => 85, 'estado' => 'regular',   'provincia' => 'Maputo Província'],
            ['matricula' => 'AZZ-003-MC', 'marca' => 'Kia',     'modelo' => 'Sportage', 'score' => 91, 'estado' => 'regular',   'provincia' => 'Maputo Cidade'],
            ['matricula' => 'BBB-004-MP', 'marca' => '—',       'modelo' => '—',        'score' => 0,  'estado' => 'sem-dados', 'provincia' => 'Maputo Província'],
            ['matricula' => 'CCC-005-MC', 'marca' => '—',       'modelo' => '—',        'score' => 0,  'estado' => 'sem-dados', 'provincia' => 'Maputo Cidade'],
        ];

        foreach ($veiculos as $v) {
            Veiculo::updateOrCreate(['matricula' => $v['matricula']], $v);
        }

        // ─── Patrulhas ──────────────────────────────────
        $agente = User::first();
        $postoBaixa = Posto::where('codigo', 'PS-BAIXA')->first();

        if ($agente && $postoBaixa) {
            $patrulhas = [
                ['codigo' => 'PT-001', 'turno' => 'Matinal',  'viatura' => 'MAP-2290', 'local' => 'Baixa',            'estado' => 'Em curso'],
                ['codigo' => 'PT-002', 'turno' => 'Tarde',    'viatura' => 'MAP-4413', 'local' => 'EN4 — Namaacha',   'estado' => 'Concluída'],
                ['codigo' => 'PT-003', 'turno' => 'Nocturna', 'viatura' => 'MAP-1122', 'local' => 'Av. 25 Setembro',  'estado' => 'Pendente'],
            ];

            foreach ($patrulhas as $p) {
                Patrulha::updateOrCreate(
                    ['codigo' => $p['codigo']],
                    array_merge($p, [
                        'posto_id'  => $postoBaixa->id,
                        'agente_id' => $agente->id,
                        'inicio'    => now()->subHours(2),
                    ])
                );
            }
        }
    }
}