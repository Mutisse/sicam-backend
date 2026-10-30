<?php

namespace Database\Seeders;

use App\Models\CartaConducao;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CartaConducaoSeeder extends Seeder
{
    public function run(): void
    {
        $exemplos = [
            [
                'numero' => '110100234',
                'nomes' => 'JOAO MANUEL',
                'nome_completo' => 'João Manuel Chissano',
                'nif' => '110100234001A',  // BI/NIF real (para ligar ao veículo)
                'data_nascimento' => '1985-03-15',
                'data_emissao' => '2020-06-10',
                'data_validade' => '2030-06-10',
                'data_primeira_emissao' => '2005-08-20',
                'codigo_carta' => 'B0',
                'categorias' => ['A1', 'B'],
                'restricoes' => '',
                'restricoes_condutor' => '',
                'sexo' => 'M',
                'nacionalidade' => 'Moçambicana',
                'estado' => 'Válida',
            ],
            [
                'numero' => '110298765',
                'nomes' => 'MARIA CONCEICAO',
                'nome_completo' => 'Maria da Conceição Nhaca',
                'nif' => '110298765002B',
                'data_nascimento' => '1990-11-02',
                'data_emissao' => '2022-01-15',
                'data_validade' => '2032-01-15',
                'data_primeira_emissao' => '2012-03-08',
                'codigo_carta' => 'B0',
                'categorias' => ['B'],
                'sexo' => 'F',
                'nacionalidade' => 'Moçambicana',
                'estado' => 'Válida',
            ],
        ];

        foreach ($exemplos as $e) {
            CartaConducao::create($e);
        }
    }
}