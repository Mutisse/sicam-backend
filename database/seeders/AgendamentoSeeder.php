<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AgendamentoSeeder extends Seeder
{
    public function run(): void
    {
        $hoje = Carbon::today();

        $exemplos = [
            [
                'matricula'    => 'AFC-205-MC',
                'marca'        => 'Mazda',
                'modelo'       => '3',
                'proprietario' => 'Carlos Nhaca',
                'nif'          => '345678901',
                'telefone'     => '+258 84 111 2233',
                'data'         => $hoje->copy()->addDay(),
                'hora'         => '09:00',
                'duracao'      => 30,
                'tipo'         => 'Periódica',
                'posto'        => 'Posto Baixa',
                'inspetor'     => 'Insp. Chissano',
                'estado'       => 'Confirmado',
            ],
            [
                'matricula'    => 'ABX-556-MP',
                'marca'        => 'Toyota',
                'modelo'       => 'Corolla',
                'proprietario' => 'Pedro Cumbe',
                'nif'          => '678901234',
                'telefone'     => '+258 82 333 4455',
                'data'         => $hoje->copy()->addDay(),
                'hora'         => '10:30',
                'duracao'      => 30,
                'tipo'         => 'Periódica',
                'posto'        => 'Posto Matola',
                'inspetor'     => 'Insp. Nhaca',
                'estado'       => 'Pendente',
            ],
            [
                'matricula'    => 'AJK-120-MC',
                'marca'        => 'Mazda',
                'modelo'       => 'CX-5',
                'proprietario' => 'Rita Tembe',
                'nif'          => '789012345',
                'telefone'     => '+258 84 555 6677',
                'data'         => $hoje->copy()->addDays(2),
                'hora'         => '14:00',
                'duracao'      => 30,
                'tipo'         => 'Reinspecção',
                'posto'        => 'Posto Baixa',
                'inspetor'     => 'Insp. Sitoe',
                'estado'       => 'Confirmado',
                'observacoes'  => 'Reinspecção após reprovação em 22/09',
            ],
            [
                'matricula'    => 'AEF-649-MC',
                'marca'        => 'Honda',
                'modelo'       => 'Fit',
                'proprietario' => 'Sofia Macuácua',
                'nif'          => '901234567',
                'telefone'     => '+258 87 777 8899',
                'data'         => $hoje->copy()->addDays(2),
                'hora'         => '15:30',
                'duracao'      => 30,
                'tipo'         => 'Periódica',
                'posto'        => 'Posto Machava',
                'inspetor'     => 'Insp. Cumbe',
                'estado'       => 'Pendente',
            ],
            [
                'matricula'    => 'AKP-412-MP',
                'marca'        => 'Mercedes',
                'modelo'       => 'Actros',
                'proprietario' => 'TransNorte Lda',
                'nif'          => '223344556',
                'telefone'     => '+258 21 000 1122',
                'data'         => $hoje->copy()->addDays(3),
                'hora'         => '09:30',
                'duracao'      => 60,
                'tipo'         => 'Extraordinária',
                'posto'        => 'Posto Namaacha',
                'inspetor'     => 'Insp. Tembe',
                'estado'       => 'Confirmado',
            ],
            [
                'matricula'    => 'ALM-507-MC',
                'marca'        => 'Volkswagen',
                'modelo'       => 'Golf',
                'proprietario' => 'Luísa Muianga',
                'nif'          => '112233445',
                'telefone'     => '+258 84 999 0011',
                'data'         => $hoje->copy()->addDays(4),
                'hora'         => '16:00',
                'duracao'      => 30,
                'tipo'         => 'Periódica',
                'posto'        => 'Posto Baixa',
                'inspetor'     => 'Insp. Chissano',
                'estado'       => 'Cancelado',
                'observacoes'  => 'Cliente pediu cancelamento',
            ],
            // Dois agendamentos para HOJE (para o KPI "hoje" contar)
            [
                'matricula'    => 'AJP-201-MC',
                'marca'        => 'Toyota',
                'modelo'       => 'Hilux',
                'proprietario' => 'João Silva',
                'nif'          => '123456789',
                'telefone'     => '+258 84 100 2000',
                'data'         => $hoje->copy(),
                'hora'         => '08:30',
                'duracao'      => 45,
                'tipo'         => 'Periódica',
                'posto'        => 'Posto Baixa',
                'inspetor'     => 'Insp. Chissano',
                'estado'       => 'Confirmado',
            ],
            [
                'matricula'    => 'ABZ-899-MC',
                'marca'        => 'Nissan',
                'modelo'       => 'Navara',
                'proprietario' => 'Maria Machava',
                'nif'          => '987654321',
                'telefone'     => '+258 82 300 4000',
                'data'         => $hoje->copy(),
                'hora'         => '11:00',
                'duracao'      => 30,
                'tipo'         => 'Periódica',
                'posto'        => 'Posto Matola',
                'inspetor'     => 'Insp. Nhaca',
                'estado'       => 'Pendente',
            ],
        ];

        foreach ($exemplos as $e) {
            $postoId = \App\Models\Posto::where('nome', $e['posto'])->value('id');

            $a = Agendamento::create([
                'veiculo_id'   => null,
                'matricula'    => $e['matricula'],
                'marca'        => $e['marca'],
                'modelo'       => $e['modelo'],
                'proprietario' => $e['proprietario'],
                'nif'          => $e['nif'],
                'telefone'     => $e['telefone'],
                'data'         => $e['data']->toDateString(),
                'hora'         => $e['hora'],
                'duracao'      => $e['duracao'],
                'tipo'         => $e['tipo'],
                'posto_id'     => $postoId,
                'posto_nome'   => $e['posto'],
                'inspetor'     => $e['inspetor'],
                'estado'       => $e['estado'],
                'observacoes'  => $e['observacoes'] ?? null,
                'criado_por'   => null,
            ]);

            $ano = $e['data']->format('Y');
            $a->update([
                'codigo' => sprintf('AGD-%s-%06d', $ano, $a->id),
            ]);
        }
    }
}