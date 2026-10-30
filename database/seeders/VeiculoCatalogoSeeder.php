<?php

namespace Database\Seeders;

use App\Models\VeiculoCatalogo;
use Illuminate\Database\Seeder;

class VeiculoCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = $this->getCatalogo();

        $mapeamentoTipo = [
            'suv'         => 'Ligeiro',
            'sedan/hatch' => 'Ligeiro',
            'pickup'      => 'Ligeiro',
            'comercial'   => 'Ligeiro',
            'citadino'    => 'Ligeiro',
            'desportivo'  => 'Ligeiro',
        ];

        // Constrói todos os registos
        $registos = [];

        foreach ($catalogo as $marca => $modelos) {
            foreach ($modelos as $m) {
                if (empty($m)) continue;

                [$modelo, $categoria, $combustivel, $origem, $popularidade] = $m;

                $registos[] = [
                    'marca'        => $marca,
                    'modelo'       => $modelo,
                    'categoria'    => $categoria,
                    'combustivel'  => $combustivel,
                    'origem'       => $origem,
                    'popularidade' => $popularidade,
                    'tipo_veiculo' => $mapeamentoTipo[$categoria] ?? 'Ligeiro',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
        }

        // Insere em chunks de 50 — evita timeout e problemas de SSL
        $chunks = array_chunk($registos, 50);
        $total = 0;

        foreach ($chunks as $i => $chunk) {
            try {
                VeiculoCatalogo::upsert(
                    $chunk,
                    ['marca', 'modelo'],
                    ['categoria', 'combustivel', 'origem', 'popularidade', 'tipo_veiculo', 'updated_at'],
                );

                $total += count($chunk);
                echo "  → Chunk " . ($i + 1) . " de " . count($chunks) . " ({$total} registos)\n";
            } catch (\Throwable $e) {
                echo "  ✗ Chunk " . ($i + 1) . " falhou: " . $e->getMessage() . "\n";
                echo "    Aguardando 2s para a ligação recuperar…\n";
                sleep(2);
            }
        }

        echo "\n✅ Total inserido: {$total} registos de catálogo.\n";
    }

    private function getCatalogo(): array
    {
        return [
            'Toyota' => [
                ['Hilux',        'pickup',        'diesel/gasolina', 'Japão', 'alta'],
                ['Navara',       'pickup',        'diesel/gasolina', 'Japão', 'alta'],
                ['Ranger',       'pickup',        'diesel/gasolina', 'EUA',   'média'],
                ['Corolla',      'sedan/hatch',   'diesel/gasolina', 'Japão', 'alta'],
                ['Yaris',        'sedan/hatch',   'diesel/gasolina', 'Japão', 'alta'],
                ['Avanza',       'sedan/hatch',   'diesel/gasolina', 'Japão', 'alta'],
                ['RAV4',         'suv',           'diesel/gasolina', 'Japão', 'alta'],
                ['Fortuner',     'suv',           'diesel/gasolina', 'Japão', 'alta'],
                ['Land Cruiser', 'suv',           'diesel/gasolina', 'Japão', 'alta'],
                ['Hiace',        'comercial',     'diesel/gasolina', 'Japão', 'alta'],
                ['Corolla Cross','suv',           'diesel/gasolina', 'Japão', 'alta'],
            ],
            'Nissan' => [
                ['Navara',   'pickup',      'diesel/gasolina', 'Japão', 'alta'],
                ['NP300',    'pickup',      'diesel/gasolina', 'Japão', 'alta'],
                ['X-Trail',  'suv',         'diesel/gasolina', 'Japão', 'alta'],
                ['Qashqai',  'suv',         'diesel/gasolina', 'Japão', 'alta'],
                ['Juke',     'suv',         'diesel/gasolina', 'Japão', 'alta'],
                ['Kicks',    'suv',         'diesel/gasolina', 'Japão', 'alta'],
                ['Patrol',   'suv',         'diesel/gasolina', 'Japão', 'alta'],
                ['Note',     'sedan/hatch', 'diesel/gasolina', 'Japão', 'alta'],
                ['Magnite',  'suv',         'diesel/gasolina', 'Japão', 'alta'],
            ],
            'Ford' => [
                ['Ranger',  'pickup',      'diesel/gasolina', 'EUA', 'média'],
                ['Everest', 'suv',         'diesel/gasolina', 'EUA', 'média'],
                ['F-150',   'pickup',      'diesel/gasolina', 'EUA', 'média'],
                ['Kuga',    'suv',         'diesel/gasolina', 'EUA', 'média'],
                ['EcoSport','suv',         'diesel/gasolina', 'EUA', 'média'],
                ['Focus',   'sedan/hatch', 'diesel/gasolina', 'EUA', 'média'],
                ['Fiesta',  'sedan/hatch', 'diesel/gasolina', 'EUA', 'média'],
            ],
            'Mazda' => [
                ['BT-50', 'pickup',      'gasolina', 'Japão', 'alta'],
                ['3',     'sedan/hatch', 'gasolina', 'Japão', 'alta'],
                ['6',     'sedan/hatch', 'gasolina', 'Japão', 'alta'],
                ['CX-3',  'suv',         'gasolina', 'Japão', 'alta'],
                ['CX-5',  'suv',         'gasolina', 'Japão', 'alta'],
                ['CX-30', 'suv',         'gasolina', 'Japão', 'alta'],
                ['CX-60', 'suv',         'gasolina', 'Japão', 'alta'],
                ['Demio', 'sedan/hatch', 'gasolina', 'Japão', 'alta'],
                ['Axela', 'sedan/hatch', 'gasolina', 'Japão', 'alta'],
            ],
            'Honda' => [
                ['Civic',  'sedan/hatch', 'gasolina', 'Japão', 'média'],
                ['Accord', 'sedan/hatch', 'gasolina', 'Japão', 'média'],
                ['City',   'sedan/hatch', 'gasolina', 'Japão', 'média'],
                ['Fit',    'sedan/hatch', 'gasolina', 'Japão', 'média'],
                ['Brio',   'sedan/hatch', 'gasolina', 'Japão', 'média'],
                ['CR-V',   'suv',         'gasolina', 'Japão', 'média'],
                ['HR-V',   'suv',         'gasolina', 'Japão', 'média'],
            ],
            'Hyundai' => [
                ['i20',      'sedan/hatch', 'diesel/gasolina', 'Coreia do Sul', 'alta'],
                ['Accent',   'sedan/hatch', 'diesel/gasolina', 'Coreia do Sul', 'alta'],
                ['Elantra',  'sedan/hatch', 'diesel/gasolina', 'Coreia do Sul', 'alta'],
                ['Grand i10','sedan/hatch', 'diesel/gasolina', 'Coreia do Sul', 'alta'],
                ['Creta',    'suv',         'diesel/gasolina', 'Coreia do Sul', 'alta'],
                ['Tucson',   'suv',         'diesel/gasolina', 'Coreia do Sul', 'alta'],
                ['Venue',    'suv',         'diesel/gasolina', 'Coreia do Sul', 'alta'],
                ['Santa Fe', 'suv',         'diesel/gasolina', 'Coreia do Sul', 'alta'],
                ['H1',       'comercial',   'diesel/gasolina', 'Coreia do Sul', 'alta'],
            ],
            'Kia' => [
                ['Sportage','suv',         'diesel/gasolina', 'Coreia do Sul', 'média'],
                ['Sorento', 'suv',         'diesel/gasolina', 'Coreia do Sul', 'média'],
                ['Seltos',  'suv',         'diesel/gasolina', 'Coreia do Sul', 'média'],
                ['Sonet',   'suv',         'diesel/gasolina', 'Coreia do Sul', 'média'],
                ['Picanto', 'sedan/hatch', 'diesel/gasolina', 'Coreia do Sul', 'média'],
                ['Rio',     'sedan/hatch', 'diesel/gasolina', 'Coreia do Sul', 'média'],
                ['Cerato',  'sedan/hatch', 'diesel/gasolina', 'Coreia do Sul', 'média'],
                ['K3',      'sedan/hatch', 'diesel/gasolina', 'Coreia do Sul', 'média'],
                ['Carnival','comercial',   'diesel/gasolina', 'Coreia do Sul', 'média'],
            ],
            'VW' => [
                ['Polo',     'sedan/hatch', 'diesel/gasolina', 'Alemanha', 'alta'],
                ['Golf',     'sedan/hatch', 'diesel/gasolina', 'Alemanha', 'alta'],
                ['Passat',   'sedan/hatch', 'diesel/gasolina', 'Alemanha', 'alta'],
                ['Vento',    'sedan/hatch', 'diesel/gasolina', 'Alemanha', 'alta'],
                ['T-Cross',  'suv',         'diesel/gasolina', 'Alemanha', 'alta'],
                ['Tiguan',   'suv',         'diesel/gasolina', 'Alemanha', 'alta'],
                ['Teramont', 'suv',         'diesel/gasolina', 'Alemanha', 'alta'],
                ['Amarok',   'pickup',      'diesel/gasolina', 'Alemanha', 'alta'],
                ['Caddy',    'comercial',   'diesel/gasolina', 'Alemanha', 'alta'],
            ],
            'Mercedes-Benz' => [
                ['A-Class','sedan/hatch', 'diesel/gasolina', 'Alemanha', 'média'],
                ['C-Class','sedan/hatch', 'diesel/gasolina', 'Alemanha', 'média'],
                ['E-Class','sedan/hatch', 'diesel/gasolina', 'Alemanha', 'média'],
                ['G-Class','suv',         'diesel/gasolina', 'Alemanha', 'média'],
                ['GLC',    'suv',         'diesel/gasolina', 'Alemanha', 'média'],
                ['GLE',    'suv',         'diesel/gasolina', 'Alemanha', 'média'],
                ['Sprinter','comercial',  'diesel/gasolina', 'Alemanha', 'média'],
                ['V-Class','comercial',   'diesel/gasolina', 'Alemanha', 'média'],
            ],
            'BMW' => [
                ['Serie 1', 'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
                ['Serie 3', 'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
                ['Serie 5', 'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
                ['X1',      'suv',         'gasolina', 'Alemanha', 'baixa'],
                ['X3',      'suv',         'gasolina', 'Alemanha', 'baixa'],
                ['X5',      'suv',         'gasolina', 'Alemanha', 'baixa'],
                ['X6',      'suv',         'gasolina', 'Alemanha', 'baixa'],
            ],
            'Audi' => [
                ['A3', 'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
                ['A4', 'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
                ['A6', 'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
                ['Q2', 'suv',         'gasolina', 'Alemanha', 'baixa'],
                ['Q3', 'suv',         'gasolina', 'Alemanha', 'baixa'],
                ['Q5', 'suv',         'gasolina', 'Alemanha', 'baixa'],
                ['Q7', 'suv',         'gasolina', 'Alemanha', 'baixa'],
            ],
            'BYD' => [
                ['Atto 3',   'suv',         'elétrico', 'China', 'baixa'],
                ['Dolphin',  'sedan/hatch', 'elétrico', 'China', 'baixa'],
                ['Seal',     'sedan/hatch', 'elétrico', 'China', 'baixa'],
                ['Song Plus','suv',         'elétrico', 'China', 'baixa'],
                ['Yuan Plus','suv',         'elétrico', 'China', 'baixa'],
            ],
            'Chery' => [
                ['Arrizo 5','sedan/hatch', 'gasolina', 'China', 'baixa'],
                ['Tiggo 4', 'suv',         'gasolina', 'China', 'baixa'],
                ['Tiggo 7', 'suv',         'gasolina', 'China', 'baixa'],
                ['Tiggo 8', 'suv',         'gasolina', 'China', 'baixa'],
            ],
            'Haval' => [
                ['Jolion',  'suv', 'gasolina', 'China', 'baixa'],
                ['H6',      'suv', 'gasolina', 'China', 'baixa'],
                ['H9',      'suv', 'gasolina', 'China', 'baixa'],
                ['Big Dog', 'suv', 'gasolina', 'China', 'baixa'],
            ],
            'Changan' => [
                ['Alsvin','sedan/hatch', 'gasolina', 'China', 'baixa'],
                ['CS35',  'suv',         'gasolina', 'China', 'baixa'],
                ['CS55',  'suv',         'gasolina', 'China', 'baixa'],
                ['CS75',  'suv',         'gasolina', 'China', 'baixa'],
                ['Hunter','pickup',      'gasolina', 'China', 'baixa'],
            ],
            'GWM' => [
                ['Cannon',  'pickup', 'diesel', 'China', 'baixa'],
                ['P-Serie', 'pickup', 'diesel', 'China', 'baixa'],
            ],
            'Isuzu' => [
                ['D-Max','pickup',      'diesel', 'Japão', 'média'],
                ['MU-X', 'suv',         'diesel', 'Japão', 'média'],
                ['KB250','sedan/hatch', 'diesel', 'Japão', 'média'],
            ],
            'Mitsubishi' => [
                ['L200',           'pickup',      'diesel/gasolina', 'Japão', 'média'],
                ['Pajero',         'suv',         'diesel/gasolina', 'Japão', 'média'],
                ['Outlander',      'suv',         'diesel/gasolina', 'Japão', 'média'],
                ['ASX',            'suv',         'diesel/gasolina', 'Japão', 'média'],
                ['Eclipse Cross',  'suv',         'diesel/gasolina', 'Japão', 'média'],
                ['Xpander',        'comercial',   'diesel/gasolina', 'Japão', 'média'],
            ],
            'Suzuki' => [
                ['Swift',    'sedan/hatch', 'gasolina', 'Japão', 'média'],
                ['Baleno',   'sedan/hatch', 'gasolina', 'Japão', 'média'],
                ['Ciaz',     'sedan/hatch', 'gasolina', 'Japão', 'média'],
                ['Dzire',    'sedan/hatch', 'gasolina', 'Japão', 'média'],
                ['Vitara',   'suv',         'gasolina', 'Japão', 'média'],
                ['Jimny',    'suv',         'gasolina', 'Japão', 'média'],
                ['Ertiga',   'comercial',   'gasolina', 'Japão', 'média'],
                ['S-Presso', 'sedan/hatch', 'gasolina', 'Japão', 'média'],
            ],
            'Subaru' => [
                ['Forester','suv',         'gasolina', 'Japão', 'baixa'],
                ['Impreza', 'sedan/hatch', 'gasolina', 'Japão', 'baixa'],
                ['Legacy',  'sedan/hatch', 'gasolina', 'Japão', 'baixa'],
                ['Outback', 'suv',         'gasolina', 'Japão', 'baixa'],
                ['XV',      'suv',         'gasolina', 'Japão', 'baixa'],
            ],
            'Chevrolet' => [
                ['Cruze',       'sedan/hatch', 'diesel/gasolina', 'EUA', 'média'],
                ['Onix',        'sedan/hatch', 'diesel/gasolina', 'EUA', 'média'],
                ['S10',         'pickup',      'diesel/gasolina', 'EUA', 'média'],
                ['Spin',        'comercial',   'diesel/gasolina', 'EUA', 'média'],
                ['Tracker',     'suv',         'diesel/gasolina', 'EUA', 'média'],
                ['Trailblazer','suv',          'diesel/gasolina', 'EUA', 'média'],
            ],
            'Peugeot' => [
                ['208',      'sedan/hatch', 'diesel/gasolina', 'França', 'média'],
                ['2008',     'suv',         'diesel/gasolina', 'França', 'média'],
                ['3008',     'suv',         'diesel/gasolina', 'França', 'média'],
                ['5008',     'suv',         'diesel/gasolina', 'França', 'média'],
                ['Landtrek', 'pickup',      'diesel/gasolina', 'França', 'média'],
                ['Partner',  'comercial',   'diesel/gasolina', 'França', 'média'],
            ],
            'Renault' => [
                ['Kwid',    'sedan/hatch', 'gasolina', 'França', 'média'],
                ['Logan',   'sedan/hatch', 'gasolina', 'França', 'média'],
                ['Sandero', 'sedan/hatch', 'gasolina', 'França', 'média'],
                ['Duster',  'suv',         'gasolina', 'França', 'média'],
                ['Captur',  'suv',         'gasolina', 'França', 'média'],
                ['Koleos',  'suv',         'gasolina', 'França', 'média'],
                ['Triber',  'suv',         'gasolina', 'França', 'média'],
            ],
            'Land Rover' => [
                ['Defender',          'suv', 'diesel/gasolina', 'Reino Unido', 'baixa'],
                ['Discovery',         'suv', 'diesel/gasolina', 'Reino Unido', 'baixa'],
                ['Discovery Sport',   'suv', 'diesel/gasolina', 'Reino Unido', 'baixa'],
                ['Evoque',            'suv', 'diesel/gasolina', 'Reino Unido', 'baixa'],
                ['Range Rover',       'suv', 'diesel/gasolina', 'Reino Unido', 'baixa'],
                ['Range Rover Sport', 'suv', 'diesel/gasolina', 'Reino Unido', 'baixa'],
            ],
            'Porsche' => [
                ['911',      'desportivo', 'gasolina', 'Alemanha', 'baixa'],
                ['Cayenne',  'suv',        'gasolina', 'Alemanha', 'baixa'],
                ['Macan',    'suv',        'gasolina', 'Alemanha', 'baixa'],
                ['Panamera', 'desportivo', 'gasolina', 'Alemanha', 'baixa'],
            ],
            'Volvo' => [
                ['S60',  'sedan/hatch', 'diesel/gasolina', 'Suécia', 'baixa'],
                ['V60',  'sedan/hatch', 'diesel/gasolina', 'Suécia', 'baixa'],
                ['XC40', 'suv',         'diesel/gasolina', 'Suécia', 'baixa'],
                ['XC60', 'suv',         'diesel/gasolina', 'Suécia', 'baixa'],
                ['XC90', 'suv',         'diesel/gasolina', 'Suécia', 'baixa'],
            ],
            'Tesla' => [
                ['Model 3', 'sedan/hatch', 'elétrico', 'EUA', 'baixa'],
                ['Model S', 'sedan/hatch', 'elétrico', 'EUA', 'baixa'],
                ['Model X', 'suv',         'elétrico', 'EUA', 'baixa'],
                ['Model Y', 'suv',         'elétrico', 'EUA', 'baixa'],
            ],
            'BAIC' => [
                ['BJ40', 'suv', 'gasolina', 'China', 'baixa'],
                ['X35',  'suv', 'gasolina', 'China', 'baixa'],
                ['X55',  'suv', 'gasolina', 'China', 'baixa'],
            ],
            'Jaecoo' => [
                ['J7', 'suv', 'híbrido/gasolina', 'China', 'baixa'],
            ],
            'Omoda' => [
                ['C5', 'suv', 'híbrido/gasolina', 'China', 'baixa'],
            ],
            'Mahindra' => [
                ['Bolero', 'sedan/hatch', 'diesel', 'Índia', 'baixa'],
                ['Pik Up', 'pickup',      'diesel', 'Índia', 'baixa'],
                ['Scorpio','suv',         'diesel', 'Índia', 'baixa'],
                ['Thar',   'suv',         'diesel', 'Índia', 'baixa'],
                ['XUV700','suv',          'diesel', 'Índia', 'baixa'],
            ],
            'Tata' => [
                ['Altroz', 'sedan/hatch', 'diesel', 'Índia', 'baixa'],
                ['Harrier','suv',         'diesel', 'Índia', 'baixa'],
                ['Safari', 'suv',         'diesel', 'Índia', 'baixa'],
                ['Xenon',  'pickup',      'diesel', 'Índia', 'baixa'],
            ],
            'Geely' => [
                ['Atlas Pro','suv',         'gasolina', 'China', 'baixa'],
                ['Coolray',  'suv',         'gasolina', 'China', 'baixa'],
                ['Emgrand',  'sedan/hatch', 'gasolina', 'China', 'baixa'],
            ],
            'JAC' => [
                ['JS2', 'sedan/hatch', 'diesel/gasolina', 'China', 'baixa'],
                ['S4',  'sedan/hatch', 'diesel/gasolina', 'China', 'baixa'],
                ['T8',  'pickup',      'diesel/gasolina', 'China', 'baixa'],
            ],
            'Foton' => [
                ['Sauvana','suv',    'diesel', 'China', 'baixa'],
                ['Tunland','pickup', 'diesel', 'China', 'baixa'],
            ],
            'FAW' => [
                ['J6F','pickup','diesel','China','baixa'],
                ['R7', 'suv',   'diesel','China','baixa'],
            ],
            'DFM' => [
                ['D60',        'suv', 'gasolina', 'China', 'baixa'],
                ['Fengon 580', 'suv', 'gasolina', 'China', 'baixa'],
                ['T5 Evo',     'suv', 'gasolina', 'China', 'baixa'],
            ],
            'Livan' => [
                ['X3 Pro', 'suv', 'gasolina', 'China', 'baixa'],
            ],
            'Proton' => [
                ['Saga',    'sedan/hatch', 'gasolina', 'Malásia', 'baixa'],
                ['Persona', 'sedan/hatch', 'gasolina', 'Malásia', 'baixa'],
                ['X50',     'suv',         'gasolina', 'Malásia', 'baixa'],
                ['X70',     'suv',         'gasolina', 'Malásia', 'baixa'],
            ],
            'SsangYong' => [
                ['Korando','suv',    'diesel', 'Coreia do Sul', 'baixa'],
                ['Rexton', 'suv',    'diesel', 'Coreia do Sul', 'baixa'],
                ['Musso',  'pickup', 'diesel', 'Coreia do Sul', 'baixa'],
            ],
            'Citroën' => [
                ['C3',        'sedan/hatch', 'gasolina', 'França', 'baixa'],
                ['C4 Cactus', 'sedan/hatch', 'gasolina', 'França', 'baixa'],
                ['Berlingo',  'comercial',   'gasolina', 'França', 'baixa'],
            ],
            'Fiat' => [
                ['Argo',   'sedan/hatch', 'gasolina', 'Itália', 'baixa'],
                ['Cronos', 'sedan/hatch', 'gasolina', 'Itália', 'baixa'],
                ['Mobi',   'sedan/hatch', 'gasolina', 'Itália', 'baixa'],
                ['Strada', 'pickup',      'gasolina', 'Itália', 'baixa'],
                ['Toro',   'pickup',      'gasolina', 'Itália', 'baixa'],
            ],
            'Alfa Romeo' => [
                ['Giulia',  'sedan/hatch', 'gasolina', 'Itália', 'baixa'],
                ['Stelvio', 'suv',         'gasolina', 'Itália', 'baixa'],
            ],
            'Cadillac' => [
                ['CT5',      'sedan/hatch', 'gasolina', 'EUA', 'baixa'],
                ['Escalade', 'suv',         'gasolina', 'EUA', 'baixa'],
                ['XT5',      'suv',         'gasolina', 'EUA', 'baixa'],
            ],
            'Dodge' => [
                ['Durango',  'suv',    'gasolina', 'EUA', 'baixa'],
                ['Journey',  'suv',    'gasolina', 'EUA', 'baixa'],
                ['RAM 1500', 'pickup', 'gasolina', 'EUA', 'baixa'],
            ],
            'Jeep' => [
                ['Compass',        'suv', 'gasolina', 'EUA', 'baixa'],
                ['Renegade',       'suv', 'gasolina', 'EUA', 'baixa'],
                ['Wrangler',       'suv', 'gasolina', 'EUA', 'baixa'],
                ['Grand Cherokee', 'suv', 'gasolina', 'EUA', 'baixa'],
            ],
            'Jaguar' => [
                ['XE',     'sedan/hatch', 'gasolina', 'Reino Unido', 'baixa'],
                ['XF',     'sedan/hatch', 'gasolina', 'Reino Unido', 'baixa'],
                ['E-Pace', 'suv',         'gasolina', 'Reino Unido', 'baixa'],
                ['F-Pace', 'suv',         'gasolina', 'Reino Unido', 'baixa'],
            ],
            'Mini' => [
                ['Cooper',     'citadino',    'gasolina', 'Reino Unido', 'baixa'],
                ['Clubman',    'sedan/hatch', 'gasolina', 'Reino Unido', 'baixa'],
                ['Countryman','suv',          'gasolina', 'Reino Unido', 'baixa'],
            ],
            'Smart' => [
                ['ForTwo',  'citadino', 'elétrico/gasolina', 'Alemanha', 'baixa'],
                ['ForFour', 'citadino', 'elétrico/gasolina', 'Alemanha', 'baixa'],
            ],
            'Lexus' => [
                ['ES', 'sedan/hatch', 'híbrido/gasolina', 'Japão', 'baixa'],
                ['IS', 'sedan/hatch', 'híbrido/gasolina', 'Japão', 'baixa'],
                ['GX', 'suv',         'híbrido/gasolina', 'Japão', 'baixa'],
                ['LX', 'suv',         'híbrido/gasolina', 'Japão', 'baixa'],
                ['NX', 'suv',         'híbrido/gasolina', 'Japão', 'baixa'],
                ['RX', 'suv',         'híbrido/gasolina', 'Japão', 'baixa'],
            ],
            'Infiniti' => [
                ['Q50',  'sedan/hatch', 'gasolina', 'Japão', 'baixa'],
                ['QX50', 'suv',         'gasolina', 'Japão', 'baixa'],
                ['QX60', 'suv',         'gasolina', 'Japão', 'baixa'],
            ],
            'Opel' => [
                ['Astra',     'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
                ['Corsa',     'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
                ['Crossland', 'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
                ['Grandland', 'sedan/hatch', 'gasolina', 'Alemanha', 'baixa'],
            ],
            'Great Wall' => [
                ['H5',    'sedan/hatch', 'gasolina', 'China', 'baixa'],
                ['Steed', 'pickup',      'gasolina', 'China', 'baixa'],
            ],
        ];
    }
}