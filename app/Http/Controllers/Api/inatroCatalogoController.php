<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cor;
use App\Models\Marca;
use App\Models\Provincia;

class CatalogoController extends Controller
{
    /**
     * GET /api/catalogos
     *
     * Devolve marcas+modelos, cores agrupadas por categoria e províncias.
     */
    public function index()
    {
        // ------------------------------------------------------
        // Marcas → { "Toyota": ["Corolla", "Hilux", ...], ... }
        // ------------------------------------------------------
        $marcas = Marca::query()
            ->where('ativo', true)
            ->with(['modelos' => function ($q) {
                $q->where('ativo', true)->orderBy('nome');
            }])
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(function ($m) {
                return [
                    $m->nome => $m->modelos->pluck('nome')->all(),
                ];
            });

        // ------------------------------------------------------
        // Cores → [{ categoria, cores: [...] }, ...]
        // ------------------------------------------------------
        $cores = Cor::query()
            ->where('ativo', true)
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get()
            ->groupBy('categoria')
            ->map(function ($grupo, $categoria) {
                return [
                    'categoria' => $categoria,
                    'cores'     => $grupo->pluck('nome')->values()->all(),
                ];
            })
            ->values();

        // ------------------------------------------------------
        // Províncias → [{ codigo, nome }, ...]
        // ------------------------------------------------------
        $provincias = Provincia::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['codigo', 'nome']);

        return response()->json([
            'marcas'     => $marcas,
            'cores'      => $cores,
            'provincias' => $provincias,
        ]);
    }
}