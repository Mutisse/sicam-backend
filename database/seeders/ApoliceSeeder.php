<?php

namespace Database\Seeders;

use App\Models\Apolice;
use App\Models\Veiculo;
use Illuminate\Database\Seeder;

class ApoliceSeeder extends Seeder
{
    public function run(): void
    {
        $apolices = Apolice::orderBy('id')->get();

        $nomes = [
            'Carlos Nhaca', 'Pedro Cumbe', 'Rita Tembe', 'Sofia Macuácua',
            'Luísa Muianga', 'TransNorte Lda', 'Hélder Sitoe',
            'Aníbal Chissano', 'Fátima Machava', 'Rui Ndlovu', 'Teresa Bila',
        ];
        $nifs = [
            '345678901', '678901234', '789012345', '901234567',
            '112233445', '223344556', '556677889',
            '667788990', '778899001', '889900112', '990011223',
        ];
        $premios = [18500, 42800, 16200, 28400, 58900, 17800, 26100, 45600, 19200, 29800, 52400];

        foreach ($apolices as $i => $a) {
            $a->update([
                'segurado'    => $nomes[$i] ?? 'Segurado #' . $a->id,
                'nif'         => $nifs[$i] ?? null,
                'premio'      => $premios[$i] ?? 0,
                'observacoes' => $a->observacoes ?: null,
            ]);
        }
    }
}