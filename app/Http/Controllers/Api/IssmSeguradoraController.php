<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Seguradora;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class IssmSeguradoraController extends Controller
{
    /**
     * GET /api/issm/seguradoras
     */
    public function index(Request $request)
    {
        $query = Seguradora::query();

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('nome', 'like', "%{$q}%")
                    ->orWhere('sigla', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
                    ->orWhere('nif', 'like', "%{$q}%");
            });
        }

        if ($request->filled('categoria')) $query->where('categoria', $request->categoria);
        if ($request->filled('estado'))    $query->where('estado', $request->estado);
        if ($request->filled('provincia')) $query->where('provincia', $request->provincia);

        $items = $query
            ->orderBy('nome')
            ->limit(300)
            ->get()
            ->map(fn ($s) => $this->formatar($s));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/issm/seguradoras/{id}
     */
    public function show(int $id)
    {
        $s = Seguradora::findOrFail($id);
        return response()->json($this->formatar($s));
    }

    /**
     * POST /api/issm/seguradoras
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'codigo'              => 'required|string|max:20|unique:seguradoras,codigo',
            'sigla'               => 'required|string|max:20',
            'nome'                => 'required|string|max:160',
            'nif'                 => 'required|string|size:9|unique:seguradoras,nif',
            'categoria'           => 'required|string|in:Nacional,Internacional,Mútua',
            'email'               => 'required|email|max:120',
            'telefone'            => 'required|string|max:30',
            'website'             => 'nullable|string|max:160',
            'endereco'            => 'required|string|max:200',
            'cidade'              => 'required|string|max:120',
            'provincia'           => 'required|string|max:120',
            'responsavel'         => 'required|string|max:120',
            'contactoResponsavel' => 'nullable|string|max:30',
            'estado'              => 'nullable|string|in:Activa,Suspensa,Inactiva',
            'observacoes'         => 'nullable|string',
        ]);

        $s = Seguradora::create([
            'codigo'              => strtoupper($dados['codigo']),
            'sigla'               => strtoupper($dados['sigla']),
            'nome'                => $dados['nome'],
            'nif'                 => $dados['nif'],
            'categoria'           => $dados['categoria'],
            'email'               => $dados['email'],
            'telefone'            => $dados['telefone'],
            'website'             => $dados['website'] ?? null,
            'endereco'            => $dados['endereco'],
            'cidade'              => $dados['cidade'],
            'provincia'           => $dados['provincia'],
            'responsavel'         => $dados['responsavel'],
            'contacto_responsavel'=> $dados['contactoResponsavel'] ?? null,
            'numero_apoles'       => 0,
            'numero_sinistros'    => 0,
            'estado'              => $dados['estado'] ?? 'Activa',
            'observacoes'         => $dados['observacoes'] ?? null,
            'registada_em'        => now(),
            'criado_por'          => $request->user()?->id,
        ]);

        return response()->json([
            'id'         => (string) $s->id,
            'seguradora' => $this->formatar($s),
            'kpis'       => $this->kpis(),
        ], 201);
    }

    /**
     * PUT /api/issm/seguradoras/{id}
     */
    public function update(Request $request, int $id)
    {
        $s = Seguradora::findOrFail($id);

        $dados = $request->validate([
            'sigla'               => 'sometimes|string|max:20',
            'nome'                => 'sometimes|string|max:160',
            'nif'                 => 'sometimes|string|size:9|unique:seguradoras,nif,' . $id,
            'categoria'           => 'sometimes|string|in:Nacional,Internacional,Mútua',
            'email'               => 'sometimes|email|max:120',
            'telefone'            => 'sometimes|string|max:30',
            'website'             => 'nullable|string|max:160',
            'endereco'            => 'sometimes|string|max:200',
            'cidade'              => 'sometimes|string|max:120',
            'provincia'           => 'sometimes|string|max:120',
            'responsavel'         => 'sometimes|string|max:120',
            'contactoResponsavel' => 'nullable|string|max:30',
            'estado'              => 'sometimes|string|in:Activa,Suspensa,Inactiva',
            'observacoes'         => 'nullable|string',
        ]);

        $update = [];
        if (isset($dados['sigla']))               $update['sigla']               = strtoupper($dados['sigla']);
        if (isset($dados['nome']))                $update['nome']                = $dados['nome'];
        if (isset($dados['nif']))                 $update['nif']                 = $dados['nif'];
        if (isset($dados['categoria']))           $update['categoria']           = $dados['categoria'];
        if (isset($dados['email']))               $update['email']               = $dados['email'];
        if (isset($dados['telefone']))            $update['telefone']            = $dados['telefone'];
        if (array_key_exists('website', $dados))  $update['website']             = $dados['website'];
        if (isset($dados['endereco']))            $update['endereco']            = $dados['endereco'];
        if (isset($dados['cidade']))              $update['cidade']              = $dados['cidade'];
        if (isset($dados['provincia']))           $update['provincia']           = $dados['provincia'];
        if (isset($dados['responsavel']))         $update['responsavel']         = $dados['responsavel'];
        if (array_key_exists('contactoResponsavel', $dados)) $update['contacto_responsavel'] = $dados['contactoResponsavel'];
        if (isset($dados['estado']))              $update['estado']              = $dados['estado'];
        if (array_key_exists('observacoes', $dados)) $update['observacoes']      = $dados['observacoes'];

        $s->update($update);

        return response()->json([
            'id'         => (string) $s->id,
            'seguradora' => $this->formatar($s->fresh()),
            'kpis'       => $this->kpis(),
        ]);
    }

    /**
     * DELETE /api/issm/seguradoras/{id}
     */
    public function destroy(int $id)
    {
        $s = Seguradora::findOrFail($id);
        $s->delete();

        return response()->json([
            'ok'   => true,
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/issm/seguradoras/{id}/activar
     */
    public function activar(int $id)
    {
        $s = Seguradora::findOrFail($id);
        $s->update(['estado' => 'Activa']);

        return response()->json([
            'id'         => (string) $s->id,
            'seguradora' => $this->formatar($s->fresh()),
            'kpis'       => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/issm/seguradoras/{id}/suspender
     */
    public function suspender(int $id)
    {
        $s = Seguradora::findOrFail($id);
        $s->update(['estado' => 'Suspensa']);

        return response()->json([
            'id'         => (string) $s->id,
            'seguradora' => $this->formatar($s->fresh()),
            'kpis'       => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/issm/seguradoras/{id}/inactivar
     */
    public function inactivar(int $id)
    {
        $s = Seguradora::findOrFail($id);
        $s->update(['estado' => 'Inactiva']);

        return response()->json([
            'id'         => (string) $s->id,
            'seguradora' => $this->formatar($s->fresh()),
            'kpis'       => $this->kpis(),
        ]);
    }

    /**
     * GET /api/issm/seguradoras/xlsx
     */
    public function xlsx(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Código', 'Sigla', 'Nome', 'NIF', 'Categoria', 'Email', 'Telefone',
            'Endereço', 'Cidade', 'Província', 'Responsável', 'Nº Apólices',
            'Nº Sinistros', 'Estado',
        ];

        $linhas = $items->map(fn ($s) => [
            $s->codigo, $s->sigla, $s->nome, $s->nif, $s->categoria,
            $s->email, $s->telefone,
            $s->endereco, $s->cidade, $s->provincia, $s->responsavel,
            (string) $s->numero_apoles, (string) $s->numero_sinistros,
            $s->estado,
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Seguradoras'),
            'issm-seguradoras-' . date('Y-m-d') . '.xlsx',
        );
    }

    /**
     * GET /api/issm/seguradoras/pdf
     */
    public function pdf(Request $request)
    {
        $items = $this->listarParaExport($request);

        $cabecalhos = [
            'Código', 'Nome', 'Categoria', 'Província',
            'Telefone', 'Nº Apólices', 'Estado',
        ];

        $linhas = $items->map(fn ($s) => [
            $s->codigo,
            $s->nome,
            $s->categoria,
            $s->provincia,
            $s->telefone,
            (string) $s->numero_apoles,
            $s->estado,
        ])->toArray();

        $pdf = Pdf::loadView('pdf.exportacao', [
            'instituicao' => 'SICAM — Instituto de Supervisão de Seguros de Moçambique',
            'sigla'       => 'ISSM',
            'rodape'      => 'SICAM — ISSM · Seguradoras',
            'titulo'      => 'Seguradoras',
            'subtitulo'   => 'Registo das seguradoras autorizadas',
            'cabecalhos'  => $cabecalhos,
            'linhas'      => $linhas,
            'resumo'      => [
                'Total'    => $items->count(),
                'Activas'  => $items->where('estado', 'Activa')->count(),
                'Suspensas'=> $items->where('estado', 'Suspensa')->count(),
                'Inactivas'=> $items->where('estado', 'Inactiva')->count(),
            ],
            'geradoEm'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('issm-seguradoras-' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Seguradora $s): array
    {
        return [
            'id'                  => (string) $s->id,
            'codigo'              => $s->codigo,
            'sigla'               => $s->sigla,
            'nome'                => $s->nome,
            'nif'                 => $s->nif,
            'categoria'           => $s->categoria,
            'email'               => $s->email,
            'telefone'            => $s->telefone,
            'website'             => $s->website,
            'endereco'            => $s->endereco,
            'cidade'              => $s->cidade,
            'provincia'           => $s->provincia,
            'responsavel'         => $s->responsavel,
            'contactoResponsavel' => $s->contacto_responsavel ?? '—',
            'numeroApoles'        => (int) $s->numero_apoles,
            'numeroSinistros'     => (int) $s->numero_sinistros,
            'estado'              => $s->estado,
            'observacoes'         => $s->observacoes,
            'registadaEm'         => $s->registada_em?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function kpis(): array
    {
        return [
            'total'          => Seguradora::count(),
            'activas'        => Seguradora::where('estado', 'Activa')->count(),
            'suspensas'      => Seguradora::where('estado', 'Suspensa')->count(),
            'inactivas'      => Seguradora::where('estado', 'Inactiva')->count(),
            'totalApoles'    => Seguradora::sum('numero_apoles'),
            'totalSinistros' => Seguradora::sum('numero_sinistros'),
        ];
    }

    private function listarParaExport(Request $request)
    {
        $query = Seguradora::query();

        if ($request->filled('categoria')) $query->where('categoria', $request->categoria);
        if ($request->filled('estado'))    $query->where('estado', $request->estado);
        if ($request->filled('provincia')) $query->where('provincia', $request->provincia);

        return $query->orderBy('nome')->limit(500)->get();
    }
}