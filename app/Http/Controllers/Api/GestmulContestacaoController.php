<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Contestacao;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class GestmulContestacaoController extends Controller
{
    /**
     * GET /api/gestmul/contestacoes
     */
    public function index(Request $request)
    {
        $query = Contestacao::query();

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('multa_codigo', 'like', "%{$q}%")
                    ->orWhere('matricula', 'like', "%{$q}%")
                    ->orWhere('contestante', 'like', "%{$q}%");
            });
        }

        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('motivo'))     $query->where('motivo', $request->motivo);
        if ($request->filled('prioridade')) $query->where('prioridade', $request->prioridade);

        $items = $query
            ->orderByDesc('data_submissao')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn ($c) => $this->formatar($c));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/gestmul/contestacoes/{id}
     */
    public function show(int $id)
    {
        $c = Contestacao::findOrFail($id);
        return response()->json($this->formatar($c));
    }

    /**
     * POST /api/gestmul/contestacoes
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'numero'        => 'nullable|string|max:30|unique:contestacoes,numero',
            'multaCodigo'   => 'required|string|max:30',
            'matricula'     => 'required|string|max:20',
            'infracao'      => 'nullable|string|max:160',
            'valorMulta'    => 'nullable|numeric|min:0',
            'contestante'   => 'required|string|max:160',
            'nif'           => 'required|string|size:9',
            'contacto'      => 'nullable|string|max:30',
            'motivo'        => 'required|string|max:120',
            'descricao'     => 'required|string|min:20',
            'prioridade'    => 'nullable|string|in:Baixa,Média,Alta',
            'prazoLimite'   => 'required|string',
            'estado'        => 'nullable|string|in:Pendente,Em análise,Aceite,Rejeitada,Cancelada',
            'observacoes'   => 'nullable|string',
        ]);

        $numero = $dados['numero'] ?? $this->gerarNumero();

        $c = Contestacao::create([
            'numero'         => $numero,
            'multa_codigo'   => strtoupper($dados['multaCodigo']),
            'matricula'      => strtoupper($dados['matricula']),
            'infracao'       => $dados['infracao'] ?? '—',
            'valor_multa'    => $dados['valorMulta'] ?? 0,
            'contestante'    => $dados['contestante'],
            'nif'            => $dados['nif'],
            'contacto'       => $dados['contacto'] ?? null,
            'motivo'         => $dados['motivo'],
            'descricao'      => $dados['descricao'],
            'prioridade'     => $dados['prioridade'] ?? 'Média',
            'data_submissao' => now()->toDateString(),
            'prazo_limite'   => $this->parseDataPt($dados['prazoLimite']),
            'estado'         => $dados['estado'] ?? 'Pendente',
            'observacoes'    => $dados['observacoes'] ?? null,
            'criado_por'     => $request->user()?->id,
        ]);

        return response()->json([
            'id'          => (string) $c->id,
            'contestacao' => $this->formatar($c),
            'kpis'        => $this->kpis(),
        ], 201);
    }

    /**
     * PUT /api/gestmul/contestacoes/{id}
     */
    public function update(Request $request, int $id)
    {
        $c = Contestacao::findOrFail($id);

        $dados = $request->validate([
            'multaCodigo'   => 'sometimes|string|max:30',
            'matricula'     => 'sometimes|string|max:20',
            'infracao'      => 'nullable|string|max:160',
            'valorMulta'    => 'sometimes|numeric|min:0',
            'contestante'   => 'sometimes|string|max:160',
            'nif'           => 'sometimes|string|size:9',
            'contacto'      => 'nullable|string|max:30',
            'motivo'        => 'sometimes|string|max:120',
            'descricao'     => 'sometimes|string|min:20',
            'prioridade'    => 'sometimes|string|in:Baixa,Média,Alta',
            'prazoLimite'   => 'sometimes|string',
            'estado'        => 'sometimes|string|in:Pendente,Em análise,Aceite,Rejeitada,Cancelada',
            'observacoes'   => 'nullable|string',
        ]);

        $update = [];
        if (isset($dados['multaCodigo']))   $update['multa_codigo'] = strtoupper($dados['multaCodigo']);
        if (isset($dados['matricula']))     $update['matricula']    = strtoupper($dados['matricula']);
        if (array_key_exists('infracao', $dados))   $update['infracao']   = $dados['infracao'];
        if (isset($dados['valorMulta']))    $update['valor_multa']  = $dados['valorMulta'];
        if (isset($dados['contestante']))   $update['contestante']  = $dados['contestante'];
        if (isset($dados['nif']))           $update['nif']          = $dados['nif'];
        if (array_key_exists('contacto', $dados))   $update['contacto']   = $dados['contacto'];
        if (isset($dados['motivo']))        $update['motivo']       = $dados['motivo'];
        if (isset($dados['descricao']))     $update['descricao']    = $dados['descricao'];
        if (isset($dados['prioridade']))    $update['prioridade']   = $dados['prioridade'];
        if (isset($dados['prazoLimite']))   $update['prazo_limite'] = $this->parseDataPt($dados['prazoLimite']);
        if (isset($dados['estado']))        $update['estado']       = $dados['estado'];
        if (array_key_exists('observacoes', $dados)) $update['observacoes'] = $dados['observacoes'];

        $c->update($update);

        return response()->json([
            'id'          => (string) $c->id,
            'contestacao' => $this->formatar($c->fresh()),
            'kpis'        => $this->kpis(),
        ]);
    }

    /**
     * DELETE /api/gestmul/contestacoes/{id}
     */
    public function destroy(int $id)
    {
        $c = Contestacao::findOrFail($id);
        $c->delete();

        return response()->json([
            'ok'   => true,
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/gestmul/contestacoes/{id}/em-analise
     */
    public function marcarEmAnalise(int $id)
    {
        $c = Contestacao::findOrFail($id);
        $c->update(['estado' => 'Em análise']);

        return response()->json([
            'id'          => (string) $c->id,
            'contestacao' => $this->formatar($c->fresh()),
            'kpis'        => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/gestmul/contestacoes/{id}/cancelar
     */
    public function cancelar(int $id)
    {
        $c = Contestacao::findOrFail($id);
        $c->update(['estado' => 'Cancelada']);

        return response()->json([
            'id'          => (string) $c->id,
            'contestacao' => $this->formatar($c->fresh()),
            'kpis'        => $this->kpis(),
        ]);
    }

    /**
     * POST /api/gestmul/contestacoes/{id}/decidir
     */
    public function decidir(Request $request, int $id)
    {
        $c = Contestacao::findOrFail($id);

        $dados = $request->validate([
            'tipo'        => 'required|string|in:Aceite,Rejeitada',
            'fundamento'  => 'required|string|min:10',
            'responsavel' => 'required|string|max:160',
        ]);

        $c->update([
            'estado'      => $dados['tipo'],
            'decisao'     => $dados['fundamento'],
            'decisao_em'  => now(),
            'decisao_por' => $dados['responsavel'],
        ]);

        return response()->json([
            'id'          => (string) $c->id,
            'contestacao' => $this->formatar($c->fresh()),
            'kpis'        => $this->kpis(),
        ]);
    }

    /**
     * GET /api/gestmul/contestacoes/xlsx
     */
    public function xlsx(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Nº Contestação', 'Multa', 'Matrícula', 'Contestante', 'NIF',
            'Motivo', 'Prioridade', 'Data Submissão', 'Prazo', 'Estado',
            'Decisão', 'Decisão Por',
        ];

        $linhas = $items->map(fn ($c) => [
            $c->numero,
            $c->multa_codigo,
            $c->matricula,
            $c->contestante,
            $c->nif,
            $c->motivo,
            $c->prioridade,
            $c->data_submissao?->format('d/m/Y') ?? '—',
            $c->prazo_limite?->format('d/m/Y') ?? '—',
            $c->estado,
            $c->decisao ?? '',
            $c->decisao_por ?? '',
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Contestações'),
            'gestmul-contestacoes-' . date('Y-m-d') . '.xlsx',
        );
    }

    /**
     * GET /api/gestmul/contestacoes/pdf
     */
    public function pdf(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Nº Contestação', 'Matrícula', 'Contestante', 'Motivo',
            'Prioridade', 'Prazo', 'Estado',
        ];

        $linhas = $items->map(fn ($c) => [
            $c->numero,
            $c->matricula,
            $c->contestante,
            $c->motivo,
            $c->prioridade,
            $c->prazo_limite?->format('d/m/Y') ?? '—',
            $c->estado,
        ])->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — GESTMUL — Sistema de Gestão de Multas',
            'sigla'       => 'GESTMUL',
            'rodape'      => 'SICAM — GESTMUL · Contestações',
            'titulo'      => 'Contestações de Multas',
            'subtitulo'   => 'Lista de contestações submetidas',
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Total'      => $items->count(),
                'Pendentes'  => $items->where('estado', 'Pendente')->count(),
                'Em análise' => $items->where('estado', 'Em análise')->count(),
                'Aceites'    => $items->where('estado', 'Aceite')->count(),
                'Rejeitadas' => $items->where('estado', 'Rejeitada')->count(),
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('gestmul-contestacoes-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Contestacao $c): array
    {
        return [
            'id'             => (string) $c->id,
            'numero'         => $c->numero,
            'multaCodigo'    => $c->multa_codigo,
            'matricula'      => $c->matricula,
            'infracao'       => $c->infracao,
            'valorMulta'     => (float) $c->valor_multa,
            'contestante'    => $c->contestante,
            'nif'            => $c->nif,
            'contacto'       => $c->contacto ?? '—',
            'motivo'         => $c->motivo,
            'descricao'      => $c->descricao,
            'prioridade'     => $c->prioridade,
            'dataSubmissao'  => $c->data_submissao?->format('d/m/Y') ?? '—',
            'prazoLimite'    => $c->prazo_limite?->format('d/m/Y') ?? '—',
            'estado'         => $c->estado,
            'decisao'        => $c->decisao,
            'decisaoEm'      => $c->decisao_em?->format('d/m/Y H:i'),
            'decisaoPor'     => $c->decisao_por,
            'observacoes'    => $c->observacoes,
            'criadoEm'       => $c->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function kpis(): array
    {
        $pendentes = Contestacao::where('estado', 'Pendente')->count();
        $emAnalise = Contestacao::where('estado', 'Em análise')->count();

        return [
            'total'       => Contestacao::count(),
            'pendentes'   => $pendentes,
            'emAnalise'   => $emAnalise,
            'aceites'     => Contestacao::where('estado', 'Aceite')->count(),
            'rejeitadas'  => Contestacao::where('estado', 'Rejeitada')->count(),
            'canceladas'  => Contestacao::where('estado', 'Cancelada')->count(),
            'valorEmCausa'=> (float) Contestacao::whereIn('estado', ['Pendente', 'Em análise'])
                                ->sum('valor_multa'),
            'aExpirar'    => Contestacao::whereIn('estado', ['Pendente', 'Em análise'])
                                ->whereBetween('prazo_limite', [
                                    Carbon::today(),
                                    Carbon::today()->addDays(7),
                                ])
                                ->count(),
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Contestacao::query();

        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('motivo'))     $query->where('motivo', $request->motivo);
        if ($request->filled('prioridade')) $query->where('prioridade', $request->prioridade);

        return $query->orderByDesc('data_submissao')->limit(500)->get();
    }

    private function gerarNumero(): string
    {
        $ano = now()->year;
        $ultimo = Contestacao::whereYear('created_at', $ano)
            ->orderByDesc('id')
            ->first();

        $seq = 1;
        if ($ultimo && preg_match('/(\d{6})$/', $ultimo->numero, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('CT-%d-%06d', $ano, $seq);
    }

    private function parseDataPt(string $data): string
    {
        // aceita "dd/mm/aaaa" ou "aaaa-mm-dd"
        if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $data)) {
            [$d, $m, $a] = explode('/', $data);
            return "{$a}-{$m}-{$d}";
        }
        return $data;
    }
}