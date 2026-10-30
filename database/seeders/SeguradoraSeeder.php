<?php

namespace Database\Seeders;

use App\Models\Seguradora;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SeguradoraSeeder extends Seeder
{
    public function run(): void
    {
        $exemplos = [
            [
                'codigo' => 'SEG-001', 'sigla' => 'SEG-A',
                'nome' => 'Seguradora A, S.A.', 'nif' => '400123456',
                'categoria' => 'Nacional',
                'email' => 'geral@seguradoraA.co.mz', 'telefone' => '+258 21 300 100',
                'website' => 'www.seguradoraA.co.mz',
                'endereco' => 'Av. 25 de Setembro, 1234', 'cidade' => 'Maputo', 'provincia' => 'Maputo Cidade',
                'responsavel' => 'Dr. João Machava', 'contacto_responsavel' => '+258 84 100 2000',
                'numero_apoles' => 842, 'numero_sinistros' => 156,
                'estado' => 'Activa', 'registada_em' => '2018-03-12 09:00:00',
            ],
            [
                'codigo' => 'SEG-002', 'sigla' => 'SEG-B',
                'nome' => 'Seguradora B, Lda.', 'nif' => '400234567',
                'categoria' => 'Nacional',
                'email' => 'info@seguradoraB.co.mz', 'telefone' => '+258 21 300 200',
                'endereco' => 'Av. Julius Nyerere, 456', 'cidade' => 'Maputo', 'provincia' => 'Maputo Cidade',
                'responsavel' => 'Dra. Ana Sitoe', 'contacto_responsavel' => '+258 82 200 3000',
                'numero_apoles' => 610, 'numero_sinistros' => 98,
                'estado' => 'Activa', 'registada_em' => '2019-07-05 10:30:00',
            ],
            [
                'codigo' => 'SEG-003', 'sigla' => 'SEG-C',
                'nome' => 'Seguradora C Internacional', 'nif' => '400345678',
                'categoria' => 'Internacional',
                'email' => 'mocambique@segC.com', 'telefone' => '+258 21 300 300',
                'website' => 'www.segC.com',
                'endereco' => 'Av. Vladimir Lenine, 789', 'cidade' => 'Maputo', 'provincia' => 'Maputo Cidade',
                'responsavel' => 'Sr. Michael Smith', 'contacto_responsavel' => '+258 84 300 4000',
                'numero_apoles' => 456, 'numero_sinistros' => 72,
                'estado' => 'Activa', 'registada_em' => '2020-11-20 14:15:00',
            ],
            [
                'codigo' => 'SEG-004', 'sigla' => 'SEG-D',
                'nome' => 'Seguradora D, S.A.', 'nif' => '400456789',
                'categoria' => 'Nacional',
                'email' => 'contacto@segD.co.mz', 'telefone' => '+258 21 300 400',
                'endereco' => 'Av. Marginal, 321', 'cidade' => 'Matola', 'provincia' => 'Maputo Província',
                'responsavel' => 'Dr. Carlos Nhaca', 'contacto_responsavel' => '+258 87 400 5000',
                'numero_apoles' => 128, 'numero_sinistros' => 14,
                'estado' => 'Suspensa',
                'observacoes' => 'Suspensa temporariamente por falta de liquidez',
                'registada_em' => '2021-02-15 11:00:00',
            ],
            [
                'codigo' => 'SEG-005', 'sigla' => 'MUT-TRANS',
                'nome' => 'Mútua dos Transportadores', 'nif' => '400567890',
                'categoria' => 'Mútua',
                'email' => 'geral@mutuatransportadores.co.mz', 'telefone' => '+258 21 300 500',
                'endereco' => 'Av. das Indústrias, 55', 'cidade' => 'Beira', 'provincia' => 'Sofala',
                'responsavel' => 'Eng. Fátima Macuácua', 'contacto_responsavel' => '+258 82 500 6000',
                'numero_apoles' => 234, 'numero_sinistros' => 41,
                'estado' => 'Activa', 'registada_em' => '2022-09-03 08:45:00',
            ],
            [
                'codigo' => 'SEG-006', 'sigla' => 'SEG-E',
                'nome' => 'Seguradora E, Lda.', 'nif' => '400678901',
                'categoria' => 'Nacional',
                'email' => 'atendimento@segE.co.mz', 'telefone' => '+258 21 300 600',
                'endereco' => 'Av. do Trabalho, 88', 'cidade' => 'Nampula', 'provincia' => 'Nampula',
                'responsavel' => 'Dr. Rui Ndlovu', 'contacto_responsavel' => '+258 84 600 7000',
                'numero_apoles' => 76, 'numero_sinistros' => 8,
                'estado' => 'Inactiva',
                'observacoes' => 'Encerrou operações em 2024',
                'registada_em' => '2023-05-10 15:20:00',
            ],
        ];

        foreach ($exemplos as $e) {
            Seguradora::create($e);
        }
    }
}