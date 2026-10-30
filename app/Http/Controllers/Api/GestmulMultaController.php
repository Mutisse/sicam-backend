<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Multa;
use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class GestmulMultaController extends Controller
{
    /**
     * GET /api/gestmul/multas
     */
    public function index(Request $request)
    {
        $query = Multa::query()->with(['veiculo', 'agente', 'posto']);

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('infracao', 'like', "%{$q}%")
                    ->orWhere('provincia', 'like', "%{$q}%")
                    ->orWhereHas('veiculo', fn ($v) => $v->where('matricula', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('estado'))    $query->where('estado', $request->estado);
        if ($request->filled('infracao'))  $query->where('infracao', $request->infracao);
        if ($request->filled('agente')) {
            $query->whereHas('agente', fn ($a) => $a->where('name', $request->agente));
        }

        $items = $query
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn ($m) => $this->formatar($m));

        return response()->json([
            'items'  => $items,
            'kpis'   => $this->kpis(),
            'opcoes' => [
                'infracao' => Multa::select('infracao')->distinct()->pluck('infracao'),
                'agente'   => DB::table('users')
                    ->whereIn('id', DB::table('multas')->select('agente_id')->distinct())
                    ->select('name')
                    ->orderBy('name')
                    ->pluck('name'),
            ],
        ]);
    }

    /**
     * GET /api/gestmul/multas/{id}
     */
    public function show(int $id)
    {
        $m = Multa::with(['veiculo', 'agente', 'posto'])->findOrFail($id);
        return response()->json($this->formatar($m));
    }

    /**
     * PUT /api/gestmul/multas/{id}
     */
    public function update(Request $request, int $id)
    {
        $m = Multa::findOrFail($id);

        $dados = $request->validate([
            'infracao'    => 'sometimes|string|max:120',
            'valor'       => 'sometimes|numeric|min:0',
            'estado'      => 'sometimes|string|in:Pendente,Paga,Contestada,Anulada',
            'observacoes' => 'nullable|string',
        ]);

        $m->update($dados);

        return response()->json([
            'id'    => (string) $m->id,
            'multa' => $this->formatar($m->fresh()->load(['veiculo', 'agente', 'posto'])),
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * DELETE /api/gestmul/multas/{id}
     */
    public function destroy(int $id)
    {
        $m = Multa::findOrFail($id);
        $m->delete();

        return response()->json([
            'ok'   => true,
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/gestmul/multas/{id}/contestar
     */
    public function contestar(int $id)
    {
        $m = Multa::findOrFail($id);
        $m->update(['estado' => 'Contestada']);

        return response()->json([
            'id'    => (string) $m->id,
            'multa' => $this->formatar($m->fresh()->load(['veiculo', 'agente', 'posto'])),
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/gestmul/multas/{id}/anular
     */
    public function anular(int $id)
    {
        $m = Multa::findOrFail($id);
        $m->update(['estado' => 'Anulada']);

        return response()->json([
            'id'    => (string) $m->id,
            'multa' => $this->formatar($m->fresh()->load(['veiculo', 'agente', 'posto'])),
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * POST /api/gestmul/multas/{id}/pagar
     * Body: { metodo, pagador, referencia? }
     */
    public function pagar(Request $request, int $id)
    {
        $m = Multa::with(['veiculo'])->findOrFail($id);

        if ($m->estado === 'Paga') {
            return response()->json(['message' => 'Multa já se encontra paga.'], 422);
        }

        $dados = $request->validate([
            'metodo'     => 'required|string|in:Dinheiro,M-Pesa,Transferência,Multibanco',
            'pagador'    => 'required|string|max:160',
            'referencia' => 'nullable|string|max:120',
        ]);

        $m->update([
            'estado'         => 'Paga',
            'data_pagamento' => now()->toDateString(),
        ]);

        $recibo = Recibo::create([
            'numero'     => $this->gerarNumeroRecibo(),
            'multa_id'   => $m->id,
            'metodo'     => $dados['metodo'],
            'pagador'    => $dados['pagador'],
            'referencia' => $dados['referencia'] ?? null,
            'valor'      => $m->valor,
            'criado_por' => $request->user()?->id,
        ]);

        return response()->json([
            'multa'  => $this->formatar($m->fresh()->load(['veiculo', 'agente', 'posto'])),
            'recibo' => $this->formatarRecibo($recibo, $m->fresh()),
            'kpis'   => $this->kpis(),
        ]);
    }

    /**
     * GET /api/gestmul/multas/{id}/recibo/pdf
     */
    public function reciboPdf(int $id)
    {
        $m = Multa::with(['veiculo', 'agente', 'posto'])->findOrFail($id);

        $recibo = Recibo::where('multa_id', $m->id)->orderByDesc('id')->first();

        if (!$recibo) {
            $recibo = new Recibo([
                'numero'  => 'RC-' . $m->numero,
                'metodo'  => 'Pagamento anterior',
                'pagador' => $m->veiculo?->proprietario_nome ?? '—',
                'valor'   => $m->valor,
            ]);
        }

        $pdf = Pdf::loadView('pdf.recibo', [
            'instituicao' => 'SICAM — Sistema Integrado de Controlo Automóvel de Moçambique',
            'sigla'       => 'GESTMUL',
            'titulo'      => 'Recibo de pagamento de multa',
            'multa'       => $this->formatar($m),
            'recibo'      => $this->formatarRecibo($recibo, $m),
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a5', 'landscape');

        return $pdf->download("recibo-{$recibo->numero}.pdf");
    }

    /**
     * GET /api/gestmul/multas/xlsx
     */
    public function xlsx(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = ['Número', 'Matrícula', 'Infração', 'Agente', 'Posto', 'Província', 'Data', 'Valor', 'Estado'];
        $linhas = $items->map(fn ($m) => [
            $m->numero,
            $m->veiculo?->matricula ?? '—',
            $m->infracao,
            $m->agente?->name ?? '—',
            $m->posto?->nome ?? '—',
            $m->provincia,
            $m->data?->format('d/m/Y') ?? '—',
            (string) $m->valor,
            $m->estado,
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Multas'),
            'gestmul-multas-' . date('Y-m-d') . '.xlsx',
        );
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Multa $m): array
    {
        $v = $m->veiculo;

        return [
            'id'            => (string) $m->id,
            'codigo'        => $m->numero,
            'matricula'     => $v?->matricula ?? '—',
            'marca'         => $v?->marca ?? '—',
            'modelo'        => $v?->modelo ?? '—',
            'proprietario'  => $v?->proprietario_nome ?? '—',
            'infracao'      => $m->infracao,
            'agente'        => $m->agente?->name ?? '—',
            'posto'         => $m->posto?->nome ?? '—',
            'local'         => $m->posto?->endereco ?? $m->provincia,
            'provincia'     => $m->provincia,
            'data'          => $m->data?->format('d/m/Y') ?? '—',
            'dataPagamento' => $m->data_pagamento?->format('d/m/Y'),
            'valor'         => (float) $m->valor,
            'estado'        => $m->estado,
            'observacoes'   => $m->observacoes,
            'criadoEm'      => $m->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function formatarRecibo(Recibo $r, Multa $m): array
    {
        return [
            'numero'      => $r->numero,
            'data'        => $r->created_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
            'hora'        => $r->created_at?->format('H:i') ?? now()->format('H:i'),
            'multaCodigo' => $m->numero,
            'matricula'   => $m->veiculo?->matricula ?? '—',
            'infracao'    => $m->infracao,
            'metodo'      => $r->metodo,
            'pagador'     => $r->pagador,
            'referencia'  => $r->referencia,
            'valor'       => (float) $r->valor,
        ];
    }

    private function kpis(): array
    {
        return [
            'total'          => Multa::count(),
            'pagas'          => Multa::where('estado', 'Paga')->count(),
            'pendentes'      => Multa::where('estado', 'Pendente')->count(),
            'contestadas'    => Multa::where('estado', 'Contestada')->count(),
            'anuladas'       => Multa::where('estado', 'Anulada')->count(),
            'valorTotal'     => (float) Multa::sum('valor'),
            'valorPago'      => (float) Multa::where('estado', 'Paga')->sum('valor'),
            'valorPendente'  => (float) Multa::where('estado', 'Pendente')->sum('valor'),
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Multa::with(['veiculo', 'agente', 'posto']);

        if ($request->filled('estado'))    $query->where('estado', $request->estado);
        if ($request->filled('infracao'))  $query->where('infracao', $request->infracao);

        return $query->orderByDesc('data')->limit(1000)->get();
    }

    private function gerarNumeroRecibo(): string
    {
        $ano = now()->year;
        $ultimo = Recibo::whereYear('created_at', $ano)->orderByDesc('id')->first();

        $seq = 1;
        if ($ultimo && preg_match('/(\d{6})$/', $ultimo->numero, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('RC-%d-%06d', $ano, $seq);
    }
}