<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Exportacao;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ExportacaoController extends Controller
{
    /**
     * GET /api/exportacoes
     */
    public function index(Request $request)
    {
        $query = Exportacao::query()->with('criador:id,name');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('formato')) {
            $query->where('formato', $request->formato);
        }
        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('titulo', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
                    ->orWhere('detalhe', 'like', "%{$q}%");
            });
        }

        $items = $query
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($e) => $this->formatar($e));

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * POST /api/exportacoes
     *
     * Regista uma exportação feita no cliente (só metadata).
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'tipo'               => 'required|string|max:40',
            'formato'            => 'required|string|max:20',
            'titulo'             => 'required|string|max:160',
            'detalhe'            => 'nullable|string|max:255',
            'data_de'            => 'nullable|date',
            'data_ate'           => 'nullable|date',
            'incluir_cabecalho'  => 'boolean',
            'incluir_resumo'     => 'boolean',
            'apenas_ativos'      => 'boolean',
            'total_registos'     => 'integer|min:0',
            'tamanho_bytes'      => 'integer|min:0',
        ]);

        $exportacao = DB::transaction(function () use ($dados, $request) {
            $e = Exportacao::create([
                'tipo'               => $dados['tipo'],
                'formato'            => $dados['formato'],
                'titulo'             => $dados['titulo'],
                'detalhe'            => $dados['detalhe'] ?? null,
                'data_de'            => $dados['data_de'] ?? null,
                'data_ate'           => $dados['data_ate'] ?? null,
                'incluir_cabecalho'  => $dados['incluir_cabecalho'] ?? true,
                'incluir_resumo'     => $dados['incluir_resumo'] ?? true,
                'apenas_ativos'      => $dados['apenas_ativos'] ?? false,
                'total_registos'     => $dados['total_registos'] ?? 0,
                'tamanho_bytes'      => $dados['tamanho_bytes'] ?? 0,
                'criado_por'         => $request->user()?->id,
            ]);

            $ano = Carbon::now()->format('Y');
            $e->update([
                'codigo' => sprintf('EXP-%s-%06d', $ano, $e->id),
            ]);

            return $e;
        });

        return response()->json([
            'id'     => (string) $exportacao->id,
            'codigo' => $exportacao->codigo,
        ], 201);
    }

    /**
     * POST /api/exportacoes/xlsx
     *
     * Recebe dados montados no frontend e devolve um .xlsx
     */
    public function gerarXlsx(Request $request)
    {
        $dados = $request->validate([
            'titulo'     => 'required|string|max:160',
            'cabecalhos' => 'required|array',
            'linhas'     => 'required|array',
            'resumo'     => 'nullable|array',
        ]);

        $linhas = [];

        // Resumo (opcional)
        if (!empty($dados['resumo'])) {
            foreach ($dados['resumo'] as $k => $v) {
                $linhas[] = [$k, $v];
            }
            $linhas[] = [];
        }

        // Dados
        foreach ($dados['linhas'] as $l) {
            $linhas[] = array_values($l);
        }

        $nomeFicheiro = sprintf(
            'inatro-%s-%s.xlsx',
            strtolower(str_replace(' ', '-', $dados['titulo'])),
            date('Y-m-d'),
        );

        return Excel::download(
            new DadosExport($linhas, $dados['cabecalhos'], $dados['titulo']),
            $nomeFicheiro,
        );
    }

    /**
     * POST /api/exportacoes/pdf
     *
     * Recebe dados montados no frontend e devolve um PDF
     */
    public function gerarPdf(Request $request)
    {
        $dados = $request->validate([
            'titulo'     => 'required|string|max:160',
            'subtitulo'  => 'nullable|string|max:255',
            'cabecalhos' => 'required|array',
            'linhas'     => 'required|array',
            'resumo'     => 'nullable|array',
        ]);

        $pdf = Pdf::loadView('pdf.exportacao', [
            'titulo'     => $dados['titulo'],
            'subtitulo'  => $dados['subtitulo'] ?? null,
            'cabecalhos' => $dados['cabecalhos'],
            'linhas'     => $dados['linhas'],
            'resumo'     => $dados['resumo'] ?? [],
            'geradoEm'   => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $nomeFicheiro = sprintf(
            'inatro-%s-%s.pdf',
            strtolower(str_replace(' ', '-', $dados['titulo'])),
            date('Y-m-d'),
        );

        return $pdf->download($nomeFicheiro);
    }

    /**
     * DELETE /api/exportacoes/{id}
     */
    public function destroy(int $id)
    {
        $e = Exportacao::findOrFail($id);
        $e->delete();

        return response()->json(['ok' => true]);
    }

    private function formatar(Exportacao $e): array
    {
        return [
            'id'                 => (string) $e->id,
            'codigo'             => $e->codigo,
            'tipo'               => $e->tipo,
            'formato'            => $e->formato,
            'titulo'             => $e->titulo,
            'detalhe'            => $e->detalhe,
            'data_de'            => $e->data_de?->format('Y-m-d'),
            'data_ate'           => $e->data_ate?->format('Y-m-d'),
            'incluir_cabecalho'  => $e->incluir_cabecalho,
            'incluir_resumo'     => $e->incluir_resumo,
            'apenas_ativos'      => $e->apenas_ativos,
            'total_registos'     => $e->total_registos,
            'tamanho_bytes'      => $e->tamanho_bytes,
            'criadoEm'           => $e->created_at?->format('d/m/Y H:i') ?? '—',
            'criadoPor'          => $e->criador?->name ?? '—',
        ];
    }
}