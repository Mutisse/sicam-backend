<?php

namespace Database\Seeders;

use App\Models\Multa;
use App\Models\Recibo;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReciboSeeder extends Seeder
{
    public function run(): void
    {
        // Pega nas multas com estado 'Paga'
        $multasPagas = Multa::where('estado', 'Paga')->limit(10)->get();

        if ($multasPagas->isEmpty()) {
            $this->command->warn('Nenhuma multa paga encontrada.');
            return;
        }

        $metodos = ['Dinheiro', 'M-Pesa', 'Transferência', 'Multibanco'];
        $nomes = [
            'João Chissano', 'Maria Nhaca', 'Pedro Cumbe', 'Ana Sitoe',
            'Carlos Tembe', 'Fátima Macuácua', 'Rui Ndlovu', 'Hélder Sitoe',
            'Sofia Machava', 'Jorge Machel',
        ];

        $seq = 1;

        foreach ($multasPagas as $m) {
            $metodo = $metodos[array_rand($metodos)];
            $data = Carbon::parse($m->data ?? now())->addDays(rand(1, 5));

            Recibo::create([
                'numero'     => sprintf('RC-%d-%06d', $data->year, $seq++),
                'multa_id'   => $m->id,
                'metodo'     => $metodo,
                'pagador'    => $nomes[array_rand($nomes)],
                'referencia' => in_array($metodo, ['M-Pesa', 'Transferência'])
                    ? '+258 84 ' . rand(100, 999) . ' ' . rand(1000, 9999)
                    : null,
                'valor'      => $m->valor,
                'estado'     => rand(0, 9) > 2 ? 'Confirmado' : 'Pendente',
                'created_at' => $data,
                'updated_at' => $data,
            ]);
        }

        $this->command->info('Criados ' . $seq - 1 . ' recibos.');
    }
}