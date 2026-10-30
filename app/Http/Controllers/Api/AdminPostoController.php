<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Posto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AdminPostoController extends Controller
{
    /**
     * GET /api/admin/postos
     */
    public function index(Request $request)
    {
        $query = Posto::query();

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('nome', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
                    ->orWhere('distrito', 'like', "%{$q}%");
            });
        }

        if ($request->filled('provincia')) {
            $query->where('provincia', $request->provincia);
        }

        // Contagem real de agentes por posto (nome do posto na tabela users)
        $agentesPorPosto = DB::table('users')
            ->select('posto', DB::raw('COUNT(*) as total'))
            ->whereNotNull('posto')
            ->groupBy('posto')
            ->pluck('total', 'posto')
            ->toArray();

        // Contagem de veículos por posto (quando atribuídos)
        $criticosPorPosto = [];
        if (DB::getSchemaBuilder()->hasColumn('veiculos', 'posto_id')) {
            $criticosPorPosto = DB::table('veiculos')
                ->select('posto_id', DB::raw('COUNT(*) as total'))
                ->whereNotNull('posto_id')
                ->groupBy('posto_id')
                ->pluck('total', 'posto_id')
                ->toArray();
        }

        $items = $query
            ->orderBy('nome')
            ->limit(500)
            ->get()
            ->map(function ($p) use ($agentesPorPosto, $criticosPorPosto) {
                return [
                    'id'         => (string) $p->id,
                    'codigo'     => $p->codigo,
                    'nome'       => $p->nome,
                    'provincia'  => $p->provincia,
                    'distrito'   => $p->distrito,
                    'agentes'    => (int) ($agentesPorPosto[$p->nome] ?? 0),
                    'criticos'   => (int) ($criticosPorPosto[$p->id] ?? 0),
                    'cor'        => $p->cor ?? '#2563eb',
                    'endereco'   => $p->endereco,
                    'telefone'   => $p->telefone,
                    'notas'      => $p->notas,
                    'estado'     => $p->estado ?? 'Ativo',
                ];
            });

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis($agentesPorPosto, $criticosPorPosto),
        ]);
    }

    /**
     * GET /api/admin/postos/{id}
     */
    public function show(int $id)
    {
        $p = Posto::findOrFail($id);

        $agentes = DB::table('users')->where('posto', $p->nome)->count();
        $criticos = 0;
        if (DB::getSchemaBuilder()->hasColumn('veiculos', 'posto_id')) {
            $criticos = DB::table('veiculos')->where('posto_id', $p->id)->count();
        }

        return response()->json([
            'id'        => (string) $p->id,
            'codigo'    => $p->codigo,
            'nome'      => $p->nome,
            'provincia' => $p->provincia,
            'distrito'  => $p->distrito,
            'agentes'   => $agentes,
            'criticos'  => $criticos,
            'cor'       => $p->cor ?? '#2563eb',
            'endereco'  => $p->endereco,
            'telefone'  => $p->telefone,
            'notas'     => $p->notas,
            'estado'    => $p->estado ?? 'Ativo',
        ]);
    }

    /**
     * POST /api/admin/postos
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'codigo'    => 'required|string|max:30|unique:postos,codigo',
            'nome'      => 'required|string|max:160',
            'provincia' => 'required|string|max:120',
            'cor'       => 'nullable|string|max:20',
            'endereco'  => 'nullable|string|max:200',
            'telefone'  => 'nullable|string|max:30',
            'notas'     => 'nullable|string',
        ]);

        $p = Posto::create([
            'codigo'    => strtoupper($dados['codigo']),
            'nome'      => $dados['nome'],
            'provincia' => $dados['provincia'],
            'distrito'  => '',
            'endereco'  => $dados['endereco'] ?? null,
            'telefone'  => $dados['telefone'] ?? null,
            'cor'       => $dados['cor'] ?? '#2563eb',
            'notas'     => $dados['notas'] ?? null,
            'estado'    => 'Ativo',
        ]);

        return response()->json([
            'id'    => (string) $p->id,
            'posto' => $this->show((int) $p->id)->getData(true),
        ], 201);
    }

    /**
     * PUT /api/admin/postos/{id}
     */
    public function update(Request $request, int $id)
    {
        $p = Posto::findOrFail($id);

        $dados = $request->validate([
            'codigo'    => 'sometimes|string|max:30|unique:postos,codigo,' . $id,
            'nome'      => 'sometimes|string|max:160',
            'provincia' => 'sometimes|string|max:120',
            'cor'       => 'sometimes|string|max:20',
            'endereco'  => 'nullable|string|max:200',
            'telefone'  => 'nullable|string|max:30',
            'notas'     => 'nullable|string',
        ]);

        $update = [];
        if (isset($dados['codigo']))    $update['codigo']    = strtoupper($dados['codigo']);
        if (isset($dados['nome']))      $update['nome']      = $dados['nome'];
        if (isset($dados['provincia'])) $update['provincia'] = $dados['provincia'];
        if (isset($dados['cor']))       $update['cor']       = $dados['cor'];
        if (array_key_exists('endereco', $dados)) $update['endereco'] = $dados['endereco'];
        if (array_key_exists('telefone', $dados)) $update['telefone'] = $dados['telefone'];
        if (array_key_exists('notas', $dados))    $update['notas']    = $dados['notas'];

        $p->update($update);

        return response()->json([
            'id'    => (string) $p->id,
            'posto' => $this->show((int) $p->id)->getData(true),
        ]);
    }

    /**
     * DELETE /api/admin/postos/{id}
     */
    public function destroy(int $id)
    {
        $p = Posto::findOrFail($id);

        $agentes = DB::table('users')->where('posto', $p->nome)->count();
        if ($agentes > 0) {
            return response()->json([
                'message' => "Este posto tem {$agentes} agente(s) atribuído(s).",
            ], 422);
        }

        $p->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/admin/postos/xlsx
     */
    public function xlsx()
    {
        $postos = Posto::orderBy('nome')->get();

        $agentesPorPosto = DB::table('users')
            ->select('posto', DB::raw('COUNT(*) as total'))
            ->whereNotNull('posto')
            ->groupBy('posto')
            ->pluck('total', 'posto')
            ->toArray();

        $cabecalhos = ['Código', 'Nome', 'Província', 'Distrito', 'Agentes', 'Telefone'];
        $linhas = $postos->map(fn ($p) => [
            $p->codigo,
            $p->nome,
            $p->provincia,
            $p->distrito ?? '—',
            (string) ($agentesPorPosto[$p->nome] ?? 0),
            $p->telefone ?? '—',
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Postos'),
            'admin-postos-' . date('Y-m-d') . '.xlsx',
        );
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function kpis(array $agentesPorPosto, array $criticosPorPosto): array
    {
        return [
            'total'      => Posto::count(),
            'agentes'    => array_sum($agentesPorPosto),
            'criticos'   => array_sum($criticosPorPosto),
            'provincias' => Posto::distinct('provincia')->count('provincia'),
        ];
    }
}