<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InatroVeiculoController extends Controller
{
    /**
     * GET /api/veiculos
     * Lista paginada com filtros (q, provincia, estado, posto_id).
     */
    public function index(Request $request)
    {
        $query = Veiculo::with('posto:id,nome')
            ->when($request->filled('q'), function ($q) use ($request) {
                $termo = '%' . $request->q . '%';
                $q->where(function ($sub) use ($termo) {
                    $sub->where('matricula', 'like', $termo)
                        ->orWhere('marca', 'like', $termo)
                        ->orWhere('modelo', 'like', $termo)
                        ->orWhere('proprietario_nome', 'like', $termo)
                        ->orWhere('proprietario_numero_id', 'like', $termo);
                });
            })
            ->when($request->filled('provincia'), fn ($q) => $q->where('provincia', $request->provincia))
            ->when($request->filled('estado'),    fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('posto_id'),  fn ($q) => $q->where('posto_id', $request->posto_id))
            ->orderByDesc('id');

        $porPagina = max(1, min((int) $request->input('per_page', 15), 100));
        $pagina = $query->paginate($porPagina);

        return response()->json([
            'data' => $pagina->items(),
            'meta' => [
                'total'        => $pagina->total(),
                'por_pagina'   => $pagina->perPage(),
                'pagina_atual' => $pagina->currentPage(),
                'ultima'       => $pagina->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/veiculos
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula'              => ['required', 'string', 'max:20', 'unique:veiculos,matricula'],
            'marca'                  => ['required', 'string', 'max:100'],
            'modelo'                 => ['required', 'string', 'max:100'],
            'ano'                    => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'cor'                    => ['nullable', 'string', 'max:50'],
            'proprietario_nome'      => ['required', 'string', 'max:255'],
            'proprietario_numero_id' => ['nullable', 'string', 'max:50'],
            'posto_id'               => ['nullable', 'integer', 'exists:postos,id'],
            'provincia'              => ['nullable', 'string', 'max:100'],
            'estado'                 => ['nullable', Rule::in(['activo', 'suspenso', 'apreendido', 'inactivo'])],
        ]);

        $veiculo = Veiculo::create(array_merge($dados, [
            'score'                => 0,
            'estado'               => $dados['estado'] ?? 'activo',
            'ultima_sincronizacao' => now(),
        ]));

        $veiculo->load('posto:id,nome');

        return response()->json([
            'message' => 'Veículo registado com sucesso.',
            'data'    => $veiculo,
        ], 201);
    }

    /**
     * GET /api/veiculos/{id}
     */
    public function show($id)
    {
        $veiculo = Veiculo::with([
            'posto:id,nome',
        ])->find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        return response()->json(['data' => $veiculo]);
    }
}