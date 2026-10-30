<?php

namespace Database\Seeders;

use App\Models\Noticia;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class NoticiaSeeder extends Seeder
{
    public function run(): void
    {
        $hoje = Carbon::today();

        $exemplos = [
            [
                'titulo' => 'Nova campanha de inspecção em Maputo',
                'resumo' => 'Postos móveis vão estar em 5 locais da cidade durante Outubro.',
                'categoria' => 'Inspecção',
                'autor' => 'INATRO',
                'data_publicacao' => $hoje->copy()->subDays(1),
                'destaque' => true,
            ],
            [
                'titulo' => 'Seguro obrigatório: prazos 2026',
                'resumo' => 'Novos valores e datas de renovação já disponíveis.',
                'categoria' => 'Seguro',
                'autor' => 'ISSM',
                'data_publicacao' => $hoje->copy()->subDays(4),
                'destaque' => true,
            ],
            [
                'titulo' => 'Multas: como pagar online',
                'resumo' => 'Passo-a-passo para liquidar multas pela plataforma SICAM.',
                'categoria' => 'Multas',
                'autor' => 'GESTMUL',
                'data_publicacao' => $hoje->copy()->subDays(8),
                'destaque' => false,
            ],
            [
                'titulo' => 'Nova lei de trânsito entra em vigor',
                'resumo' => 'Alterações às regras de circulação e valores de coimas.',
                'categoria' => 'Legislação',
                'autor' => 'SICAM',
                'data_publicacao' => $hoje->copy()->subDays(12),
                'destaque' => false,
            ],
            [
                'titulo' => 'Postos de atendimento alargam horário',
                'resumo' => 'Atendimento ao público passa a funcionar das 7h às 19h.',
                'categoria' => 'Serviços',
                'autor' => 'SICAM',
                'data_publicacao' => $hoje->copy()->subDays(15),
                'destaque' => false,
            ],
        ];

        foreach ($exemplos as $e) {
            Noticia::create($e);
        }
    }
}