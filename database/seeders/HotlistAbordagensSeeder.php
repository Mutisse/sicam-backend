<?php

namespace Database\Seeders;

use App\Models\Abordagem;
use App\Models\Hotlist;
use App\Models\Posto;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Database\Seeder;

class HotlistAbordagensSeeder extends Seeder
{
    public function run(): void
    {
        $veiculos = Veiculo::all();
        $agentes = User::where('perfil_id', 8)->get();
        $postos = Posto::all();

        if ($veiculos->isEmpty()) {
            $this->command->warn('Sem veículos na BD.');
            return;
        }

        // ─── Hotlist ──────────────────────────────────────
        $motivos = ['Furtado', 'Clonagem suspeita', 'Mandado captura', 'Suspeito', 'Outros'];
        $perigos = ['alto', 'medio', 'baixo'];

        $contador = 0;
        foreach ($veiculos as $veiculo) {
            // 40% dos veículos estão na hotlist
            if (random_int(1, 100) > 40) continue;

            Hotlist::create([
                'veiculo_id'    => $veiculo->id,
                'motivo'        => $motivos[array_rand($motivos)],
                'perigo'        => $perigos[array_rand($perigos)],
                'estado'        => random_int(1, 100) <= 60 ? 'Activo' : 'Resolvido',
                'descricao'     => null,
                'detectado_em'  => now()->subDays(random_int(1, 180)),
                'resolvido_em'  => null,
            ]);
            $contador++;
        }

        $this->command->info("Hotlist: $contador registos");

        // ─── Abordagens ───────────────────────────────────
        $contadorAb = 0;
        for ($i = 0; $i < 30; $i++) {
            $veiculo = $veiculos->random();
            $agente = $agentes->isNotEmpty() ? $agentes->random() : null;
            $posto = $postos->isNotEmpty() ? $postos->random() : null;

            $resultado = ['Sem incidentes', 'Com detenção', 'Em curso'][array_rand(['Sem incidentes', 'Com detenção', 'Em curso'])];

            Abordagem::create([
                'codigo'      => 'AB-' . str_pad($contadorAb + 1, 3, '0', STR_PAD_LEFT),
                'veiculo_id'  => $veiculo->id,
                'agente_id'   => $agente?->id,
                'posto_id'    => $posto?->id,
                'local'       => $posto?->nome ?? 'Via pública',
                'resultado'   => $resultado,
                'data'        => now()->subDays(random_int(0, 30)),
                'observacoes' => null,
            ]);
            $contadorAb++;
        }

        $this->command->info("Abordagens: $contadorAb registos");
    }
}