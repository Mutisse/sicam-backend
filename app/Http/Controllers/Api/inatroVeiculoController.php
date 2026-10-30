<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Posto;
use App\Models\Veiculo;
use App\Services\NotificacaoService;
use Illuminate\Http\Request;

class VeiculoController extends Controller
{
    /**
     * GET /api/veiculos
     */
    public function index(Request $request)
    {
        $query = Veiculo::query();

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('matricula', 'like', "%{$q}%")
                    ->orWhere('marca', 'like', "%{$q}%")
                    ->orWhere('modelo', 'like', "%{$q}%");
            });
        }
        if ($request->filled('provincia')) {
            $query->where('provincia', $request->provincia);
        }

        $veiculos = $query->with('posto:id,nome')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($v) => $this->formatar($v));

        return response()->json([
            'items' => $veiculos,
            'kpis'  => $this->kpis(),
        ]);
    }

    private function kpis(): array
    {
        $v = Veiculo::selectRaw("
            COUNT(*) as registados,
            SUM(CASE WHEN estado = 'regular'   THEN 1 ELSE 0 END) as ok,
            SUM(CASE WHEN estado = 'critico'   THEN 1 ELSE 0 END) as criticos,
            SUM(CASE WHEN estado = 'sem-dados' THEN 1 ELSE 0 END) as pendentes
        ")->first();

        return [
            'registados' => (int) $v->registados,
            'inspecaoOk' => (int) $v->ok,
            'inspecaoExp' => (int) $v->criticos,
            'pendentes'  => (int) $v->pendentes,
        ];
    }

    private function formatar(Veiculo $v): array
    {
        return [
            'id'          => (string) $v->id,
            'matricula'   => $v->matricula,
            'tipo'        => $v->tipo ?? '—',
            'marca'       => $v->marca ?? '—',
            'modelo'      => $v->modelo ?? '—',
            'ano'         => $v->ano,
            'cor'         => $v->cor ?? '—',
            'combustivel' => $v->combustivel ?? '—',
            'chassis'     => $v->chassis ?? '—',
            'proprietario' => $v->proprietario_nome ?? '—',
            'nif'         => $v->proprietario_numero_id ?? '—',
            'posto'       => $v->posto?->nome ?? '—',
            'provincia'   => $v->provincia ?? '—',
            'score'       => (int) $v->score,
            'estado'      => $v->estado ?? 'sem-dados',
            'data'        => $v->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    /**
     * POST /api/veiculos
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula'     => 'required|string|max:20|unique:veiculos,matricula',
            'tipo'          => 'required|string|max:40',
            'marca'         => 'required|string|max:60',
            'modelo'        => 'required|string|max:60',
            'ano'           => 'required|integer|min:1950|max:' . date('Y'),
            'cor'           => 'required|string|max:30',
            'combustivel'   => 'nullable|string|max:30',
            'chassis'       => 'nullable|string|max:30',
            'proprietario'  => 'required|string|max:120',
            'nif'           => 'required|string|size:9',
            'telefone'      => 'nullable|string|max:30',
            'provincia'     => 'required|string',
            'endereco'      => 'nullable|string',
            'posto'         => 'required|string',
        ]);

        if (!empty($dados['chassis'])) {
            $vin = strtoupper($dados['chassis']);
            if (Veiculo::where('chassis', $vin)->exists()) {
                return response()->json([
                    'message'      => 'Não passou',
                    'motivo_falha' => 'Número de chassis (VIN) duplicado — já registado no sistema',
                ], 422);
            }
        }

        $posto = Posto::where('nome', $dados['posto'])->first();

        $veiculo = Veiculo::create([
            'matricula'              => strtoupper($dados['matricula']),
            'tipo'                   => $dados['tipo'],
            'marca'                  => $dados['marca'],
            'modelo'                 => $dados['modelo'],
            'ano'                    => $dados['ano'],
            'cor'                    => $dados['cor'],
            'combustivel'            => $dados['combustivel'] ?? null,
            'chassis'                => isset($dados['chassis']) ? strtoupper($dados['chassis']) : null,
            'proprietario_nome'      => $dados['proprietario'],
            'proprietario_numero_id' => $dados['nif'],
            'posto_id'               => $posto?->id,
            'provincia'              => $dados['provincia'],
            'score'                  => 50,
            'estado'                 => 'sem-dados',
        ]);

        // 🔔 Notificar admin/chefe
        NotificacaoService::veiculoAdicionadoHotlist(
            $veiculo->matricula,
            'Registo novo',
            'medio'
        );

        return response()->json([
            'id'   => (string) $veiculo->id,
            'kpis' => $this->kpis(),
        ], 201);
    }

    /**
     * GET /api/veiculos/{id}
     */
    public function show(int $id)
    {
        $v = Veiculo::with('posto:id,nome')->findOrFail($id);
        return response()->json($this->formatar($v));
    }
}
