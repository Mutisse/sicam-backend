<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Hotlist;
use App\Models\Multa;
use App\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class GestmulPagamentoController extends Controller
{
    /**
     * GET /api/gestmul/pagamentos
     */
    public function index(Request $request)
    {
        $query = Recibo::query()->with(['multa.veiculo', 'multa.agente', 'multa.posto']);

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('pagador', 'like', "%{$q}%")
                    ->orWhere('referencia', 'like', "%{$q}%")
                    ->orWhereHas('multa', fn ($m) =>
                        $m->where('numero', 'like', "%{$q}%")
                          ->orWhereHas('veiculo', fn ($v) =>
                              $v->where('matricula', 'like', "%{$q}%")
                          )
                    );
            });
        }

        if ($request->filled('metodo')) $query->where('metodo', $request->metodo);
        if ($request->filled('estado')) $query->where('estado', $request->estado);

        $items = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn ($r) => $this->formatar($r));

        return response()->json([
            'items'  => $items,
            'kpis'   => $this->kpis(),
            'opcoes' => [
                'metodo' => Recibo::select('metodo')->distinct()->pluck('metodo'),
                'estado' => Recibo::select('estado')->distinct()->pluck('estado'),
            ],
        ]);
    }

    /**
     * GET /api/gestmul/pagamentos/{id}
     */
    public function show(int $id)
    {
        $r = Recibo::with(['multa.veiculo', 'multa.agente', 'multa.posto'])->findOrFail($id);
        return response()->json($this->formatar($r));
    }

    /**
     * PUT /api/gestmul/pagamentos/{id}
     */
    public function update(Request $request, int $id)
    {
        $r = Recibo::findOrFail($id);

        $dados = $request->validate([
            'metodo'      => 'sometimes|string|max:30',
            'pagador'     => 'sometimes|string|max:160',
            'referencia'  => 'nullable|string|max:120',
            'estado'      => 'sometimes|string|in:Confirmado,Pendente,Anulado',
            'observacoes' => 'nullable|string',
        ]);

        $r->update($dados);
        $r->load(['multa.veiculo', 'multa.agente', 'multa.posto']);

        return response()->json([
            'id'       => (string) $r->id,
            'pagamento'=> $this->formatar($r),
            'kpis'     => $this->kpis(),
        ]);
    }

    /**
     * DELETE /api/gestmul/pagamentos/{id}
     */
    public function destroy(int $id)
    {
        $r = Recibo::findOrFail($id);
        $r->delete();

        return response()->json([
            'ok'   => true,
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/gestmul/pagamentos/{id}/confirmar
     */
    public function confirmar(int $id)
    {
        $r = Recibo::findOrFail($id);
        $r->update(['estado' => 'Confirmado']);
        $r->load(['multa.veiculo', 'multa.agente', 'multa.posto']);

        return response()->json([
            'id'       => (string) $r->id,
            'pagamento'=> $this->formatar($r),
            'kpis'     => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/gestmul/pagamentos/{id}/anular
     */
    public function anular(int $id)
    {
        $r = Recibo::findOrFail($id);
        $r->update(['estado' => 'Anulado']);
        $r->load(['multa.veiculo', 'multa.agente', 'multa.posto']);

        return response()->json([
            'id'       => (string) $r->id,
            'pagamento'=> $this->formatar($r),
            'kpis'     => $this->kpis(),
        ]);
    }

    /**
     * POST /api/gestmul/pagamentos
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'multaNumero' => 'required|string|max:30',
            'metodo'      => 'required|string|in:Dinheiro,Transferência,Multibanco,M-Pesa',
            'pagador'     => 'required|string|max:160',
            'referencia'  => 'nullable|string|max:120',
            'estado'      => 'nullable|string|in:Confirmado,Pendente,Anulado',
            'observacoes' => 'nullable|string',
        ]);

        $multa = Multa::where('numero', $dados['multaNumero'])->first();
        if (!$multa) {
            return response()->json(['message' => 'Multa não encontrada.'], 422);
        }

        $estado = $dados['estado'] ?? 'Confirmado';

        $r = DB::transaction(function () use ($dados, $multa, $request, $estado) {
            $numero = $this->gerarNumeroRecibo();

            $recibo = Recibo::create([
                'numero'      => $numero,
                'multa_id'    => $multa->id,
                'metodo'      => $dados['metodo'],
                'pagador'     => $dados['pagador'],
                'referencia'  => $dados['referencia'] ?? null,
                'valor'       => $multa->valor,
                'estado'      => $estado,
                'observacoes' => $dados['observacoes'] ?? null,
                'criado_por'  => $request->user()?->id,
            ]);

            if ($estado === 'Confirmado') {
                $multa->update([
                    'estado'         => 'Paga',
                    'data_pagamento' => now()->toDateString(),
                ]);

                // LÓGICA DE NEGÓCIO: Verifica se deve sair da Hotlist
                $this->avaliarHotlistPorMultas($multa->veiculo_id);
            }

            return $recibo;
        });

        $r->load(['multa.veiculo', 'multa.agente', 'multa.posto']);

        return response()->json([
            'id'       => (string) $r->id,
            'pagamento'=> $this->formatar($r),
            'kpis'     => $this->kpis(),
        ], 201);
    }

    /**
     * GET /api/gestmul/pagamentos/{id}/recibo/pdf
     */
    public function reciboPdf(int $id)
    {
        $r = Recibo::with(['multa.veiculo', 'multa.agente', 'multa.posto'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.recibo', [
            'instituicao' => 'SICAM — Sistema Integrado de Controlo Automóvel de Moçambique',
            'sigla'       => 'GESTMUL',
            'titulo'      => 'Recibo de pagamento de multa',
            'multa'       => $this->formatarMulta($r->multa),
            'recibo'      => $this->formatarReciboPara($r),
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a5', 'landscape');

        return $pdf->download("recibo-{$r->numero}.pdf");
    }

    /**
     * GET /api/gestmul/pagamentos/xlsx
     */
    public function xlsx(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = ['Recibo', 'Matrícula', 'Multa', 'Método', 'Data', 'Valor', 'Pagador', 'Estado'];
        $linhas = $items->map(fn ($r) => [
            $r->numero,
            $r->multa?->veiculo?->matricula ?? '—',
            $r->multa?->numero ?? '—',
            $r->metodo,
            $r->created_at?->format('d/m/Y') ?? '—',
            (string) $r->valor,
            $r->pagador,
            $r->estado,
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Pagamentos'),
            'gestmul-pagamentos-' . date('Y-m-d') . '.xlsx',
        );
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function avaliarHotlistPorMultas(int $veiculoId): void
    {
        $pendentes = Multa::where('veiculo_id', $veiculoId)->where('estado', 'Pendente');
        $totalPendentes = $pendentes->count();
        $valorPendente = $pendentes->sum('valor');

        $hotlistAtiva = Hotlist::where('veiculo_id', $veiculoId)->where('estado', 'ativo')->first();

        if ($totalPendentes >= 3) {
            if ($hotlistAtiva) {
                $hotlistAtiva->update([
                    'multas_pendentes' => $totalPendentes,
                    'valor_multas'     => $valorPendente,
                ]);
            } else {
                Hotlist::create([
                    'veiculo_id'       => $veiculoId,
                    'motivo'           => 'Multas em atraso',
                    'perigo'           => 'medio',
                    'prioridade'       => 'media',
                    'multas_pendentes' => $totalPendentes,
                    'valor_multas'     => $valorPendente,
                    'estado'           => 'ativo',
                    'descricao'        => 'Inserido automaticamente por excesso de multas pendentes.',
                    'detectado_em'     => now(),
                ]);
            }
        } else {
            if ($hotlistAtiva && $hotlistAtiva->motivo === 'Multas em atraso') {
                $hotlistAtiva->update(['estado' => 'resolvido', 'resolvido_em' => now()]);
            }
        }
    }

    private function formatar(Recibo $r): array
    {
        $m = $r->multa;
        $v = $m?->veiculo;

        return [
            'id'          => (string) $r->id,
            'recibo'      => $r->numero,
            'matricula'   => $v?->matricula ?? '—',
            'multaCodigo' => $m?->numero ?? '—',
            'infracao'    => $m?->infracao ?? '—',
            'metodo'      => $r->metodo,
            'data'        => $r->created_at?->format('d/m/Y') ?? '—',
            'valor'       => (float) $r->valor,
            'pagador'     => $r->pagador,
            'referencia'  => $r->referencia,
            'estado'      => $r->estado,
            'observacoes' => $r->observacoes,
            'criadoEm'    => $r->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function formatarMulta(?Multa $m): array
    {
        if (!$m) return [];

        return [
            'id'          => (string) $m->id,
            'codigo'      => $m->numero,
            'matricula'   => $m->veiculo?->matricula ?? '—',
            'marca'       => $m->veiculo?->marca ?? '—',
            'modelo'      => $m->veiculo?->modelo ?? '—',
            'proprietario'=> $m->veiculo?->proprietario_nome ?? '—',
            'infracao'    => $m->infracao,
            'agente'      => $m->agente?->name ?? '—',
            'posto'       => $m->posto?->nome ?? '—',
            'local'       => $m->posto?->endereco ?? $m->provincia,
            'provincia'   => $m->provincia,
            'data'        => $m->data?->format('d/m/Y') ?? '—',
            'valor'       => (float) $m->valor,
            'estado'      => $m->estado,
        ];
    }

    private function formatarReciboPara(Recibo $r): array
    {
        $m = $r->multa;

        return [
            'numero'      => $r->numero,
            'data'        => $r->created_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
            'hora'        => $r->created_at?->format('H:i') ?? now()->format('H:i'),
            'multaCodigo' => $m?->numero ?? '—',
            'matricula'   => $m?->veiculo?->matricula ?? '—',
            'infracao'    => $m?->infracao ?? '—',
            'metodo'      => $r->metodo,
            'pagador'     => $r->pagador,
            'referencia'  => $r->referencia,
            'valor'       => (float) $r->valor,
        ];
    }

    private function kpis(): array
    {
        $row = Recibo::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Confirmado' THEN 1 ELSE 0 END) as confirmados,
            SUM(CASE WHEN estado = 'Pendente'   THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN estado = 'Anulado'    THEN 1 ELSE 0 END) as anulados,
            SUM(CASE WHEN estado = 'Confirmado' THEN valor ELSE 0 END) as valor_confirmado,
            SUM(CASE WHEN estado = 'Pendente'   THEN valor ELSE 0 END) as valor_pendente,
            SUM(valor) as valor_total
        ")->first();

        $total = (int) $row->total;

        return [
            'total'           => $total,
            'confirmados'     => (int) $row->confirmados,
            'pendentes'       => (int) $row->pendentes,
            'anulados'        => (int) $row->anulados,
            'valorConfirmado' => (float) $row->valor_confirmado,
            'valorPendente'   => (float) $row->valor_pendente,
            'valorTotal'      => (float) $row->valor_total,
            'ticketMedio'     => $total > 0 ? round($row->valor_total / $total) : 0,
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Recibo::with(['multa.veiculo']);

        if ($request->filled('metodo')) $query->where('metodo', $request->metodo);
        if ($request->filled('estado')) $query->where('estado', $request->estado);

        return $query->orderByDesc('created_at')->limit(1000)->get();
    }

    private function gerarNumeroRecibo(): string
    {
        $ano = now()->year;

        $ultimo = Recibo::whereYear('created_at', $ano)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $seq = 1;
        if ($ultimo && preg_match('/(\d{6})$/', $ultimo->numero, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('RC-%d-%06d', $ano, $seq);
    }
}