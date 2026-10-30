<?php

namespace Database\Seeders;

use App\Models\Multa;
use App\Models\Posto;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MultasCidadaoSeeder extends Seeder
{
    /**
     * Cria multas específicas para dois cidadãos de teste:
     *  - Maria da Conceição Nhaca  (carta 110298765, NIF 110298765002B)
     *  - João Manuel Chissano      (carta 110100234, NIF 110100234001A)
     *
     * Cobre TODOS os estados (Paga / Pendente / Contestada) e
     * TODAS as infrações (Excesso velocidade, Seguro expirado,
     * Inspecção atraso, Documentos, Outros).
     */
    public function run(): void
    {
        // ========================================================
        // 1. Atribuir veículos aos proprietários
        // ========================================================
        $veiculosMaria = ['ABR-771-MP', 'AFC-205-MC'];
        $veiculosJoao  = ['AGL-482-MC', 'ACD-918-MP'];

        DB::table('veiculos')
            ->whereIn('matricula', $veiculosMaria)
            ->update([
                'proprietario_nome'      => 'Maria da Conceição Nhaca',
                'proprietario_numero_id' => '110298765002B',
            ]);

        DB::table('veiculos')
            ->whereIn('matricula', $veiculosJoao)
            ->update([
                'proprietario_nome'      => 'João Manuel Chissano',
                'proprietario_numero_id' => '110100234001A',
            ]);

        $this->command->info('Veículos atribuídos:');
        $this->command->info('  Maria → ' . implode(', ', $veiculosMaria));
        $this->command->info('  João  → ' . implode(', ', $veiculosJoao));

        // ========================================================
        // 2. Recolher IDs de veículos, agentes e postos
        // ========================================================
        $idsMaria = Veiculo::whereIn('matricula', $veiculosMaria)->pluck('id')->toArray();
        $idsJoao  = Veiculo::whereIn('matricula', $veiculosJoao)->pluck('id')->toArray();

        $agentes = User::all();
        $postos  = Posto::all();

        // ========================================================
        // 3. Dados fixos das multas
        // ========================================================
        $provincias = [
            'Maputo Cidade',
            'Maputo Província',
            'Gaza',
            'Inhambane',
            'Sofala',
        ];

        // Cada multa = [infracao, valor_base, estado]
        // Cobre TODOS os estados e TODAS as infrações
        $multsTemplate = [
            ['Excesso velocidade', 3000, 'Paga'],
            ['Seguro expirado',    2500, 'Paga'],
            ['Inspecção atraso',   2000, 'Pendente'],
            ['Documentos',         1500, 'Pendente'],
            ['Outros',             1000, 'Contestada'],
        ];

        // ========================================================
        // 4. Criar as multas para cada cidadão
        // ========================================================
        $this->criarMultas(
            cidadao: 'Maria da Conceição Nhaca',
            prefixo: 'M',
            veiculoIds: $idsMaria,
            multsTemplate: $multsTemplate,
            provincias: $provincias,
            agentes: $agentes,
            postos: $postos,
        );

        $this->criarMultas(
            cidadao: 'João Manuel Chissano',
            prefixo: 'J',
            veiculoIds: $idsJoao,
            multsTemplate: $multsTemplate,
            provincias: $provincias,
            agentes: $agentes,
            postos: $postos,
        );

        $this->command->info('');
        $this->command->info('✅ MultasCidadaoSeeder concluído.');
        $this->command->info('   Maria: 5 multas (2 Pagas, 2 Pendentes, 1 Contestada)');
        $this->command->info('   João:  5 multas (2 Pagas, 2 Pendentes, 1 Contestada)');
    }

    /**
     * Cria 5 multas para um cidadão.
     */
    private function criarMultas(
        string $cidadao,
        string $prefixo,
        array $veiculoIds,
        array $multsTemplate,
        array $provincias,
        $agentes,
        $postos,
    ): void {
        if (empty($veiculoIds)) {
            $this->command->warn("Sem veículos para $cidadao — a saltar.");
            return;
        }

        foreach ($multsTemplate as $i => [$infracao, $valorBase, $estado]) {
            $veiculoId = $veiculoIds[array_rand($veiculoIds)];
            $agente    = $agentes->isNotEmpty() ? $agentes->random() : null;
            $posto     = $postos->isNotEmpty() ? $postos->random() : null;

            // Datas coerentes com o estado
            $data = now()->subDays(random_int(5, 90));

            $dataPagamento = null;
            if ($estado === 'Paga') {
                // Paga sempre DEPOIS da data da multa
                $dataPagamento = (clone $data)->addDays(random_int(1, 15));
                if ($dataPagamento->isFuture()) {
                    $dataPagamento = now()->subDays(random_int(0, 3));
                }
            }

            Multa::create([
                'numero'         => 'ML-' . $prefixo . '-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'veiculo_id'     => $veiculoId,
                'agente_id'      => $agente?->id,
                'posto_id'       => $posto?->id,
                'infracao'       => $infracao,
                'provincia'      => $provincias[array_rand($provincias)],
                'valor'          => $valorBase + random_int(-200, 200),
                'estado'         => $estado,
                'data'           => $data,
                'data_pagamento' => $dataPagamento,
                'observacoes'    => null,
            ]);
        }
    }
}