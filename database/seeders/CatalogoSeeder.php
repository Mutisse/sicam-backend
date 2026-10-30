<?php

namespace Database\Seeders;

use App\Models\Cor;
use App\Models\Marca;
use App\Models\Provincia;
use Illuminate\Database\Seeder;

class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------
        // MARCAS + MODELOS
        // ------------------------------------------------------
        $marcas = [
            'Toyota'         => ['Corolla', 'Camry', 'Hilux', 'Yaris', 'RAV4', 'Land Cruiser', 'Hiace', 'Prius', 'Fortuner', 'Etios'],
            'Nissan'         => ['Navara', 'X-Trail', 'Qashqai', 'Patrol', 'Almera', 'Micra', 'Note', 'Urvan', 'Juke', 'Leaf'],
            'Mitsubishi'     => ['Pajero', 'L200', 'Outlander', 'ASX', 'Lancer', 'Canter', 'Fuso', 'Eclipse Cross'],
            'Ford'           => ['Ranger', 'Focus', 'Fiesta', 'EcoSport', 'Transit', 'Explorer', 'Everest', 'Edge'],
            'Volkswagen'     => ['Golf', 'Polo', 'Passat', 'Tiguan', 'Amarok', 'Caddy', 'Touareg', 'Jetta'],
            'Mercedes-Benz'  => ['Classe A', 'Classe C', 'Classe E', 'Classe S', 'GLA', 'GLC', 'Sprinter', 'Vito', 'Actros'],
            'BMW'            => ['Série 1', 'Série 3', 'Série 5', 'X1', 'X3', 'X5', 'X6', 'Z4', 'M3'],
            'Hyundai'        => ['i10', 'i20', 'i30', 'Tucson', 'Santa Fe', 'H-1', 'Accent', 'Creta', 'Elantra'],
            'Kia'            => ['Picanto', 'Rio', 'Sportage', 'Sorento', 'Cerato', 'Carnival', 'Seltos', 'Stonic'],
            'Honda'          => ['Civic', 'Accord', 'CR-V', 'HR-V', 'Fit', 'Jazz', 'Pilot', 'City'],
            'Mazda'          => ['Mazda2', 'Mazda3', 'Mazda6', 'CX-3', 'CX-30', 'CX-5', 'CX-9', 'MX-5', 'BT-50'],
            'Isuzu'          => ['D-Max', 'MU-X', 'NPR', 'NQR', 'FVR'],
            'Peugeot'        => ['108', '208', '308', '408', '508', '2008', '3008', '5008', 'Partner', 'Boxer'],
            'Renault'        => ['Clio', 'Megane', 'Captur', 'Duster', 'Kangoo', 'Master', 'Talisman', 'Scenic'],
            'Suzuki'         => ['Swift', 'Vitara', 'Jimny', 'SX4', 'Baleno', 'Ertiga', 'Ignis', 'Ciaz'],
            'Land Rover'     => ['Defender', 'Discovery', 'Range Rover', 'Range Rover Sport', 'Range Rover Evoque'],
            'Volvo'          => ['FH', 'FM', 'FMX', 'FE', 'FL', 'XC40', 'XC60', 'XC90', 'S60', 'S90'],
            'Scania'         => ['P-series', 'G-series', 'R-series', 'S-series'],
            'MAN'            => ['TGX', 'TGS', 'TGM', 'TGL', 'TGE'],
            'Audi'           => ['A1', 'A3', 'A4', 'A5', 'A6', 'Q2', 'Q3', 'Q5', 'Q7', 'TT'],
            'Opel'           => ['Corsa', 'Astra', 'Insignia', 'Mokka', 'Zafira', 'Vivaro', 'Movano'],
            'Citroën'        => ['C1', 'C3', 'C4', 'Berlingo', 'Jumpy', 'Jumper'],
            'Fiat'           => ['500', 'Panda', 'Tipo', 'Punto', 'Ducato', 'Doblò'],
            'Chevrolet'      => ['Onix', 'Prisma', 'Cruze', 'Spin', 'Tracker', 'S10', 'Camaro'],
            'Jeep'           => ['Renegade', 'Compass', 'Cherokee', 'Grand Cherokee', 'Wrangler'],
            'Subaru'         => ['Impreza', 'Legacy', 'Outback', 'Forester', 'XV', 'WRX', 'BRZ'],
            'Lexus'          => ['CT', 'IS', 'ES', 'GS', 'LS', 'UX', 'NX', 'RX', 'GX', 'LX'],
            'Outra'          => ['Outro modelo'],
        ];

        foreach ($marcas as $nomeMarca => $modelos) {
            $marca = Marca::create(['nome' => $nomeMarca]);

            foreach ($modelos as $nomeModelo) {
                $marca->modelos()->create(['nome' => $nomeModelo]);
            }
        }

        // ------------------------------------------------------
        // CORES
        // ------------------------------------------------------
        $cores = [
            'Sólidas — Básicas' => [
                'Branco', 'Preto', 'Cinza', 'Prata', 'Vermelho', 'Azul',
                'Verde', 'Amarelo', 'Laranja', 'Castanho', 'Bege', 'Dourado',
            ],
            'Metálicas' => [
                'Branco metálico', 'Preto metálico', 'Cinza metálico',
                'Prata metálico', 'Vermelho metálico', 'Azul metálico', 'Verde metálico',
            ],
            'Perolizadas' => [
                'Branco perolizado', 'Preto perolizado',
                'Cinza perolizado', 'Prata perolizado',
            ],
            'Especiais / Premium' => [
                'Azul-noite', 'Verde-militar', 'Vermelho-ferrari',
                'Amarelo-ouro', 'Preto-mate', 'Branco-mate', 'Camuflado', 'Bicolor',
            ],
        ];

        foreach ($cores as $categoria => $lista) {
            foreach ($lista as $nomeCor) {
                Cor::create([
                    'categoria' => $categoria,
                    'nome'      => $nomeCor,
                ]);
            }
        }

        // ------------------------------------------------------
        // PROVÍNCIAS
        // ------------------------------------------------------
        $provincias = [
            ['codigo' => 'MC', 'nome' => 'Maputo Cidade'],
            ['codigo' => 'MP', 'nome' => 'Maputo Província'],
            ['codigo' => 'GZ', 'nome' => 'Gaza'],
            ['codigo' => 'IN', 'nome' => 'Inhambane'],
            ['codigo' => 'SF', 'nome' => 'Sofala'],
            ['codigo' => 'MN', 'nome' => 'Manica'],
            ['codigo' => 'TT', 'nome' => 'Tete'],
            ['codigo' => 'ZB', 'nome' => 'Zambézia'],
            ['codigo' => 'NP', 'nome' => 'Nampula'],
            ['codigo' => 'CD', 'nome' => 'Cabo Delgado'],
            ['codigo' => 'NS', 'nome' => 'Niassa'],
        ];

        foreach ($provincias as $p) {
            Provincia::create($p);
        }
    }
}