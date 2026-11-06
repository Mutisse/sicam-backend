<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Exportacao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class InatroExportacaoController extends Controller
{
    /**
     * GET /api/exportacoes
     * Lista o histórico de exportações.
     */
    public function index(Request $request)
    {
        $query = Exportacao::with('criador:id,name')
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('formato'), fn ($q) => $q->where('formato', $request->formato))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $items = $query->get()->map(function ($e) {
            return [
                'id'            => (string) $e->id,
                'codigo'        => $e->codigo,
                'tipo'          => $e->tipo,
                'formato'       => $e->formato,
                'titulo'        => $e->titulo,
                'detalhe'       => $e->detalhe,
                'data_de'       => $e->data_de ? $e->data_de->format('Y-m-d') : null,
                'data_ate'      => $e->data_ate ? $e->data_ate->format('Y-m-d') : null,
                'incluir_cabecalho' => (bool) $e->incluir_cabecalho,
                'incluir_resumo'    => (bool) $e->incluir_resumo,
                'apenas_ativos'     => (bool) $e->apenas_ativos,
                'total_registos'    => (int) $e->total_registos,
                'tamanho_bytes'     => (int) $e->tamanho_bytes,
                'criadoEm'          => $e->created_at ? $e->created_at->toIso8601String() : '',
                'criadoPor'         => $e->criador->name ?? 'Sistema',
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'meta'  => ['total' => $items->count()],
        ]);
    }

    /**
     * POST /api/exportacoes
     * Regista o histórico depois do download.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'tipo'              => ['required', 'string', 'max:50'],
            'formato'           => ['required', Rule::in(['xlsx', 'pdf', 'csv', 'json'])],
            'titulo'            => ['required', 'string', 'max:255'],
            'detalhe'           => ['nullable', 'string'],
            'data_de'           => ['nullable', 'date'],
            'data_ate'          => ['nullable', 'date', 'after_or_equal:data_de'],
            'incluir_cabecalho' => ['nullable', 'boolean'],
            'incluir_resumo'    => ['nullable', 'boolean'],
            'apenas_ativos'     => ['nullable', 'boolean'],
            'total_registos'    => ['nullable', 'integer'],
            'tamanho_bytes'     => ['nullable', 'integer'],
        ]);

        try {
            $exportacao = DB::transaction(function () use ($dados) {
                $ano = now()->year;
                $ultimo = Exportacao::where('codigo', 'like', "EXP-{$ano}-%")->lockForUpdate()->count();
                $codigo = sprintf('EXP-%d-%04d', $ano, $ultimo + 1);

                return Exportacao::create(array_merge($dados, [
                    'codigo'         => $codigo,
                    'incluir_cabecalho' => $dados['incluir_cabecalho'] ?? true,
                    'incluir_resumo'    => $dados['incluir_resumo'] ?? true,
                    'apenas_ativos'     => $dados['apenas_ativos'] ?? false,
                    'total_registos'    => $dados['total_registos'] ?? 0,
                    'tamanho_bytes'     => $dados['tamanho_bytes'] ?? 0,
                    'criado_por'        => auth()->id(),
                ]));
            });

            return response()->json([
                'id'     => (string) $exportacao->id,
                'codigo' => $exportacao->codigo,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Falha ao registar exportação.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/exportacoes/xlsx
     */
    public function gerarXlsx(Request $request)
    {
        $dados = $request->validate([
            'titulo'    => ['required', 'string'],
            'cabecalhos'=> ['required', 'array'],
            'linhas'    => ['required', 'array'],
            'resumo'    => ['nullable', 'array'],
        ]);

        $nomeFicheiro = 'exportacao-' . now()->format('YmdHis') . '.xlsx';

        return Excel::download(
            new DadosExport($dados['linhas'], $dados['cabecalhos'], $dados['titulo']),
            $nomeFicheiro
        );
    }

    /**
     * POST /api/exportacoes/pdf
     */
    public function gerarPdf(Request $request)
    {
        $dados = $request->validate([
            'titulo'     => ['required', 'string'],
            'subtitulo'  => ['nullable', 'string'],
            'cabecalhos' => ['required', 'array'],
            'linhas'     => ['required', 'array'],
            'resumo'     => ['nullable', 'array'],
        ]);

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — ' . $dados['titulo'],
            'sigla'       => 'SICAM',
            'rodape'      => 'Documento gerado pelo SICAM',
            'titulo'      => $dados['titulo'],
            'subtitulo'   => $dados['subtitulo'] ?? '',
            'cabecalhos'  => $dados['cabecalhos'],
            'linhas'      => $dados['linhas'],
            'resumo'      => $dados['resumo'] ?? [],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('exportacao-' . now()->format('YmdHis') . '.pdf');
    }

    /**
     * DELETE /api/exportacoes/{id}
     */
    public function destroy($id)
    {
        $exportacao = Exportacao::find($id);
        if (!$exportacao) {
            return response()->json(['message' => 'Exportação não encontrada.'], 404);
        }

        $exportacao->delete();
        return response()->json(['message' => 'Exportação eliminada.']);
    }
}