<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InatroAgendamentoController extends Controller
{
    /**
     * GET /api/agendamentos
     */
    public function index(Request $request)
    {
        $query = Agendamento::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $termo = '%' . $request->q . '%';
                $q->where(function ($sub) use ($termo) {
                    $sub->where('matricula', 'like', $termo)
                        ->orWhere('codigo', 'like', $termo)
                        ->orWhere('proprietario', 'like', $termo);
                });
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('posto'),  fn ($q) => $q->where('posto_nome', $request->posto))
            ->when($request->filled('data'),   fn ($q) => $q->whereDate('data', $request->data))
            ->orderByDesc('data')
            ->orderByDesc('hora');

        $items = $query->get()->map(fn (Agendamento $a) => $this->formatar($a))->values();

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * GET /api/agendamentos/kpis
     */
    public function kpis()
    {
        $hoje = now()->toDateString();
        $fimSemana = now()->addDays(7)->toDateString();

        return [
            'hoje'        => Agendamento::whereDate('data', $hoje)->count(),
            'semana'      => Agendamento::whereDate('data', '>=', $hoje)->whereDate('data', '<=', $fimSemana)->count(),
            'confirmados' => Agendamento::where('estado', 'Confirmado')->count(),
            'cancelados'  => Agendamento::where('estado', 'Cancelado')->count(),
            'concluidos'  => Agendamento::where('estado', 'Concluído')->count(),
            'pendentes'   => Agendamento::where('estado', 'Pendente')->count(),
        ];
    }

    /**
     * POST /api/agendamentos
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula'    => ['required', 'string', 'max:20'],
            'marca'        => ['nullable', 'string', 'max:60'],
            'modelo'       => ['nullable', 'string', 'max:60'],
            'proprietario' => ['nullable', 'string', 'max:120'],
            'nif'          => ['nullable', 'string', 'max:20'],
            'telefone'     => ['nullable', 'string', 'max:30'],
            'data'         => ['required', 'date'],
            'hora'         => ['required', 'string', 'max:10'],
            'duracao'      => ['nullable', 'integer'],
            'tipo'         => ['nullable', 'string', 'max:50'],
            'posto_nome'   => ['nullable', 'string', 'max:120'],
            'inspetor'     => ['nullable', 'string', 'max:120'],
            'observacoes'  => ['nullable', 'string'],
        ]);

        try {
            $agendamento = DB::transaction(function () use ($dados) {
                $veiculo = Veiculo::where('matricula', $dados['matricula'])->first();

                $ano = now()->year;
                $ultimo = Agendamento::where('codigo', 'like', "AG-{$ano}-%")->lockForUpdate()->count();
                $codigo = sprintf('AG-%d-%04d', $ano, $ultimo + 1);

                return Agendamento::create([
                    'codigo'       => $codigo,
                    'veiculo_id'   => $veiculo?->id,
                    'matricula'    => strtoupper($dados['matricula']),
                    'marca'        => $dados['marca'] ?? $veiculo?->marca,
                    'modelo'       => $dados['modelo'] ?? $veiculo?->modelo,
                    'proprietario' => $dados['proprietario'] ?? $veiculo?->proprietario_nome,
                    'nif'          => $dados['nif'] ?? $veiculo?->proprietario_numero_id,
                    'telefone'     => $dados['telefone'] ?? null,
                    'data'         => $dados['data'],
                    'hora'         => $dados['hora'],
                    'duracao'      => $dados['duracao'] ?? 30,
                    'tipo'         => $dados['tipo'] ?? 'Periódica',
                    'posto_nome'   => $dados['posto_nome'] ?? null,
                    'inspetor'     => $dados['inspetor'] ?? null,
                    'estado'       => 'Pendente',
                    'observacoes'  => $dados['observacoes'] ?? null,
                    'criado_por'   => auth()->id(),
                ]);
            });

            return response()->json([
                'message' => 'Agendamento criado com sucesso.',
                'data'    => $this->formatar($agendamento),
                'kpis'    => $this->kpis(),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Falha ao criar agendamento.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/agendamentos/{id}
     */
    public function show($id)
    {
        $a = Agendamento::find($id);
        if (!$a) return response()->json(['message' => 'Agendamento não encontrado.'], 404);
        return response()->json($this->formatar($a));
    }

    /**
     * PUT /api/agendamentos/{id}
     */
    public function update(Request $request, $id)
    {
        $a = Agendamento::find($id);
        if (!$a) return response()->json(['message' => 'Agendamento não encontrado.'], 404);

        $dados = $request->validate([
            'data'        => ['sometimes', 'date'],
            'hora'        => ['sometimes', 'string', 'max:10'],
            'posto_nome'  => ['nullable', 'string', 'max:120'],
            'inspetor'    => ['nullable', 'string', 'max:120'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $a->update($dados);

        return response()->json([
            'message' => 'Agendamento atualizado.',
            'data'    => $this->formatar($a->fresh()),
            'kpis'    => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/agendamentos/{id}/confirmar
     */
    public function confirmar($id)
    {
        $a = Agendamento::find($id);
        if (!$a) return response()->json(['message' => 'Agendamento não encontrado.'], 404);
        $a->update(['estado' => 'Confirmado']);

        return response()->json([
            'data' => $this->formatar($a->fresh()),
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/agendamentos/{id}/concluir
     */
    public function concluir($id)
    {
        $a = Agendamento::find($id);
        if (!$a) return response()->json(['message' => 'Agendamento não encontrado.'], 404);
        $a->update(['estado' => 'Concluído']);

        return response()->json([
            'data' => $this->formatar($a->fresh()),
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/agendamentos/{id}/cancelar
     */
    public function cancelar($id)
    {
        $a = Agendamento::find($id);
        if (!$a) return response()->json(['message' => 'Agendamento não encontrado.'], 404);
        $a->update(['estado' => 'Cancelado']);

        return response()->json([
            'data' => $this->formatar($a->fresh()),
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * DELETE /api/agendamentos/{id}
     */
    public function destroy($id)
    {
        $a = Agendamento::find($id);
        if (!$a) return response()->json(['message' => 'Agendamento não encontrado.'], 404);
        
        $a->delete();

        return response()->json([
            'ok'   => true,
            'kpis' => $this->kpis(),
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Agendamento $a): array
    {
        return [
            'id'           => (string) $a->id,
            'codigo'       => $a->codigo,
            'matricula'    => $a->matricula,
            'marca'        => $a->marca,
            'modelo'       => $a->modelo,
            'proprietario' => $a->proprietario,
            'nif'          => $a->nif,
            'telefone'     => $a->telefone,
            'data'         => $a->data?->format('d/m/Y') ?? '',
            'hora'         => $a->hora,
            'duracao'      => $a->duracao,
            'tipo'         => $a->tipo,
            'posto'        => $a->posto_nome, // Frontend espera 'posto'
            'inspetor'     => $a->inspetor,
            'estado'       => $a->estado,
            'observacoes'  => $a->observacoes,
        ];
    }

    /**
     * ⭐ Método interno chamado pelo PrmHotlistController quando o carro sai da Hotlist
     */
    public static function criarAgendamentoAutomatico(Veiculo $veiculo): void
    {
        $data = now()->addDays(2)->toDateString();
        $hora = '09:00';

        $ano = now()->year;
        $ultimo = Agendamento::where('codigo', 'like', "AG-{$ano}-%")->count();
        $codigo = sprintf('AG-%d-%04d', $ano, $ultimo + 1);

        Agendamento::create([
            'codigo'       => $codigo,
            'veiculo_id'   => $veiculo->id,
            'matricula'    => $veiculo->matricula,
            'marca'        => $veiculo->marca,
            'modelo'       => $veiculo->modelo,
            'proprietario' => $veiculo->proprietario_nome,
            'nif'          => $veiculo->proprietario_numero_id,
            'telefone'     => null,
            'data'         => $data,
            'hora'         => $hora,
            'duracao'      => 30,
            'tipo'         => 'Reinspecção',
            'posto_nome'   => 'Posto Central',
            'inspetor'     => null,
            'estado'       => 'Pendente',
            'observacoes'  => 'Agendamento automático gerado após regularização da Hotlist.',
            'criado_por'   => auth()->id() ?? 1,
        ]);
    }
}