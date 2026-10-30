<?php

namespace Database\Seeders;

use App\Models\ChecklistCampo;
use App\Models\ChecklistSeccao;
use Illuminate\Database\Seeder;

class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $estrutura = [
            [
                'chave' => 'identificacao',
                'label' => '1. Identificação do veículo',
                'campos' => [
                    ['chave' => 'vin',                 'label' => 'Nº Chassi (VIN)',          'tipo' => 'texto',   'max' => 17],
                    ['chave' => 'matricula_ok',        'label' => 'Matrícula conforme',       'tipo' => 'booleano'],
                    ['chave' => 'marca_modelo_versao', 'label' => 'Marca / Modelo / Versão',  'tipo' => 'texto'],
                    ['chave' => 'combustivel',         'label' => 'Combustível',              'tipo' => 'opcoes',
                        'opcoes' => ['Gasolina', 'Diesel', 'GPL', 'Elétrico', 'Híbrido']],
                    ['chave' => 'hodometro_km',        'label' => 'Hodómetro',                'tipo' => 'numero', 'unidade' => 'km'],
                ],
            ],
            [
                'chave' => 'travagem',
                'label' => '2. Equipamento de travagem (frenómetro)',
                'campos' => [
                    ['chave' => 'forca_dianteira_esq_kn',     'label' => 'Força dianteira esq.',    'tipo' => 'decimal', 'unidade' => 'kN'],
                    ['chave' => 'forca_dianteira_dir_kn',     'label' => 'Força dianteira dir.',    'tipo' => 'decimal', 'unidade' => 'kN'],
                    ['chave' => 'forca_traseira_esq_kn',      'label' => 'Força traseira esq.',     'tipo' => 'decimal', 'unidade' => 'kN'],
                    ['chave' => 'forca_traseira_dir_kn',      'label' => 'Força traseira dir.',     'tipo' => 'decimal', 'unidade' => 'kN'],
                    ['chave' => 'desequilibrio_dianteiro_pct','label' => 'Desequilíbrio dianteiro', 'tipo' => 'decimal', 'unidade' => '%', 'max' => 30],
                    ['chave' => 'desequilibrio_traseiro_pct', 'label' => 'Desequilíbrio traseiro',  'tipo' => 'decimal', 'unidade' => '%', 'max' => 30],
                    ['chave' => 'travao_mao_eficiencia_pct',  'label' => 'Travão de mão',           'tipo' => 'decimal', 'unidade' => '%', 'min' => 16],
                    ['chave' => 'flutuacao_pct',              'label' => 'Flutuação / ovalização',  'tipo' => 'decimal', 'unidade' => '%', 'max' => 30],
                ],
            ],
            [
                'chave' => 'direcao',
                'label' => '3. Direcção e alinhamento',
                'campos' => [
                    ['chave' => 'alinhamento_m_km', 'label' => 'Alinhamento',              'tipo' => 'decimal', 'unidade' => 'm/km', 'max' => 5],
                    ['chave' => 'folgas_rotulas',   'label' => 'Folgas — rótulas',         'tipo' => 'booleano', 'invertido' => true],
                    ['chave' => 'folgas_ponteiras', 'label' => 'Folgas — ponteiras',       'tipo' => 'booleano', 'invertido' => true],
                    ['chave' => 'folgas_bracos',    'label' => 'Folgas — braços susp.',    'tipo' => 'booleano', 'invertido' => true],
                    ['chave' => 'folgas_caixa',     'label' => 'Folgas — caixa direcção',  'tipo' => 'booleano', 'invertido' => true],
                    ['chave' => 'foles_ok',         'label' => 'Foles de direcção',        'tipo' => 'booleano'],
                ],
            ],
            [
                'chave' => 'visibilidade',
                'label' => '4. Visibilidade',
                'campos' => [
                    ['chave' => 'para_brisas_ok',         'label' => 'Para-brisas sem fissuras',  'tipo' => 'booleano'],
                    ['chave' => 'peliculas_opacidade_pct','label' => 'Opacidade de películas',     'tipo' => 'decimal', 'unidade' => '%', 'max' => 70],
                    ['chave' => 'limpa_vidros_ok',        'label' => 'Limpa-para-brisas',         'tipo' => 'booleano'],
                    ['chave' => 'espelhos_ok',            'label' => 'Espelhos retrovisores',     'tipo' => 'booleano'],
                ],
            ],
            [
                'chave' => 'iluminacao',
                'label' => '5. Iluminação e sinalização (regloscópio)',
                'campos' => [
                    ['chave' => 'focagem_medios_pct',      'label' => 'Focagem médios',           'tipo' => 'decimal', 'unidade' => '%', 'min' => -3.5, 'max' => 0],
                    ['chave' => 'focagem_maximos_pct',     'label' => 'Focagem máximos',          'tipo' => 'decimal', 'unidade' => '%'],
                    ['chave' => 'intensidade_medios_lux',  'label' => 'Intensidade médios',       'tipo' => 'numero',  'unidade' => 'Lux', 'min' => 1000],
                    ['chave' => 'intensidade_maximos_lux', 'label' => 'Intensidade máximos',      'tipo' => 'numero',  'unidade' => 'Lux'],
                    ['chave' => 'presenca_ok',             'label' => 'Luzes de presença',        'tipo' => 'booleano'],
                    ['chave' => 'piscas_ok',               'label' => 'Piscas',                   'tipo' => 'booleano'],
                    ['chave' => 'quatro_piscas_ok',        'label' => 'Quatro piscas',            'tipo' => 'booleano'],
                    ['chave' => 'marcha_atras_ok',         'label' => 'Luz marcha-atrás',         'tipo' => 'booleano'],
                    ['chave' => 'luz_travao_ok',           'label' => 'Luz de travão',            'tipo' => 'booleano'],
                    ['chave' => 'luz_matricula_ok',        'label' => 'Luz de matrícula',         'tipo' => 'booleano'],
                    ['chave' => 'buzina_ok',               'label' => 'Buzina funcional',         'tipo' => 'booleano'],
                    ['chave' => 'buzina_db',               'label' => 'Buzina',                   'tipo' => 'numero',  'unidade' => 'dB', 'min' => 87, 'max' => 112],
                ],
            ],
            [
                'chave' => 'eixos',
                'label' => '6. Eixos, rodas, pneus e suspensão',
                'campos' => [
                    ['chave' => 'pneus_dimensao_ok',           'label' => 'Dimensão dos pneus conforme','tipo' => 'booleano'],
                    ['chave' => 'pneus_sulcos_mm',             'label' => 'Profundidade sulcos',        'tipo' => 'decimal', 'unidade' => 'mm', 'min' => 1.6],
                    ['chave' => 'jantes_ok',                   'label' => 'Jantes sem danos',           'tipo' => 'booleano'],
                    ['chave' => 'amortecedores_dianteiro_pct', 'label' => 'Amortecedores dianteiro',    'tipo' => 'decimal', 'unidade' => '%', 'min' => 40],
                    ['chave' => 'amortecedores_traseiro_pct',  'label' => 'Amortecedores traseiro',     'tipo' => 'decimal', 'unidade' => '%', 'min' => 40],
                    ['chave' => 'fugas_oleo_amortecedor',      'label' => 'Fugas de óleo amortecedor',  'tipo' => 'booleano', 'invertido' => true],
                ],
            ],
            [
                'chave' => 'carrocaria',
                'label' => '7. Quadro, carroçaria e acessórios',
                'campos' => [
                    ['chave' => 'chassi_corrosao',   'label' => 'Corrosão perfurante',       'tipo' => 'booleano', 'invertido' => true],
                    ['chave' => 'chassi_deformacao', 'label' => 'Deformação estrutural',     'tipo' => 'booleano', 'invertido' => true],
                    ['chave' => 'portas_ok',         'label' => 'Portas e fechaduras',       'tipo' => 'booleano'],
                    ['chave' => 'trincos_ok',        'label' => 'Trincos de segurança',      'tipo' => 'booleano'],
                    ['chave' => 'cintos_ok',         'label' => 'Cintos de segurança',       'tipo' => 'booleano'],
                    ['chave' => 'triangulo_ok',      'label' => 'Triângulo pré-sinalização', 'tipo' => 'booleano'],
                    ['chave' => 'colete_ok',         'label' => 'Colete reflector',          'tipo' => 'booleano'],
                    ['chave' => 'sobressalente_ok',  'label' => 'Pneu sobressalente',        'tipo' => 'booleano'],
                ],
            ],
            [
                'chave' => 'emissoes',
                'label' => '8. Emissões de gases e ruído',
                'campos' => [
                    ['chave' => 'co_ralenti_pct',     'label' => 'CO ao ralenti',    'tipo' => 'decimal', 'unidade' => '%', 'max' => 0.5, 'so_se_gasolina' => true],
                    ['chave' => 'co_rotacao_pct',     'label' => 'CO em rotação',    'tipo' => 'decimal', 'unidade' => '%', 'max' => 0.3, 'so_se_gasolina' => true],
                    ['chave' => 'lambda',             'label' => 'Fator Lambda',     'tipo' => 'decimal', 'min' => 0.97, 'max' => 1.03, 'so_se_gasolina' => true],
                    ['chave' => 'opacidade_diesel_k', 'label' => 'Opacidade (k)',    'tipo' => 'decimal', 'unidade' => 'k', 'max' => 2.5, 'so_se_diesel' => true],
                    ['chave' => 'fugas_escape',       'label' => 'Fugas no escape',  'tipo' => 'booleano', 'invertido' => true],
                    ['chave' => 'fugas_fluidos',      'label' => 'Fugas de fluidos', 'tipo' => 'booleano', 'invertido' => true],
                ],
            ],
        ];

        foreach ($estrutura as $i => $seccao) {
            $s = ChecklistSeccao::create([
                'chave' => $seccao['chave'],
                'label' => $seccao['label'],
                'ordem' => $i + 1,
                'ativo' => true,
            ]);

            foreach ($seccao['campos'] as $j => $campo) {
                ChecklistCampo::create([
                    'seccao_id'      => $s->id,
                    'chave'          => $campo['chave'],
                    'label'          => $campo['label'],
                    'tipo'           => $campo['tipo'],
                    'opcoes'         => $campo['opcoes'] ?? null,
                    'min'            => $campo['min'] ?? null,
                    'max'            => $campo['max'] ?? null,
                    'invertido'      => $campo['invertido'] ?? false,
                    'so_se_gasolina' => $campo['so_se_gasolina'] ?? false,
                    'so_se_diesel'   => $campo['so_se_diesel'] ?? false,
                    'unidade'        => $campo['unidade'] ?? null,
                    'ordem'          => $j + 1,
                    'ativo'          => true,
                ]);
            }
        }
    }
}