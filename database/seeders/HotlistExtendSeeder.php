<?php

namespace Database\Seeders;

use App\Models\Hotlist;
use Illuminate\Database\Seeder;

class HotlistExtendSeeder extends Seeder
{
    public function run(): void
    {
        // Novos valores alinhados com a página
        $motivos = [
            'Hotlist PRM',
            'Seguro expirado',
            'Inspecção em atraso',
            'Multas em atraso',
            'Documentação irregular',
            'Veículo roubado',
            'Suspeita de clonagem',
        ];

        $prioridades = ['Urgente', 'Alta', 'Média', 'Alta', 'Baixa', 'Urgente', 'Alta'];
        $perigos     = ['alto', 'alto', 'medio', 'alto', 'baixo', 'alto', 'alto'];
        $agentes     = ['Agente Chissano', 'Agente Nhaca', 'Agente Sitoe', 'Agente Cumbe', 'Agente Bila', 'Agente Tembe', 'Agente Nhaca'];
        $postos      = ['Posto Baixa', 'Posto Matola', 'Posto Machava', 'Posto Baixa', 'Posto Central', 'Posto Namaacha', 'Posto Matola'];
        $zonas       = ['Av. Julius Nyerere', 'Av. 25 de Setembro', 'Av. Vladimir Lenine', 'Av. 24 de Julho', 'Av. Acordos de Lusaka', 'EN4', 'Av. do Trabalho'];
        $multas      = [3, 2, 1, 5, 2, 0, 0];
        $valores     = [8500, 4200, 2200, 14200, 3600, 0, 0];

        $registos = Hotlist::orderBy('id')->get();

        foreach ($registos as $i => $h) {
            $h->update([
                'motivo'           => $motivos[$i] ?? $h->motivo,
                'prioridade'       => $prioridades[$i] ?? 'Média',
                'perigo'           => $perigos[$i] ?? 'medio',
                'agente'           => $agentes[$i] ?? null,
                'posto'            => $postos[$i] ?? null,
                'zona'             => $zonas[$i] ?? null,
                'multas_pendentes' => $multas[$i] ?? 0,
                'valor_multas'     => $valores[$i] ?? 0,
                'descricao'        => $h->descricao ?: 'Veículo sinalizado para intervenção',
            ]);
        }

        // Garantir que os registos sem historico ganham um
        foreach (Hotlist::whereNull('historico')->get() as $h) {
            $h->update([
                'historico' => [
                    [
                        'data'        => $h->detectado_em
                            ? $h->detectado_em->format('d/m/Y H:i')
                            : now()->format('d/m/Y H:i'),
                        'acao'        => 'Registo importado',
                        'responsavel' => 'Sistema',
                    ],
                ],
            ]);
        }
    }
}