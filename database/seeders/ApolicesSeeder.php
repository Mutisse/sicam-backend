<?php

namespace Database\Seeders;

use App\Models\Apolice;
use App\Models\Veiculo;
use Illuminate\Database\Seeder;

class ApolicesSeeder extends Seeder
{
    public function run(): void
    {
        $veiculos = Veiculo::all();
        $seguradoras = [
            'EMOSE',
            'Seguradora A',
            'Seguradora B',
            'Hollard Moçambique',
            'Global Alliance',
        ];

        if ($veiculos->isEmpty()) {
            $this->command->warn('Sem veículos na BD.');
            return;
        }

        $contador = 1;

        foreach ($veiculos as $veiculo) {
            $inicio = now()->subMonths(random_int(1, 18));
            $validade = $inicio->copy()->addYear();

            // Estado conforme a validade
            if ($validade->isPast()) {
                $estado = 'Expirada';
            } elseif (random_int(1, 100) <= 5) {
                $estado = 'Sinistro';
            } else {
                $estado = 'Activa';
            }

            Apolice::create([
                'numero'      => 'AP-' . str_pad($contador, 3, '0', STR_PAD_LEFT),
                'veiculo_id'  => $veiculo->id,
                'seguradora'  => $seguradoras[array_rand($seguradoras)],
                'tipo'        => random_int(1, 2) === 1 ? 'Terceiros' : 'Contra-todos',
                'inicio'      => $inicio,
                'validade'    => $validade,
                'estado'      => $estado,
            ]);

            $contador++;
        }

        $this->command->info('Apólices criadas: ' . ($contador - 1));
    }
}