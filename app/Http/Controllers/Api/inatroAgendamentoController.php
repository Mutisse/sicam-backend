<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exportacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InatroExportacaoController extends Controller
{
    public function index(Request $request)
    {
        $query = Exportacao::with('criador:id,name')
            ->when($request->filled('tipo'),    fn($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('formato'), fn($q) => $q->where('formato', $request->formato))
            ->when($request->filled('data_de'), fn($q) => $q->whereDate('data_de', '>=', $request->data_de))
            ->when($request->filled('data_ate'), fn($q) => $q->whereDate('data_ate', '<=', $request->data_ate))
            ->orderByDesc('created_at')
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

    public function store(Request $request)
    {
        $dados = $request->validate([
            'tipo'              => ['required', 'string', 'max:50'],
            'formato'           => ['required', Rule::in(['xlsx', 'pdf', 'csv'])],
            'titulo'            => ['nullable', 'string', 'max:255'],
            'detalhe'           => ['nullable', 'string'],
            'data_de'           => ['nullable', 'date'],
            'data_ate'          => ['nullable', 'date', 'after_or_equal:data_de'],
            'incluir_cabecalho' => ['nullable', 'boolean'],
            'incluir_resumo'    => ['nullable', 'boolean'],
            'apenas_ativos'     => ['nullable', 'boolean'],
        ]);

        try {
            $exportacao = DB::transaction(function () use ($dados) {
                $ano = now()->year;
                $ultimo = Exportacao::where('codigo', 'like', "EXP-{$ano}-%")
                    ->lockForUpdate()
                    ->count();
                $codigo = sprintf('EXP-%d-%04d', $ano, $ultimo + 1);

                return Exportacao::create(array_merge($dados, [
                    'codigo'            => $codigo,
                    'incluir_cabecalho' => $dados['incluir_cabecalho'] ?? true,
                    'incluir_resumo'    => $dados['incluir_resumo'] ?? true,
                    'apenas_ativos'     => $dados['apenas_ativos'] ?? false,
                    'total_registos'    => 0,
                    'tamanho_bytes'     => 0,
                    'criado_por'        => auth()->id(),
                ]));
            });

            return response()->json([
                'message' => 'Pedido de exportação registado.',
                'data'    => $exportacao,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Falha ao registar exportação.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function gerarXlsx(Request $request)
    {
        return $this->gerar($request, 'xlsx');
    }
    public function gerarPdf(Request $request)
    {
        return $this->gerar($request, 'pdf');
    }

    public function destroy($id)
    {
        $exportacao = Exportacao::find($id);
        if (!$exportacao) return response()->json(['message' => 'Exportação não encontrada.'], 404);
        $exportacao->delete();
        return response()->json(['message' => 'Exportação eliminada.']);
    }

    private function gerar(Request $request, string $formato)
    {
        // TODO: gerar realmente o ficheiro e atualizar total_registos + tamanho_bytes
        return response()->json([
            'message' => "Geração de {$formato} ainda não implementada.",
            'formato' => $formato,
        ], 501);
    }
}
