<?php
// app/Http/Controllers/Api/AdminUtilizadorController.php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificacaoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class AdminUtilizadorController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with('perfil:id,key,label');

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('numero_id', 'like', "%{$q}%");
            });
        }

        if ($request->filled('perfil')) {
            $query->whereHas('perfil', fn ($p) => $p->where('key', $request->perfil));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $items = $query
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn ($u) => $this->formatar($u));

        return response()->json([
            'items'  => $items,
            'kpis'   => $this->kpis(),
            'perfis' => $this->perfis(),
            'postos' => $this->postos(),
        ]);
    }

    public function show(int $id)
    {
        $u = User::with('perfil')->findOrFail($id);
        return response()->json($this->formatar($u));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome'             => 'required|string|max:160',
            'email'            => 'required|email|max:160|unique:users,email',
            'telefone'         => 'nullable|string|max:30',
            'perfil'           => 'required|string',
            'posto'            => 'required|string|max:120',
            'estado'           => 'nullable|string|in:Ativo,Bloqueado',
            'senha'            => 'required|string|min:8',
            'forcarAlteracao'  => 'boolean',
        ]);

        $perfil = DB::table('perfis')->where('key', $dados['perfil'])->first();
        if (!$perfil) {
            return response()->json(['message' => 'Perfil inválido.'], 422);
        }

        $u = User::create([
            'name'       => $dados['nome'],
            'email'      => strtolower($dados['email']),
            'telefone'   => $dados['telefone'] ?? null,
            'numero_id'  => $this->gerarNumeroId($dados['perfil']),
            'perfil_id'  => $perfil->id,
            'posto'      => $dados['posto'],
            'funcao'     => $perfil->label,
            'estado'     => $dados['estado'] ?? 'Ativo',
            'password'   => Hash::make($dados['senha']),
        ]);

        return response()->json([
            'id'          => (string) $u->id,
            'utilizador'  => $this->formatar($u->fresh()->load('perfil')),
            'kpis'        => $this->kpis(),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $u = User::findOrFail($id);

        $dados = $request->validate([
            'nome'     => 'sometimes|string|max:160',
            'email'    => 'sometimes|email|max:160|unique:users,email,' . $id,
            'telefone' => 'nullable|string|max:30',
            'perfil'   => 'sometimes|string',
            'posto'    => 'sometimes|string|max:120',
            'estado'   => 'sometimes|string|in:Ativo,Bloqueado',
        ]);

        $emailAntigo = $u->email;

        $update = [];
        if (isset($dados['nome']))     $update['name']     = $dados['nome'];
        if (isset($dados['email']))    $update['email']    = strtolower($dados['email']);
        if (array_key_exists('telefone', $dados)) $update['telefone'] = $dados['telefone'];
        if (isset($dados['posto']))    $update['posto']    = $dados['posto'];
        if (isset($dados['estado']))   $update['estado']   = $dados['estado'];

        if (isset($dados['perfil'])) {
            $perfil = DB::table('perfis')->where('key', $dados['perfil'])->first();
            if ($perfil) {
                $update['perfil_id'] = $perfil->id;
                $update['funcao']    = $perfil->label;
            }
        }

        $u->update($update);

        if (isset($dados['email']) && $dados['email'] !== $emailAntigo) {
            $userId    = $u->id;
            $novoEmail = $u->email;

            dispatch(function () use ($userId, $emailAntigo, $novoEmail) {
                NotificacaoService::emailAlterado($userId, $emailAntigo, $novoEmail);
            })->afterResponse();
        }

        return response()->json([
            'id'          => (string) $u->id,
            'utilizador'  => $this->formatar($u->fresh()->load('perfil')),
            'kpis'        => $this->kpis(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $u = User::findOrFail($id);

        if ($request->user()?->id === $u->id) {
            return response()->json([
                'message' => 'Não pode remover a sua própria conta.',
            ], 422);
        }

        $u->delete();

        return response()->json([
            'ok'   => true,
            'kpis' => $this->kpis(),
        ]);
    }

    public function bloquear(Request $request, int $id)
    {
        $u = User::findOrFail($id);

        if ($request->user()?->id === $u->id) {
            return response()->json(['message' => 'Não pode bloquear-se a si próprio.'], 422);
        }

        $u->update(['estado' => 'Bloqueado']);

        return response()->json([
            'id'         => (string) $u->id,
            'utilizador' => $this->formatar($u->fresh()->load('perfil')),
            'kpis'       => $this->kpis(),
        ]);
    }

    public function activar(int $id)
    {
        $u = User::findOrFail($id);
        $u->update(['estado' => 'Ativo']);

        return response()->json([
            'id'         => (string) $u->id,
            'utilizador' => $this->formatar($u->fresh()->load('perfil')),
            'kpis'       => $this->kpis(),
        ]);
    }

    public function alterarSenha(Request $request, int $id)
    {
        $u = User::findOrFail($id);

        $dados = $request->validate([
            'senha'           => 'required|string|min:8',
            'forcarAlteracao' => 'boolean',
        ]);

        $u->update(['password' => Hash::make($dados['senha'])]);

        $userId = $u->id;
        dispatch(function () use ($userId) {
            NotificacaoService::senhaAlterada($userId);
        })->afterResponse();

        return response()->json([
            'ok' => true,
            'id' => (string) $u->id,
        ]);
    }

    public function xlsx(Request $request)
    {
        $items = User::with('perfil')->orderBy('name')->limit(1000)->get();

        $cabecalhos = ['Nome', 'Email', 'Nº ID', 'Perfil', 'Posto', 'Estado', 'Último acesso'];
        $linhas = $items->map(fn ($u) => [
            $u->name,
            $u->email,
            $u->numero_id ?? '—',
            $u->perfil?->label ?? '—',
            $u->posto ?? '—',
            $u->estado,
            $u->ultimo_acesso ? Carbon::parse($u->ultimo_acesso)->format('d/m/Y H:i') : 'Nunca',
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Utilizadores'),
            'admin-utilizadores-' . date('Y-m-d') . '.xlsx',
        );
    }

    private function formatar(User $u): array
    {
        return [
            'id'            => (string) $u->id,
            'nome'          => $u->name,
            'email'         => $u->email,
            'numeroId'      => $u->numero_id,
            'perfil'        => $u->perfil?->key ?? '—',
            'perfilLabel'   => $u->perfil?->label ?? '—',
            'posto'         => $u->posto ?? '—',
            'funcao'        => $u->funcao ?? '—',
            'estado'        => $u->estado ?? 'Ativo',
            'ultimoAcesso'  => $u->ultimo_acesso
                ? Carbon::parse($u->ultimo_acesso)->diffForHumans()
                : 'Nunca',
            'telefone'      => $u->telefone,
        ];
    }

    private function kpis(): array
    {
        $limite = Carbon::now()->subMinutes(15);

        return [
            'total'      => User::count(),
            'activos'    => User::where('estado', 'Ativo')->count(),
            'bloqueados' => User::where('estado', 'Bloqueado')->count(),
            'online'     => User::where('ultimo_acesso', '>=', $limite)->count(),
        ];
    }

    private function perfis(): array
    {
        return DB::table('perfis')
            ->where('estado', 'Ativo')
            ->orderBy('label')
            ->get(['id', 'key', 'label', 'descricao', 'icon', 'cor'])
            ->map(fn ($p) => [
                'id'        => (string) $p->id,
                'key'       => $p->key,
                'label'     => $p->label,
                'descricao' => $p->descricao,
                'icon'      => $p->icon,
                'cor'       => $p->cor,
            ])
            ->toArray();
    }

    private function postos(): array
    {
        return DB::table('postos')
            ->where('estado', 'Ativo')
            ->orderBy('nome')
            ->pluck('nome')
            ->toArray();
    }

    private function gerarNumeroId(string $perfilKey): string
    {
        $prefixo = match ($perfilKey) {
            'admin'   => 'SICAM-ADM',
            'chefe'   => 'PRM-CHF',
            'agente'  => 'PRM-AT',
            'inatro'  => 'INATRO',
            'gestmul' => 'GESTMUL',
            'issm'    => 'ISSM',
            'prm'     => 'PRM',
            'cidadao' => 'CID',
            default   => 'USR',
        };

        $ultimo = User::where('numero_id', 'like', "{$prefixo}-%")
            ->orderByDesc('id')
            ->value('numero_id');

        $seq = 1;
        if ($ultimo && preg_match('/(\d+)$/', $ultimo, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%04d', $prefixo, $seq);
    }
}