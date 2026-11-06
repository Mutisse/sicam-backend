<?php
// app/Http/Controllers/Api/PrmPostoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Posto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrmPostoController extends Controller
{
    /**
     * GET /api/prm/postos
     * Devolve a lista de postos para os filtros do PRM.
     */
    public function index(Request $request)
    {
        // ⭐ Removemos o where('ativo', 1) porque a coluna não existe na BD.
        // Vamos buscar apenas o necessário para o frontend.
        $postos = Posto::query()
            ->select('id', 'nome', 'provincia', 'latitude', 'longitude')
            ->orderBy('nome')
            ->get();

        // Se por acaso a tabela não tiver latitude/longitude, o Laravel devolve null
        // e o frontend não rebenta.
        
        return response()->json([
            'items' => $postos->map(function ($posto) {
                return [
                    'id'        => (string) $posto->id,
                    'nome'      => $posto->nome,
                    'provincia' => $posto->provincia ?? '—',
                    'latitude'  => $posto->latitude,
                    'longitude' => $posto->longitude,
                ];
            })
        ]);
    }
}