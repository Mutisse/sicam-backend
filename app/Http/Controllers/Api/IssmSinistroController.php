<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Apolice;
use App\Models\Sinistro;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class IssmSinistroController extends Controller
{
    public function index(Request $request)
    {
        $query = Sinistro::query()->with('apolice:id,numero');

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('matricula', 'like', "%{$q}%")
                    ->orWhere('segurado', 'like', "%{$q}%");
            });
        }

        if ($request->filled('tipo'))       $query->where('tipo', $request->tipo);
        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('gravidade'))  $query->where('gravidade', $request->gravidade);
        if ($request->filled('seguradora')) $query->where('seguradora', $request->seguradora);

        $items = $query
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->map(fn ($s) => $this->formatar($s));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    public function show(int $id)
    {
        $s = Sinistro::with('apolice:id,numero')->findOrFail($id);
        return response()->json($this->formatar($s));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'numero'         => 'required|string|max:40|unique:sinistros,numero',
            'matricula'      => 'required|string|max:20',
            'apolice'        => 'nullable|string|max:40',
            'segurado'       => 'required|string|max:120',
            'seguradora'     => 'required|string|max:120',
            'tipo'           => 'required|string|in:Colisão,Furto,Incêndio,Inundação,Vandalismo',
            'gravidade'      => 'required|string|in:Leve,Moderado,Grave',
            'data'           => 'required|string',
            'local'          => 'required|string|max:200',
            'descricao'      => 'required|string',
            'valorEstimado'  => 'required|integer|min:0',
            'valorAprovado'  => 'nullable|integer|min:0',
            'estado'         => 'nullable|string|in:Aberto,Em análise,Resolvido,Recusado',
            'responsavel'    => 'required|string|max:120',
            'observacoes'    => 'nullable|string',
        ]);

        // Tentar encontrar apólice pelo número
        $apoliceId = null;
        if (!empty($dados['apolice'])) {
            $apoliceId = Apolice::where('numero', $dados['apolice'])->value('id');
        }

        $s = Sinistro::create([
            'numero'         => $dados['numero'],
            'apolice_id'     => $apoliceId,
            'matricula'      => strtoupper($dados['matricula']),
            'segurado'       => $dados['segurado'],
            'seguradora'     => $dados['seguradora'],
            'tipo'           => $dados['tipo'],
            'gravidade'      => $dados['gravidade'],
            'data'           => $this->parseData($dados['data']),
            'local'          => $dados['local'],
            'descricao'      => $dados['descricao'],
            'valor_estimado' => $dados['valorEstimado'],
            'valor_aprovado' => $dados['valorAprovado'] ?? null,
            'estado'         => $dados['estado'] ?? 'Aberto',
            'responsavel'    => $dados['responsavel'],
            'observacoes'    => $dados['observacoes'] ?? null,
            'criado_por'     => $request->user()?->id,
        ]);

        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s->fresh('apolice')),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $s = Sinistro::findOrFail($id);

        $dados = $request->validate([
            'matricula'      => 'sometimes|string|max:20',
            'apolice'        => 'nullable|string|max:40',
            'segurado'       => 'sometimes|string|max:120',
            'seguradora'     => 'sometimes|string|max:120',
            'tipo'           => 'sometimes|string|in:Colisão,Furto,Incêndio,Inundação,Vandalismo',
            'gravidade'      => 'sometimes|string|in:Leve,Moderado,Grave',
            'data'           => 'sometimes|string',
            'local'          => 'sometimes|string|max:200',
            'descricao'      => 'sometimes|string',
            'valorEstimado'  => 'sometimes|integer|min:0',
            'valorAprovado'  => 'nullable|integer|min:0',
            'estado'         => 'sometimes|string|in:Aberto,Em análise,Resolvido,Recusado',
            'responsavel'    => 'sometimes|string|max:120',
            'observacoes'    => 'nullable|string',
        ]);

        $update = [];
        if (isset($dados['matricula']))      $update['matricula']      = strtoupper($dados['matricula']);
        if (isset($dados['segurado']))       $update['segurado']       = $dados['segurado'];
        if (isset($dados['seguradora']))     $update['seguradora']     = $dados['seguradora'];
        if (isset($dados['tipo']))           $update['tipo']           = $dados['tipo'];
        if (isset($dados['gravidade']))      $update['gravidade']      = $dados['gravidade'];
        if (isset($dados['data']))           $update['data']           = $this->parseData($dados['data']);
        if (isset($dados['local']))          $update['local']          = $dados['local'];
        if (isset($dados['descricao']))      $update['descricao']      = $dados['descricao'];
        if (isset($dados['valorEstimado']))  $update['valor_estimado'] = $dados['valorEstimado'];
        if (array_key_exists('valorAprovado', $dados)) $update['valor_aprovado'] = $dados['valorAprovado'];
        if (isset($dados['estado']))         $update['estado']         = $dados['estado'];
        if (isset($dados['responsavel']))    $update['responsavel']    = $dados['responsavel'];
        if (array_key_exists('observacoes', $dados)) $update['observacoes'] = $dados['observacoes'];

        if (!empty($dados['apolice'])) {
            $update['apolice_id'] = Apolice::where('numero', $dados['apolice'])->value('id');
        }

        $s->update($update);

        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s->fresh('apolice')),
        ]);
    }

    public function destroy(int $id)
    {
        $s = Sinistro::findOrFail($id);
        $s->delete();
        return response()->json(['ok' => true]);
    }

    public function marcarEmAnalise(int $id)
    {
        $s = Sinistro::findOrFail($id);
        $s->update(['estado' => 'Em análise']);
        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s->fresh('apolice')),
        ]);
    }

    public function marcarResolvido(Request $request, int $id)
    {
        $s = Sinistro::findOrFail($id);
        $update = ['estado' => 'Resolvido'];
        if ($request->filled('valorAprovado')) {
            $update['valor_aprovado'] = (int) $request->valorAprovado;
        }
        $s->update($update);
        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s->fresh('apolice')),
        ]);
    }

    public function recusar(int $id)
    {
        $s = Sinistro::findOrFail($id);
        $s->update(['estado' => 'Recusado']);
        return response()->json([
            'id'       => (string) $s->id,
            'sinistro' => $this->formatar($s->fresh('apolice')),
        ]);
    }

    public function xlsx(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Nº Sinistro', 'Matrícula', 'Segurado', 'Seguradora',
            'Tipo', 'Gravidade', 'Data', 'Valor Estimado', 'Estado', 'Responsável',
        ];

        $linhas = $items->map(fn ($s) => [
            $s->numero,
            $s->matricula,
            $s->segurado,
            $s->seguradora,
            $s->tipo,
            $s->gravidade,
            $s->data?->format('d/m/Y') ?? '—',
            (string) $s->valor_estimado,
            $s->estado,
            $s->responsavel,
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Sinistros'),
            'issm-sinistros-' . date('Y-m-d') . '.xlsx',
        );
    }

    public function pdf(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Nº Sinistro', 'Matrícula', 'Seguradora',
            'Tipo', 'Gravidade', 'Data', 'Estado',
        ];

        $linhas = $items->map(fn ($s) => [
            $s->numero,
            $s->matricula,
            $s->seguradora,
            $s->tipo,
            $s->gravidade,
            $s->data?->format('d/m/Y') ?? '—',
            $s->estado,
        ])->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Instituto de Supervisão de Seguros de Moçambique',
            'sigla'       => 'ISSM',
            'rodape'      => 'SICAM — ISSM · Sinistros',
            'titulo'      => 'Sinistros',
            'subtitulo'   => 'Registo de sinistros associados a apólices',
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Total'      => $items->count(),
                'Abertos'    => $items->where('estado', 'Aberto')->count(),
                'Em análise' => $items->where('estado', 'Em análise')->count(),
                'Resolvidos' => $items->where('estado', 'Resolvido')->count(),
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('issm-sinistros-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Sinistro $s): array
    {
        return [
            'id'            => (string) $s->id,
            'numero'        => $s->numero,
            'matricula'     => $s->matricula,
            'apolice'       => $s->apolice?->numero ?? '—',
            'segurado'      => $s->segurado,
            'seguradora'    => $s->seguradora,
            'tipo'          => $s->tipo,
            'gravidade'     => $s->gravidade,
            'data'          => $s->data?->format('d/m/Y') ?? '—',
            'local'         => $s->local,
            'descricao'     => $s->descricao,
            'valorEstimado' => (int) $s->valor_estimado,
            'valorAprovado' => $s->valor_aprovado ? (int) $s->valor_aprovado : null,
            'estado'        => $s->estado,
            'responsavel'   => $s->responsavel,
            'observacoes'   => $s->observacoes,
            'criadoEm'      => $s->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function kpis(): array
    {
        return [
            'total'        => Sinistro::count(),
            'abertos'      => Sinistro::where('estado', 'Aberto')->count(),
            'emAnalise'    => Sinistro::where('estado', 'Em análise')->count(),
            'resolvidos'   => Sinistro::where('estado', 'Resolvido')->count(),
            'recusados'    => Sinistro::where('estado', 'Recusado')->count(),
            'valorTotal'   => Sinistro::sum('valor_estimado'),
            'valorAprovado'=> Sinistro::sum('valor_aprovado'),
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Sinistro::query();

        if ($request->filled('tipo'))       $query->where('tipo', $request->tipo);
        if ($request->filled('estado'))     $query->where('estado', $request->estado);
        if ($request->filled('gravidade'))  $query->where('gravidade', $request->gravidade);
        if ($request->filled('seguradora')) $query->where('seguradora', $request->seguradora);

        return $query->orderByDesc('created_at')->limit(500)->get();
    }

    private function parseData(string $data): Carbon
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $data, $m)) {
            $data = "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return Carbon::parse($data);
    }
}