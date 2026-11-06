<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cor;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Provincia;
use App\Models\VeiculoCatalogo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class InatroCatalogoController extends Controller
{
    /**
     * GET /api/catalogos
     *
     * Devolve o contrato esperado pelo frontend:
     * {
     *   marcas:     Record<string, string[]>  → { "Toyota": ["Corolla","Hilux"] }
     *   cores:      [{ categoria: string, cores: string[] }]
     *   provincias: [{ codigo: string, nome: string }]
     * }
     *
     * Estratégia: descobre os nomes reais das colunas em runtime e adapta-se.
     * Nunca rebenta por causa de uma coluna em falta — no pior caso devolve [].
     */
    public function index()
    {
        $payload = Cache::remember('catalogos:all:v2', now()->addHour(), function () {
            return [
                'marcas'     => $this->construirMarcas(),
                'cores'      => $this->construirCores(),
                'provincias' => $this->construirProvincias(),
            ];
        });

        return response()->json($payload);
    }

    /**
     * { "Toyota": ["Corolla","Hilux"], "Honda": ["Civic"] }
     */
    private function construirMarcas(): array
    {
        try {
            $colMarca  = $this->detectarColuna('marcas', ['nome', 'designacao', 'descricao', 'label', 'titulo', 'marca']);
            $colModelo = $this->detectarColuna('modelos', ['nome', 'designacao', 'descricao', 'label', 'titulo', 'modelo']);

            if (!$colMarca || !$colModelo) {
                return [];
            }

            $marcas = Marca::orderBy($colMarca)->pluck($colMarca)->toArray();

            $resultado = [];
            foreach ($marcas as $nomeMarca) {
                $resultado[$nomeMarca] = [];
            }

            // Agrupa modelos por marca (via relação OU via coluna marca_id)
            $modelos = Modelo::query()->get();
            $marcaIdParaNome = Marca::pluck($colMarca, 'id')->toArray();

            foreach ($modelos as $modelo) {
                $marcaId = $modelo->marca_id ?? null;
                $nomeMarca = $marcaIdParaNome[$marcaId] ?? null;
                if ($nomeMarca === null) continue;

                $nomeModelo = $modelo->{$colModelo} ?? null;
                if ($nomeModelo === null) continue;

                $resultado[$nomeMarca][] = $nomeModelo;
            }

            return $resultado;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * [{ categoria: "Sólidas", cores: ["Branco","Preto"] }, ...]
     * Se a tabela `cores` tiver coluna `categoria`, agrupa por ela.
     * Senão, devolve tudo numa única categoria "Todas".
     */
    private function construirCores(): array
    {
        try {
            $colNome = $this->detectarColuna('cores', ['nome', 'designacao', 'descricao', 'label', 'titulo', 'cor']);
            if (!$colNome) {
                return [];
            }

            $colCategoria = $this->detectarColuna('cores', ['categoria', 'grupo', 'tipo']);

            $cores = Cor::orderBy($colNome)->get();

            if ($colCategoria) {
                $grupos = [];
                foreach ($cores as $cor) {
                    $cat = $cor->{$colCategoria} ?: 'Todas';
                    $grupos[$cat][] = $cor->{$colNome};
                }
                return collect($grupos)
                    ->map(fn ($lista, $cat) => ['categoria' => $cat, 'cores' => array_values($lista)])
                    ->values()
                    ->toArray();
            }

            return [[
                'categoria' => 'Todas',
                'cores'     => $cores->pluck($colNome)->values()->toArray(),
            ]];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * [{ codigo: "LDA", nome: "Luanda" }, ...]
     * Se a tabela tiver `codigo`, usa-o. Senão, deriva das 3 primeiras letras do nome.
     */
    private function construirProvincias(): array
    {
        try {
            $colNome = $this->detectarColuna('provincias', ['nome', 'designacao', 'descricao', 'label', 'titulo', 'provincia']);
            if (!$colNome) {
                return [];
            }

            $colCodigo = $this->detectarColuna('provincias', ['codigo', 'code', 'sigla', 'abreviacao']);

            return Provincia::orderBy($colNome)->get()
                ->map(function ($p) use ($colNome, $colCodigo) {
                    $nome = (string) $p->{$colNome};
                    $codigo = $colCodigo
                        ? (string) $p->{$colCodigo}
                        : strtoupper(substr(preg_replace('/\s+/', '', $nome), 0, 3));

                    return ['codigo' => $codigo, 'nome' => $nome];
                })
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Devolve o primeiro nome de coluna que exista na tabela, ou null.
     */
    private function detectarColuna(string $tabela, array $candidatos): ?string
    {
        if (!Schema::hasTable($tabela)) {
            return null;
        }

        $colunas = Schema::getColumnListing($tabela);

        foreach ($candidatos as $c) {
            if (in_array($c, $colunas, true)) {
                return $c;
            }
        }

        return null;
    }
}