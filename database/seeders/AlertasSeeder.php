<?php

namespace Database\Seeders;

use App\Models\Alerta;
use Illuminate\Database\Seeder;

class AlertasSeeder extends Seeder
{
    public function run(): void
    {
        $alertas = [
            [
                'nivel'      => 'critico',
                'tipo'       => 'hotlist',
                'titulo'     => 'Veículo em Hotlist detectado',
                'mensagem'   => 'Matrícula AGL-482-MC detectada em Posto Baixa. Alerta enviado a todos os agentes da zona.',
                'referencia' => 'AGL-482-MC',
                'origem'     => 'Sistema automático',
                'lido'       => false,
                'created_at' => now()->subMinutes(3),
            ],
            [
                'nivel'      => 'critico',
                'tipo'       => 'clonagem',
                'titulo'     => 'Suspeita de clonagem em curso',
                'mensagem'   => 'Divergência detectada entre registo INATRO e observação em campo no Posto Namaacha. Confiança: 96%.',
                'referencia' => 'AGL-482-MC',
                'origem'     => 'Integração INATRO',
                'lido'       => false,
                'created_at' => now()->subMinutes(18),
            ],
            [
                'nivel'      => 'atencao',
                'tipo'       => 'hotlist',
                'titulo'     => 'Veículo procurado avistado',
                'mensagem'   => 'AEK-017-MP avistado na zona de Machava. Confirme abordagem com apoio.',
                'referencia' => 'AEK-017-MP',
                'origem'     => 'Agente Sitoe',
                'lido'       => false,
                'created_at' => now()->subHour(),
            ],
            [
                'nivel'      => 'atencao',
                'tipo'       => 'sistema',
                'titulo'     => 'Latência elevada na integração GESTMUL',
                'mensagem'   => 'Tempo de resposta acima de 400 ms nos últimos 10 minutos. Consultas podem estar lentas.',
                'referencia' => null,
                'origem'     => 'Monitorização SICAM',
                'lido'       => false,
                'created_at' => now()->subHours(2),
            ],
            [
                'nivel'      => 'atencao',
                'tipo'       => 'sinistro',
                'titulo'     => 'Sinistro reportado em Maputo Cidade',
                'mensagem'   => 'Apólice referente à matrícula ABR-771-MP com sinistro aberto. Última actualização há 4 horas.',
                'referencia' => 'ABR-771-MP',
                'origem'     => 'Integração ISSM',
                'lido'       => false,
                'created_at' => now()->subHours(4),
            ],
            [
                'nivel'      => 'info',
                'tipo'       => 'sistema',
                'titulo'     => 'Hotlist sincronizada',
                'mensagem'   => '128 veículos sincronizados para 34 dispositivos móveis com sucesso.',
                'referencia' => null,
                'origem'     => 'Sincronização automática',
                'lido'       => true,
                'lido_em'    => now()->subHours(5),
                'created_at' => now()->subHours(6),
            ],
            [
                'nivel'      => 'info',
                'tipo'       => 'hotlist',
                'titulo'     => 'Veículo removido da Hotlist',
                'mensagem'   => 'ABM-390-GZ foi removido após resolução do caso. Caso encerrado.',
                'referencia' => 'ABM-390-GZ',
                'origem'     => 'Chefe Mabunda',
                'lido'       => true,
                'lido_em'    => now()->subHours(7),
                'created_at' => now()->subHours(8),
            ],
            [
                'nivel'      => 'info',
                'tipo'       => 'sistema',
                'titulo'     => 'Backup diário concluído',
                'mensagem'   => 'Backup automático da base de dados executado com sucesso às 03:00.',
                'referencia' => null,
                'origem'     => 'Sistema SICAM',
                'lido'       => true,
                'lido_em'    => now()->subHours(11),
                'created_at' => now()->subHours(12),
            ],
        ];

        foreach ($alertas as $a) {
            Alerta::create($a);
        }

        $this->command->info('Alertas criados: ' . count($alertas));
    }
}