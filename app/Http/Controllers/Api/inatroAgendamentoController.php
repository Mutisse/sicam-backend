<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Posto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgendamentoController extends Controller
{
    /**
     * GET /api/agendamentos
     */
    public function index(Request $request)
    {
        $query = Agendamento::query();

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('matricula', 'like', "%{$q}%")
                    ->orWhere('proprietario', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('posto')) {
            $query->where('posto_nome', $request->posto);
        }
        if ($request->filled('inspetor')) {
            $query->where('inspetor', $request->inspetor);
        }
        if ($request->filled('data')) {
            $hoje = now()->startOfDay();
            switch ($request->data) {
                case 'hoje':
                    $query->whereDate('data', $hoje->toDateString());
                    break;
                case 'amanha':
                    $query->whereDate('data', $hoje->copy()->addDay()->toDateString());
                    break;
                case 'semana':
                    $query->whereBetween('data', [
                        $hoje->toDateString(),
                        $hoje->copy()->addDays(7)->toDateString(),
                    ]);
                    break;
            }
        }

        $items = $query
            ->orderBy('data')
            ->orderBy('hora')
            ->limit(500)
            ->get()
            ->map(fn($a) => $this->formatar($a));

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
        $hoje = now()->startOfDay();
        $fimSemana = $hoje->copy()->addDays(7);

        $r = Agendamento::selectRaw("
            SUM(CASE WHEN DATE(data) = ? AND estado <> 'Cancelado' THEN 1 ELSE 0 END) as hoje,
            SUM(CASE WHEN data BETWEEN ? AND ? AND estado IN ('Pendente','Confirmado') THEN 1 ELSE 0 END) as semana,
            SUM(CASE WHEN estado = 'Confirmado' THEN 1 ELSE 0 END) as confirmados,
            SUM(CASE WHEN estado = 'Cancelado'  THEN 1 ELSE 0 END) as cancelados,
            SUM(CASE WHEN estado = 'Concluído'  THEN 1 ELSE 0 END) as concluidos,
            SUM(CASE WHEN estado = 'Pendente'   THEN 1 ELSE 0 END) as pendentes
        ", [
            $hoje->toDateString(),
            $hoje->toDateString(),
            $fimSemana->toDateString(),
        ])->first();

        return [
            'hoje'       => (int) $r->hoje,
            'semana'     => (int) $r->semana,
            'confirmados' => (int) $r->confirmados,
            'cancelados' => (int) $r->cancelados,
            'concluidos' => (int) $r->concluidos,
            'pendentes'  => (int) $r->pendentes,
        ];
    }

    /**
     * POST /api/agendamentos
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula'    => 'required|string|max:20',
            'marca'        => 'required|string|max:60',
            'modelo'       => 'required|string|max:60',
            'proprietario' => 'required|string|max:120',
            'nif'          => 'required|string|size:9',
            'telefone'     => 'nullable|string|max:30',
            'data'         => 'required|date',
            'hora'         => 'required|string',
            'duracao'      => 'required|integer|min:15|max:240',
            'tipo'         => 'required|string|max:40',
            'posto'        => 'required|string|max:120',
            'inspetor'     => 'required|string|max:120',
            'observacoes'  => 'nullable|string',
        ]);

        // Verificar conflito de horário
        $conflito = Agendamento::whereDate('data', $dados['data'])
            ->where('hora', $dados['hora'])
            ->where('posto_nome', $dados['posto'])
            ->whereIn('estado', ['Pendente', 'Confirmado'])
            ->exists();

        if ($conflito) {
            return response()->json([
                'message' => 'Já existe um agendamento nesta hora para este posto',
            ], 422);
        }

        // Resolver posto_id
        $postoId = Posto::where('nome', $dados['posto'])->value('id');

        $agendamento = DB::transaction(function () use ($dados, $postoId, $request) {
            $a = Agendamento::create([
                'veiculo_id'   => null,
                'matricula'    => strtoupper($dados['matricula']),
                'marca'        => $dados['marca'],
                'modelo'       => $dados['modelo'],
                'proprietario' => $dados['proprietario'],
                'nif'          => $dados['nif'],
                'telefone'     => $dados['telefone'] ?? null,
                'data'         => $dados['data'],
                'hora'         => $dados['hora'],
                'duracao'      => $dados['duracao'],
                'tipo'         => $dados['tipo'],
                'posto_id'     => $postoId,
                'posto_nome'   => $dados['posto'],
                'inspetor'     => $dados['inspetor'],
                'estado'       => 'Pendente',
                'observacoes'  => $dados['observacoes'] ?? null,
                'criado_por'   => $request->user()?->id,
            ]);

            $ano = Carbon::parse($dados['data'])->format('Y');
            $a->update([
                'codigo' => sprintf('AGD-%s-%06d', $ano, $a->id),
            ]);

            return $a;
        });

        return response()->json([
            'id'     => (string) $agendamento->id,
            'codigo' => $agendamento->codigo,
            'kpis'   => $this->kpis(),
        ], 201);
    }

    /**
     * GET /api/agendamentos/{id}
     */
    public function show(int $id)
    {
        $a = Agendamento::findOrFail($id);
        return response()->json($this->formatar($a));
    }

    /**
     * PUT /api/agendamentos/{id}
     */
    public function update(Request $request, int $id)
    {
        $a = Agendamento::findOrFail($id);

        $dados = $request->validate([
            'matricula'    => 'sometimes|string|max:20',
            'marca'        => 'sometimes|string|max:60',
            'modelo'       => 'sometimes|string|max:60',
            'proprietario' => 'sometimes|string|max:120',
            'nif'          => 'sometimes|string|size:9',
            'telefone'     => 'nullable|string|max:30',
            'data'         => 'sometimes|date',
            'hora'         => 'sometimes|string',
            'duracao'      => 'sometimes|integer|min:15|max:240',
            'tipo'         => 'sometimes|string|max:40',
            'posto'        => 'sometimes|string|max:120',
            'inspetor'     => 'sometimes|string|max:120',
            'observacoes'  => 'nullable|string',
        ]);

        if (isset($dados['posto'])) {
            $dados['posto_nome'] = $dados['posto'];
            $dados['posto_id']   = Posto::where('nome', $dados['posto'])->value('id');
            unset($dados['posto']);
        }

        if (isset($dados['matricula'])) {
            $dados['matricula'] = strtoupper($dados['matricula']);
        }

        $a->update($dados);

        return response()->json([
            'id'     => (string) $a->id,
            'codigo' => $a->codigo,
            'kpis'   => $this->kpis(),
        ]);
    }

    /**
     * DELETE /api/agendamentos/{id}
     */
    public function destroy(int $id)
    {
        $a = Agendamento::findOrFail($id);
        $a->delete();

        return response()->json([
            'ok'   => true,
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/agendamentos/{id}/confirmar
     */
    public function confirmar(int $id)
    {
        $a = Agendamento::findOrFail($id);
        $a->update(['estado' => 'Confirmado']);

        return response()->json([
            'id'     => (string) $a->id,
            'estado' => $a->estado,
            'kpis'   => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/agendamentos/{id}/concluir
     */
    public function concluir(int $id)
    {
        $a = Agendamento::findOrFail($id);
        $a->update(['estado' => 'Concluído']);

        return response()->json([
            'id'     => (string) $a->id,
            'estado' => $a->estado,
            'kpis'   => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/agendamentos/{id}/cancelar
     */
    public function cancelar(int $id)
    {
        $a = Agendamento::findOrFail($id);
        $a->update(['estado' => 'Cancelado']);

        return response()->json([
            'id'     => (string) $a->id,
            'estado' => $a->estado,
            'kpis'   => $this->kpis(),
        ]);
    }

    /**
     * Formata um agendamento para JSON.
     */
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
            'telefone'     => $a->telefone ?? '—',
            'data'         => $a->data?->format('d/m/Y') ?? '—',
            'data_raw'     => $a->data?->format('Y-m-d'),
            'hora'         => $a->hora ? substr($a->hora, 0, 5) : '—',
            'duracao'      => (int) $a->duracao,
            'tipo'         => $a->tipo,
            'posto'        => $a->posto_nome ?? $a->posto?->nome ?? '—',
            'inspetor'     => $a->inspetor,
            'estado'       => $a->estado,
            'observacoes'  => $a->observacoes,
            'criadoEm'     => $a->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }
}
