<?php

namespace Database\Seeders;

use App\Models\Contestacao;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ContestacaoSeeder extends Seeder
{
    public function run(): void
    {
        $hoje = Carbon::today();

        $exemplos = [
            [
                'numero' => 'CT-2026-000006', 'multa_codigo' => 'MT-26-000120',
                'matricula' => 'ACD-918-MP', 'infracao' => 'Documentos em falta',
                'valor_multa' => 1800,
                'contestante' => 'Sofia Macuácua', 'nif' => '901234567',
                'contacto' => '+258 84 555 6677',
                'motivo' => 'Erro de identificação do veículo',
                'descricao' => 'A matrícula não corresponde ao meu veículo.',
                'prioridade' => 'Alta',
                'data_submissao' => $hoje->copy()->subDays(20),
                'prazo_limite'   => $hoje->copy()->addDays(10),
                'estado' => 'Pendente',
            ],
            [
                'numero' => 'CT-2026-000005', 'multa_codigo' => 'MT-26-000115',
                'matricula' => 'AEF-649-MC', 'infracao' => 'Documentos em falta',
                'valor_multa' => 1800,
                'contestante' => 'Fátima Machava', 'nif' => '778899001',
                'contacto' => '+258 84 777 8899',
                'motivo' => 'Não era o condutor',
                'descricao' => 'O veículo estava em uso por outra pessoa nesse dia.',
                'prioridade' => 'Média',
                'data_submissao' => $hoje->copy()->subDays(21),
                'prazo_limite'   => $hoje->copy()->addDays(9),
                'estado' => 'Em análise',
            ],
            [
                'numero' => 'CT-2026-000004', 'multa_codigo' => 'MT-26-000110',
                'matricula' => 'AJK-120-MC', 'infracao' => 'Seguro expirado',
                'valor_multa' => 2800,
                'contestante' => 'Hélder Sitoe', 'nif' => '556677889',
                'contacto' => '+258 82 111 2233',
                'motivo' => 'Erro de dados na multa',
                'descricao' => 'O seguro estava renovado no dia da infração.',
                'prioridade' => 'Alta',
                'data_submissao' => $hoje->copy()->subDays(23),
                'prazo_limite'   => $hoje->copy()->addDays(7),
                'estado' => 'Aceite',
                'decisao' => 'Comprovativo de renovação verificado. Multa anulada.',
                'decisao_em' => $hoje->copy()->subDays(21)->setTime(10, 30),
                'decisao_por' => 'Dr. Carlos Nhaca',
            ],
            [
                'numero' => 'CT-2026-000003', 'multa_codigo' => 'MT-26-000105',
                'matricula' => 'ALM-507-MC', 'infracao' => 'Excesso de velocidade',
                'valor_multa' => 3500,
                'contestante' => 'Teresa Bila', 'nif' => '990011223',
                'contacto' => '+258 84 999 0011',
                'motivo' => 'Multa duplicada',
                'descricao' => 'Já paguei esta multa anteriormente.',
                'prioridade' => 'Média',
                'data_submissao' => $hoje->copy()->subDays(25),
                'prazo_limite'   => $hoje->copy()->addDays(5),
                'estado' => 'Rejeitada',
                'decisao' => 'Comprovativo de pagamento não apresentado.',
                'decisao_em' => $hoje->copy()->subDays(22)->setTime(16, 0),
                'decisao_por' => 'Dra. Rita Tembe',
            ],
            [
                'numero' => 'CT-2026-000002', 'multa_codigo' => 'MT-26-000098',
                'matricula' => 'AKP-412-MP', 'infracao' => 'Inspecção em atraso',
                'valor_multa' => 2200,
                'contestante' => 'Jorge Machel', 'nif' => '101112131',
                'contacto' => '+258 82 555 6677',
                'motivo' => 'Veículo vendido',
                'descricao' => 'Vendi o veículo antes da data da infração.',
                'prioridade' => 'Baixa',
                'data_submissao' => $hoje->copy()->subDays(28),
                'prazo_limite'   => $hoje->copy()->addDays(2),
                'estado' => 'Aceite',
                'decisao' => 'Documento de venda verificado. Multa anulada.',
                'decisao_em' => $hoje->copy()->subDays(25)->setTime(9, 0),
                'decisao_por' => 'Dr. Pedro Cumbe',
            ],
            [
                'numero' => 'CT-2026-000001', 'multa_codigo' => 'MT-26-000090',
                'matricula' => 'AGL-482-MC', 'infracao' => 'Seguro expirado',
                'valor_multa' => 2800,
                'contestante' => 'Carlos Nhaca', 'nif' => '345678901',
                'contacto' => '+258 84 111 2233',
                'motivo' => 'Prescrição',
                'descricao' => 'A multa foi emitida há mais de 2 anos.',
                'prioridade' => 'Média',
                'data_submissao' => $hoje->copy()->subDays(30),
                'prazo_limite'   => $hoje->copy()->addDays(1),
                'estado' => 'Pendente',
            ],
        ];

        foreach ($exemplos as $e) {
            Contestacao::create($e);
        }
    }
}