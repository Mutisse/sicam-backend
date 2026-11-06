<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Abordagem;
use App\Models\Posto;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PrmAbordagemController extends Controller
{
    /**
     * GET /api/abordagens
     * Devolve { items, kpis } — contrato esperado pelo abordagens.service.ts.
     */
    public function index(Request $request)
    {
        $query = Abordagem::with([
                'veiculo:id,matricula,marca,modelo',
                'posto:id,nome,latitude,longitude', // Traz as coordenadas reais do posto
                'agente:id,name',
            ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $termo = '%' . $request->q . '%';
                $q->where(function ($sub) use ($termo) {
                    $sub->where('codigo', 'like', $termo)
                        ->orWhere('local', 'like', $termo)
                        ->orWhereHas('veiculo', fn ($v) => $v->where('matricula', 'like', $termo));
                });
            })
            ->when($request->filled('resultado'),   fn ($q) => $q->where('resultado', $request->resultado))
            ->when($request->filled('data_inicio'), fn ($q) => $q->whereDate('data', '>=', $request->data_inicio))
            ->when($request->filled('data_fim'),    fn ($q) => $q->whereDate('data', '<=', $request->data_fim))
            ->orderByDesc('data')
            ->orderByDesc('id');

        $items = $query->get()->map(function (Abordagem $a) {
            $lat = null;
            $lng = null;

            // Se o Posto existir e tiver coordenadas válidas (diferentes de 0), usa-as
            if ($a->posto && $a->posto->latitude && $a->posto->longitude) {
                $lat = (float) $a->posto->latitude;
                $lng = (float) $a->posto->longitude;
            }

            return [
                'id'          => (string) $a->id,
                'codigo'      => $a->codigo,
                'dataHora'    => $a->data ? $a->data->format('d/m/Y') : '',
                'matricula'   => $a->veiculo->matricula ?? '',
                'agente'      => $a->agente->name ?? '',
                'posto'       => $a->posto->nome ?? '',
                'local'       => $a->local ?? '',
                'resultado'   => $a->resultado,
                'emHotlist'   => false, // Podes implementar lógica real aqui se quiseres
                'observacoes' => $a->observacoes,
                'diasAtras'   => $a->data ? (int) $a->data->diffInDays(now()) : 0,
                'lat'         => $lat,
                'lng'         => $lng,
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'kpis'  => [
                'total'         => $items->count(),
                'semIncidentes' => $items->where('resultado', 'Sem incidentes')->count(),
                'comDetencao'   => $items->where('resultado', 'Com detenção')->count(),
                'emCurso'       => $items->where('resultado', 'Em curso')->count(),
                'hotlist'       => 0,
            ],
        ]);
    }

    /**
     * POST /api/abordagens
     * Frontend envia: { matricula, agente, posto, local, resultado, observacoes }
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula'   => ['required', 'string', 'max:20', 'exists:veiculos,matricula'],
            'agente'      => ['nullable', 'string', 'max:255'],
            'posto'       => ['nullable', 'string', 'max:255'],
            'local'       => ['nullable', 'string', 'max:255'],
            'resultado'   => ['required', Rule::in(['Sem incidentes', 'Com detenção', 'Em curso'])],
            'observacoes' => ['nullable', 'string'],
        ]);

        try {
            $abordagem = DB::transaction(function () use ($dados) {
                $veiculo = Veiculo::where('matricula', $dados['matricula'])->firstOrFail();

                // Resolve agente por nome (se existir)
                $agenteId = null;
                if (!empty($dados['agente'])) {
                    $agenteId = User::where('name', $dados['agente'])->value('id');
                }

                // Resolve posto por nome
                $postoId = null;
                if (!empty($dados['posto'])) {
                    $postoId = Posto::where('nome', $dados['posto'])->value('id');
                }

                $ultimo = Abordagem::lockForUpdate()->max('id') ?? 0;
                $codigo = 'AB-' . str_pad($ultimo + 1, 3, '0', STR_PAD_LEFT);

                return Abordagem::create([
                    'codigo'      => $codigo,
                    'veiculo_id'  => $veiculo->id,
                    'agente_id'   => $agenteId,
                    'posto_id'    => $postoId,
                    'local'       => $dados['local']       ?? null,
                    'data'        => now()->toDateString(),
                    'resultado'   => $dados['resultado'],
                    'observacoes' => $dados['observacoes'] ?? null,
                ]);
            });

            return response()->json([
                'id'   => (string) $abordagem->id,
                'kpis' => [], // o store recarrega a lista a seguir
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Falha ao registar a abordagem.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/abordagens/{id}
     */
    public function destroy($id)
    {
        $abordagem = Abordagem::find($id);
        if (!$abordagem) {
            return response()->json(['message' => 'Abordagem não encontrada.'], 404);
        }

        $abordagem->delete();
        return response()->json(['message' => 'Abordagem eliminada.']);
    }
}