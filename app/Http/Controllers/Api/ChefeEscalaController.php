<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Escala;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ChefeEscalaController extends Controller
{
    private const PERFIL_AGENTE = 30003;

    private const DIAS_PADRAO = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

    /**
     * GET /api/chefe/escalas
     */
    public function index(Request $request)
    {
        $query = Escala::query()->with('agente:id,name,numero_id,posto');

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->whereHas('agente', function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('numero_id', 'like', "%{$q}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('posto')) {
            $query->where('posto', $request->posto);
        }

        $items = $query
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->map(fn ($e) => $this->formatar($e));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/chefe/escalas/{id}
     */
    public function show(int $id)
    {
        $e = Escala::with('agente:id,name,numero_id,posto')->findOrFail($id);
        return response()->json($this->formatar($e));
    }

    /**
     * POST /api/chefe/escalas
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'agente_id'           => 'required|integer|exists:users,id',
            'posto'               => 'required|string|max:120',
            'semana'              => 'required|string|max:60',
            'estado'              => 'nullable|string|in:Rascunho,Publicada',
            'atribuicoes'         => 'required|array|size:7',
            'atribuicoes.*.dia'   => 'required|string',
            'atribuicoes.*.ativo' => 'required|boolean',
            'atribuicoes.*.turno' => 'nullable|string|in:Manhã,Tarde,Noite',
            'notas'               => 'nullable|string',
        ]);

        $e = Escala::create([
            'agente_id'   => $dados['agente_id'],
            'posto'       => $dados['posto'],
            'semana'      => $dados['semana'],
            'estado'      => $dados['estado'] ?? 'Rascunho',
            'atribuicoes' => $dados['atribuicoes'],
            'notas'       => $dados['notas'] ?? null,
            'criado_por'  => $request->user()?->id,
        ]);

        return response()->json([
            'id'     => (string) $e->id,
            'escala' => $this->formatar($e->fresh('agente')),
        ], 201);
    }

    /**
     * PUT /api/chefe/escalas/{id}
     */
    public function update(Request $request, int $id)
    {
        $e = Escala::findOrFail($id);

        $dados = $request->validate([
            'agente_id'           => 'sometimes|integer|exists:users,id',
            'posto'               => 'sometimes|string|max:120',
            'semana'              => 'sometimes|string|max:60',
            'estado'              => 'sometimes|string|in:Rascunho,Publicada',
            'atribuicoes'         => 'sometimes|array|size:7',
            'atribuicoes.*.dia'   => 'required_with:atribuicoes|string',
            'atribuicoes.*.ativo' => 'required_with:atribuicoes|boolean',
            'atribuicoes.*.turno' => 'nullable|string|in:Manhã,Tarde,Noite',
            'notas'               => 'nullable|string',
        ]);

        $e->update($dados);

        return response()->json([
            'id'     => (string) $e->id,
            'escala' => $this->formatar($e->fresh('agente')),
        ]);
    }

    /**
     * DELETE /api/chefe/escalas/{id}
     */
    public function destroy(int $id)
    {
        $e = Escala::findOrFail($id);
        $e->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * PUT /api/chefe/escalas/{id}/publicar
     */
    public function publicar(int $id)
    {
        $e = Escala::findOrFail($id);
        $e->update(['estado' => 'Publicada']);

        return response()->json([
            'id'     => (string) $e->id,
            'escala' => $this->formatar($e->fresh('agente')),
        ]);
    }

    /**
     * GET /api/chefe/escalas/xlsx
     */
    public function xlsx(Request $request)
    {
        $escalas = $this->listarParaExport($request);

        $cabecalhos = [
            'Agente', 'Nº Série', 'Posto', 'Semana',
            'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom',
            'Dias activos', 'Estado',
        ];

        $linhas = [];
        foreach ($escalas as $e) {
            $atr    = $e->atribuicoes ?? [];
            $porDia = collect($atr)->keyBy('dia');

            $linhas[] = [
                $e->agente?->name ?? '—',
                $e->agente?->numero_id ?? '—',
                $e->posto,
                $e->semana,
                $this->diaTurno($porDia, 'Seg'),
                $this->diaTurno($porDia, 'Ter'),
                $this->diaTurno($porDia, 'Qua'),
                $this->diaTurno($porDia, 'Qui'),
                $this->diaTurno($porDia, 'Sex'),
                $this->diaTurno($porDia, 'Sáb'),
                $this->diaTurno($porDia, 'Dom'),
                (string) collect($atr)->where('ativo', true)->count() . '/7',
                $e->estado,
            ];
        }

        $nomeFicheiro = sprintf('escalas-turno-%s.xlsx', date('Y-m-d'));

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Escalas de Turno'),
            $nomeFicheiro,
        );
    }

    /**
     * GET /api/chefe/escalas/pdf
     */
    public function pdf(Request $request)
    {
        $escalas = $this->listarParaExport($request);

        $cabecalhos = [
            'Agente', 'Posto', 'Semana',
            'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom',
            'Dias', 'Estado',
        ];

        $linhas = [];
        foreach ($escalas as $e) {
            $atr    = $e->atribuicoes ?? [];
            $porDia = collect($atr)->keyBy('dia');

            $linhas[] = [
                $e->agente?->name ?? '—',
                $e->posto,
                $e->semana,
                $this->diaTurno($porDia, 'Seg'),
                $this->diaTurno($porDia, 'Ter'),
                $this->diaTurno($porDia, 'Qua'),
                $this->diaTurno($porDia, 'Qui'),
                $this->diaTurno($porDia, 'Sex'),
                $this->diaTurno($porDia, 'Sáb'),
                $this->diaTurno($porDia, 'Dom'),
                (string) collect($atr)->where('ativo', true)->count() . '/7',
                $e->estado,
            ];
        }

        $pdf = Pdf::loadView('pdf.exportacao', [
            'titulo'     => 'Escalas de Turno',
            'subtitulo'  => 'Agentes sob supervisão do posto',
            'cabecalhos' => $cabecalhos,
            'linhas'     => $linhas,
            'resumo'     => [
                'Total de escalas' => $escalas->count(),
                'Publicadas'       => $escalas->where('estado', 'Publicada')->count(),
                'Rascunhos'        => $escalas->where('estado', 'Rascunho')->count(),
            ],
            'geradoEm'   => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download(sprintf('escalas-turno-%s.pdf', date('Y-m-d')));
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Escala $e): array
    {
        $agente = $e->agente;

        return [
            'id'          => (string) $e->id,
            'agenteId'    => (string) ($agente?->id ?? ''),
            'agente'      => $agente?->name ?? '—',
            'nSerie'      => $agente?->numero_id ?? '—',
            'posto'       => $e->posto ?? '—',
            'semana'      => $e->semana ?? '—',
            'estado'      => $e->estado ?? 'Rascunho',
            'atribuicoes' => $e->atribuicoes ?? $this->atribuicoesPadrao(),
            'notas'       => $e->notas,
            'criadoEm'    => $e->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function atribuicoesPadrao(): array
    {
        return array_map(fn ($d) => [
            'dia'   => $d,
            'ativo' => false,
            'turno' => 'Manhã',
        ], self::DIAS_PADRAO);
    }

    private function kpis(): array
    {
        $total      = Escala::count();
        $publicadas = Escala::where('estado', 'Publicada')->count();
        $rascunhos  = Escala::where('estado', 'Rascunho')->count();

        $escalas = Escala::all();
        $cobertura = 0;
        if ($escalas->count() > 0) {
            $soma = 0;
            foreach ($escalas as $e) {
                $atr = $e->atribuicoes ?? [];
                $ativos = collect($atr)->where('ativo', true)->count();
                $soma += $ativos;
            }
            $mediaPorEscala = $soma / $escalas->count();
            $cobertura = round(($mediaPorEscala / 7) * 100, 1);
        }

        return [
            'total'      => $total,
            'publicadas' => $publicadas,
            'rascunhos'  => $rascunhos,
            'cobertura'  => $cobertura,
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Escala::query()->with('agente:id,name,numero_id,posto');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('posto')) {
            $query->where('posto', $request->posto);
        }

        return $query
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();
    }

    private function diaTurno($porDia, string $dia): string
    {
        $a = $porDia[$dia] ?? null;
        if (!$a || empty($a['ativo'])) return 'Folga';
        return $a['turno'] ?? '—';
    }
}