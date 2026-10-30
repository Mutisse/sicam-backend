<?php

namespace Database\Seeders;

use App\Models\Escala;
use App\Models\User;
use Illuminate\Database\Seeder;

class EscalaSeeder extends Seeder
{
    public function run(): void
    {
        // Buscar o agente existente
        $agente = User::where('perfil_id', 30003)->first();

        if (!$agente) {
            $this->command->warn('Nenhum agente encontrado (perfil_id = 30003).');
            return;
        }

        // Semana atual (segunda a domingo)
        $hoje = now();
        $dia = $hoje->dayOfWeek; // 0=Dom, 1=Seg...
        $offset = $dia === 0 ? -6 : 1 - $dia;
        $segunda = $hoje->copy()->addDays($offset);
        $domingo = $segunda->copy()->addDays(6);

        $semana = sprintf(
            '%s — %s %d',
            $segunda->format('d/m'),
            $domingo->format('d/m'),
            $domingo->year,
        );

        $dias = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

        // Escala 1 — Publicada (turnos manhã quase todos)
        Escala::create([
            'agente_id'   => $agente->id,
            'posto'       => $agente->posto ?? 'Posto Baixa',
            'semana'      => $semana,
            'estado'      => 'Publicada',
            'atribuicoes' => [
                ['dia' => 'Seg', 'ativo' => true,  'turno' => 'Manhã'],
                ['dia' => 'Ter', 'ativo' => true,  'turno' => 'Manhã'],
                ['dia' => 'Qua', 'ativo' => true,  'turno' => 'Manhã'],
                ['dia' => 'Qui', 'ativo' => true,  'turno' => 'Manhã'],
                ['dia' => 'Sex', 'ativo' => true,  'turno' => 'Manhã'],
                ['dia' => 'Sáb', 'ativo' => false, 'turno' => 'Manhã'],
                ['dia' => 'Dom', 'ativo' => false, 'turno' => 'Manhã'],
            ],
            'notas'       => 'Escala normal — turno da manhã. Fim-de-semana de folga.',
            'criado_por'  => $agente->id,
        ]);

        // Escala 2 — Rascunho (turno tarde, ainda por confirmar)
        Escala::create([
            'agente_id'   => $agente->id,
            'posto'       => $agente->posto ?? 'Posto Baixa',
            'semana'      => $semana,
            'estado'      => 'Rascunho',
            'atribuicoes' => [
                ['dia' => 'Seg', 'ativo' => false, 'turno' => 'Tarde'],
                ['dia' => 'Ter', 'ativo' => true,  'turno' => 'Tarde'],
                ['dia' => 'Qua', 'ativo' => true,  'turno' => 'Tarde'],
                ['dia' => 'Qui', 'ativo' => true,  'turno' => 'Tarde'],
                ['dia' => 'Sex', 'ativo' => true,  'turno' => 'Tarde'],
                ['dia' => 'Sáb', 'ativo' => true,  'turno' => 'Tarde'],
                ['dia' => 'Dom', 'ativo' => false, 'turno' => 'Tarde'],
            ],
            'notas'       => 'Turno da tarde reforçado — aguarda confirmação do chefe.',
            'criado_por'  => $agente->id,
        ]);

        $this->command->info('2 escalas criadas para o agente: ' . $agente->name);
    }
}