<?php

namespace Database\Seeders;

use App\Models\Aprovacao;
use Illuminate\Database\Seeder;

class AprovacaoSeeder extends Seeder
{
    public function run(): void
    {
        $exemplos = [
            [
                'tipo' => 'auto',
                'titulo' => 'Auto de Notícia — AGL-482-MC',
                'matricula' => 'AGL-482-MC',
                'agente' => 'Agente Chissano',
                'posto' => 'Posto Baixa',
                'prioridade' => 'Média',
                'estado' => 'pendente',
                'detalhes' => [
                    ['label' => 'Agente', 'valor' => 'Agente Chissano'],
                    ['label' => 'Infracção', 'valor' => 'Seguro expirado'],
                    ['label' => 'Valor', 'valor' => '2 800 MT'],
                    ['label' => 'Local', 'valor' => 'Posto Baixa'],
                ],
            ],
            [
                'tipo' => 'dados-sensiveis',
                'titulo' => 'Pedido de dados — AGL-482-MC',
                'matricula' => 'AGL-482-MC',
                'agente' => 'Agente Chissano',
                'posto' => 'Posto Baixa',
                'prioridade' => 'Alta',
                'estado' => 'pendente',
                'detalhes' => [
                    ['label' => 'Agente', 'valor' => 'Agente Chissano'],
                    ['label' => 'Dados', 'valor' => 'Proprietário + Histórico'],
                    ['label' => 'Motivo', 'valor' => 'Verificação de clonagem'],
                    ['label' => 'Local', 'valor' => 'Posto Baixa'],
                ],
            ],
            [
                'tipo' => 'apreensao',
                'titulo' => 'Apreensão de veículo — AEK-017-MP',
                'matricula' => 'AEK-017-MP',
                'agente' => 'Agente Sitoe',
                'posto' => 'Posto Machava',
                'prioridade' => 'Alta',
                'estado' => 'pendente',
                'detalhes' => [
                    ['label' => 'Agente', 'valor' => 'Agente Sitoe'],
                    ['label' => 'Motivo', 'valor' => 'Veículo em Hotlist'],
                    ['label' => 'Local', 'valor' => 'Posto Machava'],
                    ['label' => 'Prioridade', 'valor' => 'ALTA'],
                ],
            ],
            [
                'tipo' => 'auto',
                'titulo' => 'Auto de Notícia — AFC-205-MC',
                'matricula' => 'AFC-205-MC',
                'agente' => 'Agente Nhaca',
                'posto' => 'Posto Matola',
                'prioridade' => 'Baixa',
                'estado' => 'aprovado',
                'detalhes' => [
                    ['label' => 'Agente', 'valor' => 'Agente Nhaca'],
                    ['label' => 'Infracção', 'valor' => 'Inspecção em atraso'],
                    ['label' => 'Valor', 'valor' => '2 200 MT'],
                    ['label' => 'Local', 'valor' => 'Posto Matola'],
                ],
                'decidido_por' => 'Chefe Chissano',
                'decidido_em' => now()->subDay(),
            ],
            [
                'tipo' => 'dados-sensiveis',
                'titulo' => 'Pedido de dados — ABX-556-MP',
                'matricula' => 'ABX-556-MP',
                'agente' => 'Agente Cumbe',
                'posto' => 'Posto Namaacha',
                'prioridade' => 'Média',
                'estado' => 'rejeitado',
                'motivo_rejeicao' => 'Justificação insuficiente para acesso a dados sensíveis.',
                'decidido_por' => 'Chefe Nhaca',
                'decidido_em' => now()->subDay(),
                'detalhes' => [
                    ['label' => 'Agente', 'valor' => 'Agente Cumbe'],
                    ['label' => 'Dados', 'valor' => 'Proprietário'],
                    ['label' => 'Motivo', 'valor' => '—'],
                    ['label' => 'Local', 'valor' => 'Posto Namaacha'],
                ],
            ],
            [
                'tipo' => 'apreensao',
                'titulo' => 'Apreensão de veículo — AJK-120-MC',
                'matricula' => 'AJK-120-MC',
                'agente' => 'Agente Sitoe',
                'posto' => 'Posto Machava',
                'prioridade' => 'Alta',
                'estado' => 'aprovado',
                'decidido_por' => 'Chefe Chissano',
                'decidido_em' => now()->subDays(2),
                'detalhes' => [
                    ['label' => 'Agente', 'valor' => 'Agente Sitoe'],
                    ['label' => 'Motivo', 'valor' => 'Documentação em falta'],
                    ['label' => 'Local', 'valor' => 'Posto Machava'],
                    ['label' => 'Prioridade', 'valor' => 'ALTA'],
                ],
            ],
        ];

        foreach ($exemplos as $e) {
            $a = Aprovacao::create($e);
            $a->update([
                'codigo' => sprintf('APR-%s-%06d', now()->format('Y'), $a->id),
            ]);
        }
    }
}