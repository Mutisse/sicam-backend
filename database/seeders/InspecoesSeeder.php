<?php

namespace Database\Seeders;

use App\Models\Inspecao;
use App\Models\Veiculo;
use App\Models\Posto;
use Illuminate\Database\Seeder;

class InspecoesSeeder extends Seeder
{
    public function run(): void
    {
        $veiculos = Veiculo::all();
        $postos = Posto::all();

        if ($veiculos->isEmpty()) {
            $this->command->warn('Sem veículos na BD. Corre o PostoVeiculoPatrulhaSeeder primeiro.');
            return;
        }

        foreach ($veiculos as $veiculo) {
            // Cada veículo tem 1-3 inspecções
            $numInspecoes = random_int(1, 3);

            for ($i = 0; $i < $numInspecoes; $i++) {
                $data = now()->subDays(random_int(1, 400));

                // Determinar estado com base em dados aleatórios
                $rand = random_int(1, 100);
                if ($rand <= 68) {
                    $resultado = 'Aprovada';
                    $estado = 'valida';
                } elseif ($rand <= 85) {
                    $resultado = 'Reprovada';
                    $estado = 'expirada';
                } else {
                    $resultado = 'Pendente';
                    $estado = 'pendente';
                }

                Inspecao::create([
                    'veiculo_id'  => $veiculo->id,
                    'posto_id'    => $postos->isNotEmpty() ? $postos->random()->id : null,
                    'data'        => $data,
                    'validade'    => $data->copy()->addYear(),
                    'resultado'   => $resultado,
                    'estado'      => $estado,
                    'observacoes' => null,
                    'inspetor'    => 'Inspetor ' . random_int(1, 20),
                ]);
            }
        }

        $this->command->info('Inspecções criadas com sucesso.');
    }
}