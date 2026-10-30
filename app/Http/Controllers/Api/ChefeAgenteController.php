<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Multa;
use App\Models\Patrulha;
use App\Models\User;
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

        // Enriquecer com dados de patrulha + multas
        $items = $agentes->map(fn ($u) => $this->formatar($u));

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
        return response()->json($this->formatar($u));
    }

    /**
     * POST /api/chefe/agentes
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome'      => 'required|string|max:120',
            'email'     => 'required|email|unique:users,email',
            'telefone'  => 'nullable|string|max:30',
            'nSerie'    => 'required|string|max:60|unique:users,numero_id',
            'posto'     => 'required|string|max:120',
            'estado'    => 'nullable|string|in:Online,Em patrulha,Offline,Inactivo',
            'password'  => 'nullable|string|min:6',
        ]);

        $user = User::create([
            'name'       => $dados['nome'],
            'email'      => $dados['email'],
            'telefone'   => $dados['telefone'] ?? null,
            'numero_id'  => $dados['nSerie'],
            'perfil_id'  => self::PERFIL_AGENTE,
            'posto'      => $dados['posto'],
            'estado'     => 'Ativo',
            'password'   => Hash::make($dados['password'] ?? 'sicam123'),
        ]);

        return response()->json([
            'id'     => (string) $user->id,
            'agente' => $this->formatar($user),
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

        return response()->json([
            'id'     => (string) $u->id,
            'agente' => $this->formatar($u),
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

        return response()->json([
            'id'     => (string) $u->id,
            'agente' => $this->formatar($u),
        ]);
    }

    /**
     * PUT /api/chefe/agentes/{id}/desactivar
     */
    public function desactivar(int $id)
    {
        $u = User::where('perfil_id', self::PERFIL_AGENTE)->findOrFail($id);
        $u->update(['estado' => 'Inactivo']);

        return response()->json([
            'id'     => (string) $u->id,
            'agente' => $this->formatar($u),
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(User $u): array
    {
        // Patrulha em curso (mais recente)
        $patrulha = Patrulha::where('agente_id', $u->id)
            ->where('estado', 'Em curso')
            ->orderByDesc('inicio')
            ->first();

        // Multas emitidas por este agente
        $multasEmitidas = Multa::where('agente_id', $u->id)->count();

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
                                    ? \Carbon\Carbon::parse($u->ultimo_acesso)->format('d/m H:i')
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