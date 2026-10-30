<?php

namespace Database\Seeders;

use App\Models\Multa;
use App\Models\Posto;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Database\Seeder;

class MultasSeeder extends Seeder
{
    public function run(): void
    {
        $veiculos = Veiculo::all();
        $agentes = User::all();
        $postos = Posto::all();

        if ($veiculos->isEmpty()) {
            $this->command->warn('Sem veículos na BD.');
            return;
        }

        $infracoes = [
            'Excesso velocidade',
            'Seguro expirado',
            'Inspecção atraso',
            'Documentos',
            'Outros',
        ];

        // Valores típicos por infracção
        $valores = [
            'Excesso velocidade' => 3000,
            'Seguro expirado'    => 2500,
            'Inspecção atraso'   => 2000,
            'Documentos'         => 1500,
            'Outros'             => 1000,
        ];

        $provincias = [
            'Maputo Cidade',
            'Maputo Província',
            'Gaza',
            'Inhambane',
            'Sofala',
        ];

        $contador = 0;

        // Criar 40 multas
        for ($i = 0; $i < 40; $i++) {
            $veiculo = $veiculos->random();
            $agente = $agentes->isNotEmpty() ? $agentes->random() : null;
            $posto = $postos->isNotEmpty() ? $postos->random() : null;
            $infracao = $infracoes[array_rand($infracoes)];

            // Distribuição de estados
            $rand = random_int(1, 100);
            if ($rand <= 60) {
                $estado = 'Paga';
                $dataPagamento = now()->subDays(random_int(0, 30));
            } elseif ($rand <= 85) {
                $estado = 'Pendente';
                $dataPagamento = null;
            } else {
                $estado = 'Contestada';
                $dataPagamento = null;
            }

            $data = now()->subDays(random_int(0, 60));

            Multa::create([
                'numero'         => 'ML-' . str_pad($contador + 1, 3, '0', STR_PAD_LEFT),
                'veiculo_id'     => $veiculo->id,
                'agente_id'      => $agente?->id,
                'posto_id'       => $posto?->id,
                'infracao'       => $infracao,
                'provincia'      => $provincias[array_rand($provincias)],
                'valor'          => $valores[$infracao] + random_int(-500, 500),
                'estado'         => $estado,
                'data'           => $data,
                'data_pagamento' => $estado === 'Paga' ? $dataPagamento : null,
                'observacoes'    => null,
            ]);

            $contador++;
        }

        $this->command->info("Multas criadas: $contador");
    }
}