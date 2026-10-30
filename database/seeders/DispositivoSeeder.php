<?php

namespace Database\Seeders;

use App\Models\Dispositivo;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DispositivoSeeder extends Seeder
{
    public function run(): void
    {
        $exemplos = [
            [
                'serie' => 'SN-AGT-001', 'modelo' => 'Zebra TC22',
                'agente' => 'Agente Chissano', 'estado' => 'Ativo',
                'versao_app' => '2.5.0',
                'ultima_sincronizacao' => Carbon::now()->subHours(2),
            ],
            [
                'serie' => 'SN-AGT-002', 'modelo' => 'Zebra TC22',
                'agente' => 'Agente Nhaca', 'estado' => 'Ativo',
                'versao_app' => '2.5.0',
                'ultima_sincronizacao' => Carbon::now()->subHour(),
            ],
            [
                'serie' => 'SN-AGT-004', 'modelo' => 'Samsung XCover 6',
                'agente' => 'Agente Sitoe', 'estado' => 'Ativo',
                'versao_app' => '2.4.1',
                'ultima_sincronizacao' => Carbon::now()->subHours(5),
            ],
            [
                'serie' => 'SN-AGT-005', 'modelo' => 'Samsung XCover 6',
                'agente' => null, 'estado' => 'Por registar',
                'versao_app' => '2.4.1',
                'ultima_sincronizacao' => null,
            ],
            [
                'serie' => 'SN-AGT-007', 'modelo' => 'Zebra TC22',
                'agente' => 'Agente Cumbe', 'estado' => 'Ativo',
                'versao_app' => '2.5.0',
                'ultima_sincronizacao' => Carbon::now()->subHours(26),
            ],
            [
                'serie' => 'SN-AGT-008', 'modelo' => 'Zebra TC22',
                'agente' => 'Agente Bila', 'estado' => 'Ativo',
                'versao_app' => '2.3.8',
                'ultima_sincronizacao' => Carbon::now()->subHours(2),
            ],
            [
                'serie' => 'SN-AGT-010', 'modelo' => 'Zebra TC22',
                'agente' => null, 'estado' => 'Por registar',
                'versao_app' => '2.4.1',
                'ultima_sincronizacao' => null,
            ],
            [
                'serie' => 'SN-AGT-011', 'modelo' => 'Samsung XCover 6',
                'agente' => 'Agente Tembe', 'estado' => 'Ativo',
                'versao_app' => '2.3.8',
                'ultima_sincronizacao' => Carbon::now()->subHours(3),
            ],
            [
                'serie' => 'SN-CHF-001', 'modelo' => 'Zebra TC22',
                'agente' => 'Chefe Mabunda', 'estado' => 'Ativo',
                'versao_app' => '2.5.0',
                'ultima_sincronizacao' => Carbon::now()->subHours(4),
            ],
            [
                'serie' => 'SN-CHF-002', 'modelo' => 'Zebra TC22',
                'agente' => 'Chefe Zandamela', 'estado' => 'Ativo',
                'versao_app' => '2.4.1',
                'ultima_sincronizacao' => Carbon::now()->subHour(),
            ],
        ];

        foreach ($exemplos as $e) {
            Dispositivo::create($e);
        }
    }
}