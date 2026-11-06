<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Multa;
use App\Models\Patrulha;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ChefeAgenteController extends Controller
{
    private const PERFIL_AGENTE = 30003;

    /**
     * GET /api/chefe/agentes
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->where('perfil_id', self::PERFIL_AGENTE);

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('numero_id', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('telefone', 'like', "%{$q}%");
            });
        }

        if ($request->filled('posto')) {
            $query->where('posto', $request->posto);
        }

        $agentes = $query
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        // ⭐ Batch — evita N+1
        $ids = $agentes->pluck('id')->toArray();

        // Patrulhas em curso (1 query para todos)
        $patrulhasPorAgente = [];
        if (!empty($ids)) {
            $patrulhasPorAgente = Patrulha::whereIn('agente_id', $ids)
                ->where('estado', 'Em curso')
                ->orderByDesc('inicio')
                ->get()
                ->keyBy('agente_id'); // só a mais recente por agente
        }

        // Multas emitidas (1 query para todos)
        $multasPorAgente = [];
        if (!empty($ids)) {
            $multasPorAgente = Multa::select('agente_id', DB::raw('COUNT(*) as total'))
                ->whereIn('agente_id', $ids)
                ->groupBy('agente_id')
                ->pluck('total', 'agente_id')
                ->toArray();
        }

        $items = $agentes->map(fn ($u) => $this->formatar(
            $u,
            $patrulhasPorAgente[$u->id] ?? null,
            (int) ($multasPorAgente[$u->id] ?? 0),
        ));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis($items),
        ]);
    }

    /**
     * GET /api/chefe/agentes/{id}
     */
    public function show(int $id)
    {
        $u = User::where('perfil_id', self::PERFIL_AGENTE)->findOrFail($id);

        $patrulha = Patrulha::where('agente_id', $u->id)
            ->where('estado', 'Em curso')
            ->orderByDesc('inicio')
            ->first();

        $multasEmitidas = Multa::where('agente_id', $u->id)->count();

        return response()->json(
            $this->formatar($u, $patrulha, $multasEmitidas),
        );
    }

    /**
     * POST /api/chefe/agentes
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome'     => 'required|string|max:120',
            'email'    => 'required|email|unique:users,email',
            'telefone' => 'nullable|string|max:30',
            'nSerie'   => 'required|string|max:60|unique:users,numero_id',
            'posto'    => 'required|string|max:120',
            'estado'   => 'nullable|string|in:Online,Em patrulha,Offline,Inactivo',
            'password' => 'nullable|string|min:6',
        ]);

        $user = User::create([
            'name'      => $dados['nome'],
            'email'     => $dados['email'],
            'telefone'  => $dados['telefone'] ?? null,
            'numero_id' => $dados['nSerie'],
            'perfil_id' => self::PERFIL_AGENTE,
            'posto'     => $dados['posto'],
            'estado'    => 'Ativo',
            'password'  => Hash::make($dados['password'] ?? 'sicam123'),
        ]);

        return response()->json([
            'id'     => (string) $user->id,
            'agente' => $this->formatar($user, null, 0),
        ], 201);
    }

    /**
     * PUT /api/chefe/agentes/{id}
     */
    public function update(Request $request, int $id)
    {
        $u = User::where('perfil_id', self::PERFIL_AGENTE)->findOrFail($id);

        $dados = $request->validate([
            'nome'     => 'sometimes|string|max:120',
            'email'    => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($u->id)],
            'telefone' => 'nullable|string|max:30',
            'nSerie'   => ['sometimes', 'string', 'max:60', Rule::unique('users', 'numero_id')->ignore($u->id)],
            'posto'    => 'sometimes|string|max:120',
        ]);

        $u->update([
            'name'      => $dados['nome'] ?? $u->name,
            'email'     => $dados['email'] ?? $u->email,
            'telefone'  => $dados['telefone'] ?? $u->telefone,
            'numero_id' => $dados['nSerie'] ?? $u->numero_id,
            'posto'     => $dados['posto'] ?? $u->posto,
        ]);

        // Dados de patrulha e multas (para a resposta)
        $patrulha = Patrulha::where('agente_id', $u->id)
            ->where('estado', 'Em curso')
            ->orderByDesc('inicio')
            ->first();

        $multasEmitidas = Multa::where('agente_id', $u->id)->count();

        return response()->json([
            'id'     => (string) $u->id,
            'agente' => $this->formatar($u, $patrulha, $multasEmitidas),
        ]);
    }

    /**
     * DELETE /api/chefe/agentes/{id}
     */
    public function destroy(int $id)
    {
        $u = User::where('perfil_id', self::PERFIL_AGENTE)->findOrFail($id);
        $u->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * PUT /api/chefe/agentes/{id}/activar
     */
    public function activar(int $id)
    {
        $u = User::where('perfil_id', self::PERFIL_AGENTE)->findOrFail($id);
        $u->update(['estado' => 'Ativo']);

        $patrulha = Patrulha::where('agente_id', $u->id)
            ->where('estado', 'Em curso')
            ->orderByDesc('inicio')
            ->first();

        $multasEmitidas = Multa::where('agente_id', $u->id)->count();

        return response()->json([
            'id'     => (string) $u->id,
            'agente' => $this->formatar($u, $patrulha, $multasEmitidas),
        ]);
    }

    /**
     * PUT /api/chefe/agentes/{id}/desactivar
     */
    public function desactivar(int $id)
    {
        $u = User::where('perfil_id', self::PERFIL_AGENTE)->findOrFail($id);
        $u->update(['estado' => 'Inactivo']);

        $patrulha = Patrulha::where('agente_id', $u->id)
            ->where('estado', 'Em curso')
            ->orderByDesc('inicio')
            ->first();

        $multasEmitidas = Multa::where('agente_id', $u->id)->count();

        return response()->json([
            'id'     => (string) $u->id,
            'agente' => $this->formatar($u, $patrulha, $multasEmitidas),
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(
        User $u,
        ?Patrulha $patrulha = null,
        int $multasEmitidas = 0,
    ): array {
        // Estado derivado
        $estado = 'Offline';
        if ($patrulha) {
            $estado = 'Em patrulha';
        } elseif ($u->estado === 'Ativo') {
            $estado = 'Online';
        } elseif ($u->estado === 'Inactivo') {
            $estado = 'Inactivo';
        }

        return [
            'id'             => (string) $u->id,
            'nome'           => $u->name ?? '—',
            'email'          => $u->email ?? '—',
            'telefone'       => $u->telefone ?? '—',
            'nSerie'         => $u->numero_id ?? '—',
            'turno'          => $patrulha?->turno ?? '—',
            'viatura'        => $patrulha?->viatura ?? '—',
            'posto'          => $u->posto ?? '—',
            'estado'         => $estado,
            'ultimaSinc'     => $u->ultimo_acesso
                ? Carbon::parse($u->ultimo_acesso)->format('d/m H:i')
                : '—',
            'consultasHoje'  => 0, // stub
            'multasEmitidas' => $multasEmitidas,
            'admissao'       => $u->created_at?->format('d/m/Y') ?? '—',
            'observacoes'    => null,
            'criadoEm'       => $u->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function kpis($items): array
    {
        $total      = $items->count();
        $online     = $items->where('estado', 'Online')->count();
        $emPatrulha = $items->where('estado', 'Em patrulha')->count();
        $offline    = $items->where('estado', 'Offline')->count();
        $inactivos  = $items->where('estado', 'Inactivo')->count();

        return [
            'total'      => $total,
            'online'     => $online,
            'emPatrulha' => $emPatrulha,
            'offline'    => $offline,
            'inactivos'  => $inactivos,
        ];
    }
}