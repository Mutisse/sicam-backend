<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Patrulha;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ChefePatrulhaController extends Controller
{
    /**
     * GET /api/chefe/patrulhas
     */
    public function index(Request $request)
    {
        $query = Patrulha::query()->with('agente:id,name,numero_id');

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo', 'like', "%{$q}%")
                    ->orWhere('nome', 'like', "%{$q}%")
                    ->orWhere('local', 'like', "%{$q}%")
                    ->orWhere('viatura', 'like', "%{$q}%")
                    ->orWhereHas('agente', fn ($a) => $a->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('prioridade')) $query->where('prioridade', $request->prioridade);
        if ($request->filled('agente')) {
            $query->whereHas('agente', fn ($a) => $a->where('name', $request->agente));
        }

        $items = $query
            ->orderByDesc('inicio')
            ->limit(300)
            ->get()
            ->map(fn ($p) => $this->formatar($p));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/chefe/patrulhas/{id}
     */
    public function show(int $id)
    {
        $p = Patrulha::with('agente:id,name,numero_id')->findOrFail($id);
        return response()->json($this->formatar($p));
    }

    /**
     * POST /api/chefe/patrulhas
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome'              => 'required|string|max:120',
            'data'              => 'required|string',
            'horaInicio'        => 'required|string',
            'horaFim'           => 'nullable|string',
            'turno'             => 'required|string|in:Manhã,Tarde,Noite',
            'agente_id'         => 'required|integer|exists:users,id',
            'viatura'           => 'nullable|string|max:30',
            'local'             => 'required|string|max:120',
            'prioridade'        => 'required|string|in:Baixa,Média,Alta',
            'pontos_previstos'  => 'required|integer|min:1|max:100',
            'estado'            => 'nullable|string|in:Planeada,Em curso,Concluída,Cancelada',
            'notas'             => 'nullable|string',
        ]);

        $inicio = $this->parseDataHora($dados['data'], $dados['horaInicio']);
        $fim    = !empty($dados['horaFim'])
            ? $this->parseDataHora($dados['data'], $dados['horaFim'])
            : null;

        $p = Patrulha::create([
            'codigo'            => $this->gerarCodigo(),
            'nome'              => $dados['nome'],
            'turno'             => $dados['turno'],
            'posto_id'          => null,
            'agente_id'         => $dados['agente_id'],
            'viatura'           => $dados['viatura'] ?? null,
            'local'             => $dados['local'],
            'prioridade'        => $dados['prioridade'],
            'pontos_previstos'  => $dados['pontos_previstos'],
            'pontos_concluidos' => 0,
            'paragens'          => [],
            'notas'             => $dados['notas'] ?? null,
            'estado'            => $dados['estado'] ?? 'Planeada',
            'inicio'            => $inicio,
            'fim'               => $fim,
        ]);

        return response()->json([
            'id'       => (string) $p->id,
            'patrulha' => $this->formatar($p->fresh('agente')),
        ], 201);
    }

    /**
     * PUT /api/chefe/patrulhas/{id}
     */
    public function update(Request $request, int $id)
    {
        $p = Patrulha::findOrFail($id);

        $dados = $request->validate([
            'nome'              => 'sometimes|string|max:120',
            'data'              => 'sometimes|string',
            'horaInicio'        => 'sometimes|string',
            'horaFim'           => 'nullable|string',
            'turno'             => 'sometimes|string|in:Manhã,Tarde,Noite',
            'agente_id'         => 'sometimes|integer|exists:users,id',
            'viatura'           => 'nullable|string|max:30',
            'local'             => 'sometimes|string|max:120',
            'prioridade'        => 'sometimes|string|in:Baixa,Média,Alta',
            'pontos_previstos'  => 'sometimes|integer|min:1|max:100',
            'pontos_concluidos' => 'sometimes|integer|min:0',
            'estado'            => 'sometimes|string|in:Planeada,Em curso,Concluída,Cancelada',
            'notas'             => 'nullable|string',
        ]);

        if (isset($dados['data'], $dados['horaInicio'])) {
            $dados['inicio'] = $this->parseDataHora($dados['data'], $dados['horaInicio']);
        }
        if (isset($dados['data']) && array_key_exists('horaFim', $dados) && $dados['horaFim']) {
            $dados['fim'] = $this->parseDataHora($dados['data'], $dados['horaFim']);
        } elseif (array_key_exists('horaFim', $dados) && !$dados['horaFim']) {
            $dados['fim'] = null;
        }

        unset($dados['data'], $dados['horaInicio'], $dados['horaFim']);

        $p->update($dados);

        return response()->json([
            'id'       => (string) $p->id,
            'patrulha' => $this->formatar($p->fresh('agente')),
        ]);
    }

    /**
     * DELETE /api/chefe/patrulhas/{id}
     */
    public function destroy(int $id)
    {
        $p = Patrulha::findOrFail($id);
        $p->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * PUT /api/chefe/patrulhas/{id}/iniciar
     */
    public function iniciar(int $id)
    {
        $p = Patrulha::findOrFail($id);
        $p->update(['estado' => 'Em curso']);
        return response()->json([
            'id'       => (string) $p->id,
            'patrulha' => $this->formatar($p->fresh('agente')),
        ]);
    }

    /**
     * PUT /api/chefe/patrulhas/{id}/concluir
     */
    public function concluir(int $id)
    {
        $p = Patrulha::findOrFail($id);
        $p->update([
            'estado'            => 'Concluída',
            'pontos_concluidos' => $p->pontos_previstos,
            'fim'               => now(),
        ]);
        return response()->json([
            'id'       => (string) $p->id,
            'patrulha' => $this->formatar($p->fresh('agente')),
        ]);
    }

    /**
     * PUT /api/chefe/patrulhas/{id}/cancelar
     */
    public function cancelar(int $id)
    {
        $p = Patrulha::findOrFail($id);
        $p->update(['estado' => 'Cancelada']);
        return response()->json([
            'id'       => (string) $p->id,
            'patrulha' => $this->formatar($p->fresh('agente')),
        ]);
    }

    /**
     * GET /api/chefe/patrulhas/xlsx
     */
    public function xlsx(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Código', 'Nome', 'Data', 'Início', 'Fim', 'Turno',
            'Agente', 'Viatura', 'Zona', 'Pontos', 'Prioridade', 'Estado',
        ];

        $linhas = [];
        foreach ($items as $p) {
            $linhas[] = [
                $p->codigo ?? '—',
                $p->nome ?? '—',
                $p->inicio?->format('d/m/Y') ?? '—',
                $p->inicio?->format('H:i') ?? '—',
                $p->fim?->format('H:i') ?? '—',
                $p->turno ?? '—',
                $p->agente?->name ?? '—',
                $p->viatura ?? '—',
                $p->local ?? '—',
                $p->pontos_concluidos . '/' . $p->pontos_previstos,
                $p->prioridade ?? '—',
                $p->estado ?? '—',
            ];
        }

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Patrulhas'),
            'patrulhas-' . date('Y-m-d') . '.xlsx',
        );
    }

    /**
     * GET /api/chefe/patrulhas/pdf
     */
    public function pdf(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Código', 'Nome', 'Data', 'Turno', 'Agente',
            'Viatura', 'Zona', 'Prioridade', 'Estado',
        ];

        $linhas = [];
        foreach ($items as $p) {
            $linhas[] = [
                $p->codigo ?? '—',
                $p->nome ?? '—',
                $p->inicio?->format('d/m/Y') ?? '—',
                $p->turno ?? '—',
                $p->agente?->name ?? '—',
                $p->viatura ?? '—',
                $p->local ?? '—',
                $p->prioridade ?? '—',
                $p->estado ?? '—',
            ];
        }

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Polícia da República de Moçambique',
            'sigla'       => 'PRM',
            'rodape'      => 'SICAM — Chefe de Posto · Patrulhas',
            'titulo'      => 'Patrulhas',
            'subtitulo'   => 'Planeamento e acompanhamento',
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Total'      => $items->count(),
                'Planeadas'  => $items->where('estado', 'Planeada')->count(),
                'Em curso'   => $items->where('estado', 'Em curso')->count(),
                'Concluídas' => $items->where('estado', 'Concluída')->count(),
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('patrulhas-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Patrulha $p): array
    {
        return [
            'id'                => (string) $p->id,
            'codigo'            => $p->codigo ?? '—',
            'nome'              => $p->nome ?? '—',
            'data'              => $p->inicio?->format('d/m/Y') ?? '—',
            'horaInicio'        => $p->inicio?->format('H:i') ?? '—',
            'horaFim'           => $p->fim?->format('H:i') ?? '—',
            'turno'             => $p->turno ?? '—',
            'agente'            => $p->agente?->name ?? '—',
            'agenteId'          => (string) ($p->agente_id ?? ''),
            'viatura'           => $p->viatura ?? '—',
            'zona'              => $p->local ?? '—',
            'pontosPrevistos'   => (int) $p->pontos_previstos,
            'pontosConcluidos'  => (int) $p->pontos_concluidos,
            'prioridade'        => $p->prioridade ?? 'Média',
            'estado'            => $p->estado ?? 'Planeada',
            'paragens'          => $p->paragens ?? [],
            'notas'             => $p->notas,
            'criadoEm'          => $p->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function kpis(): array
    {
        return [
            'total'      => Patrulha::count(),
            'planeadas'  => Patrulha::where('estado', 'Planeada')->count(),
            'emCurso'    => Patrulha::where('estado', 'Em curso')->count(),
            'concluidas' => Patrulha::where('estado', 'Concluída')->count(),
            'canceladas' => Patrulha::where('estado', 'Cancelada')->count(),
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Patrulha::query()->with('agente:id,name,numero_id');

        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('prioridade')) $query->where('prioridade', $request->prioridade);

        return $query->orderByDesc('inicio')->limit(500)->get();
    }

    private function parseDataHora(string $data, string $hora): Carbon
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $data, $m)) {
            $data = "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return Carbon::parse("{$data} {$hora}");
    }

    private function gerarCodigo(): string
    {
        $ano = now()->format('y');
        $ultimo = Patrulha::where('codigo', 'like', "PT-{$ano}-%")->count() + 1;
        return sprintf('PT-%s-%05d', $ano, $ultimo);
    }
}