<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Multa;
use App\Models\Patrulha;
use App\Models\User;
use App\Services\SicamIAService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DadosExport;

class ChefeAgenteController extends Controller
{
    private const PERFIL_AGENTE = 30003;

    protected SicamIAService $ia;

    public function __construct(SicamIAService $ia)
    {
        $this->ia = $ia;
    }

    /**
     * GET /api/chefe/agentes
     */
    public function index(Request $request)
    {
        $query = User::query()->where('perfil_id', self::PERFIL_AGENTE);

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

        $agentes = $query->orderByDesc('created_at')->limit(500)->get();
        $ids = $agentes->pluck('id')->toArray();

        $patrulhasPorAgente = [];
        $multasPorAgente = [];
        $consultasPorAgente = [];
        $abordagensPorAgente = [];

        if (!empty($ids)) {
            $patrulhasPorAgente = Patrulha::whereIn('agente_id', $ids)
                ->where('estado', 'Em curso')
                ->orderByDesc('inicio')
                ->get()
                ->keyBy('agente_id');

            $multasPorAgente = Multa::select('agente_id', DB::raw('COUNT(*) as total'))
                ->whereIn('agente_id', $ids)
                ->groupBy('agente_id')
                ->pluck('total', 'agente_id')
                ->toArray();

            $consultasPorAgente = DB::table('consultas_agente')
                ->select('agente_id', DB::raw('COUNT(*) as total'))
                ->whereIn('agente_id', $ids)
                ->whereDate('consultado_em', now()->toDateString())
                ->groupBy('agente_id')
                ->pluck('total', 'agente_id')
                ->toArray();

            $abordagensPorAgente = DB::table('abordagens')
                ->select('agente_id', DB::raw('COUNT(*) as total'))
                ->whereIn('agente_id', $ids)
                ->whereDate('created_at', now()->toDateString())
                ->groupBy('agente_id')
                ->pluck('total', 'agente_id')
                ->toArray();
        }

        $items = $agentes->map(fn ($u) => $this->formatar(
            $u,
            $patrulhasPorAgente[$u->id] ?? null,
            (int) ($multasPorAgente[$u->id] ?? 0),
            (int) ($consultasPorAgente[$u->id] ?? 0),
            (int) ($abordagensPorAgente[$u->id] ?? 0)
        ));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis($items),
        ]);
    }

    /**
     * GET /api/chefe/agentes/xlsx
     */
    public function xlsx(Request $request)
    {
        $query = User::query()->where('perfil_id', self::PERFIL_AGENTE);

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

        $agentes = $query->orderByDesc('created_at')->limit(500)->get();
        $ids = $agentes->pluck('id')->toArray();

        // Para o Excel, puxamos os dados básicos (sem agrupar para não sobrecarregar)
        $patrulhasPorAgente = Patrulha::whereIn('agente_id', $ids)
            ->where('estado', 'Em curso')
            ->get()
            ->keyBy('agente_id');

        $multasPorAgente = Multa::select('agente_id', DB::raw('COUNT(*) as total'))
            ->whereIn('agente_id', $ids)
            ->groupBy('agente_id')
            ->pluck('total', 'agente_id')
            ->toArray();

        $consultasPorAgente = DB::table('consultas_agente')
            ->select('agente_id', DB::raw('COUNT(*) as total'))
            ->whereIn('agente_id', $ids)
            ->whereDate('consultado_em', now()->toDateString())
            ->groupBy('agente_id')
            ->pluck('total', 'agente_id')
            ->toArray();

        $abordagensPorAgente = DB::table('abordagens')
            ->select('agente_id', DB::raw('COUNT(*) as total'))
            ->whereIn('agente_id', $ids)
            ->whereDate('created_at', now()->toDateString())
            ->groupBy('agente_id')
            ->pluck('total', 'agente_id')
            ->toArray();

        $cabecalhos = [
            'Nome', 'E-mail', 'Telefone', 'Nº Série', 'Turno', 'Viatura', 
            'Posto', 'Estado', 'Última Sinc.', 'Consultas Hoje', 
            'Multas Emitidas', 'Abordagens Hoje', 'Admissão'
        ];

        $linhas = [];

        foreach ($agentes as $u) {
            $patrulha = $patrulhasPorAgente[$u->id] ?? null;
            $estado = 'Offline';

            if ($patrulha) {
                $estado = 'Em patrulha';
            } elseif ($u->estado === 'Bloqueado') {
                $estado = 'Bloqueado';
            } elseif ($u->estado === 'Inactivo') {
                $estado = 'Inactivo';
            } elseif ($u->estado === 'Ativo' && $u->ultimo_acesso) {
                $ultimo = Carbon::parse($u->ultimo_acesso);
                if ($ultimo->diffInMinutes(now()) <= 5) {
                    $estado = 'Online';
                }
            }

            $linhas[] = [
                $u->name ?? '—',
                $u->email ?? '—',
                $u->telefone ?? '—',
                $u->numero_id ?? '—',
                $u->turno ?? $patrulha?->turno ?? '—',
                $u->viatura ?? $patrulha?->viatura ?? '—',
                $u->posto ?? '—',
                $estado,
                $u->ultimo_acesso ? Carbon::parse($u->ultimo_acesso)->format('d/m H:i') : '—',
                (int) ($consultasPorAgente[$u->id] ?? 0),
                (int) ($multasPorAgente[$u->id] ?? 0),
                (int) ($abordagensPorAgente[$u->id] ?? 0),
                $u->created_at?->format('d/m/Y') ?? '—',
            ];
        }

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Agentes'),
            sprintf('agentes-%s.xlsx', date('Y-m-d')),
        );
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
        
        $consultasHoje = DB::table('consultas_agente')
            ->where('agente_id', $u->id)
            ->whereDate('consultado_em', now()->toDateString())
            ->count();

        $abordagensHoje = DB::table('abordagens')
            ->where('agente_id', $u->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return response()->json($this->formatar($u, $patrulha, $multasEmitidas, $consultasHoje, $abordagensHoje));
    }

    /**
     * GET /api/chefe/agentes/{id}/resumo-ia
     */
    public function resumoIA(Request $request, int $id)
    {
        $u = User::where('perfil_id', self::PERFIL_AGENTE)->findOrFail($id);

        $consultasHoje = DB::table('consultas_agente')
            ->where('agente_id', $u->id)
            ->whereDate('consultado_em', now()->toDateString())
            ->count();

        $multasEmitidas = Multa::where('agente_id', $u->id)->count();
        $emPatrulha = Patrulha::where('agente_id', $u->id)->where('estado', 'Em curso')->exists();

        $resposta = $this->ia->resumirAgente([
            'agente_nome'     => $u->name,
            'posto'           => $u->posto ?? '—',
            'consultas_hoje'  => $consultasHoje,
            'multas_emitidas' => $multasEmitidas,
            'em_patrulha'     => $emPatrulha,
        ]);

        return response()->json($resposta);
    }

    /**
     * GET /api/chefe/catalogos
     */
    public function catalogos()
    {
        return response()->json([
            'postos'   => \App\Models\Posto::orderBy('nome')->pluck('nome')->toArray(),
            'viaturas' => Patrulha::select('viatura')
                            ->whereNotNull('viatura')
                            ->distinct()
                            ->orderBy('viatura')
                            ->pluck('viatura')
                            ->toArray(),
            'estados'  => ['Ativo', 'Inactivo', 'Bloqueado'],
            'turnos'   => ['Manhã', 'Tarde', 'Noite'],
        ]);
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
            'turno'    => 'nullable|string|in:Manhã,Tarde,Noite',
            'viatura'  => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
        ]);

        $user = User::create([
            'name'      => $dados['nome'],
            'email'     => $dados['email'],
            'telefone'  => $dados['telefone'] ?? null,
            'numero_id' => $dados['nSerie'],
            'perfil_id' => self::PERFIL_AGENTE,
            'posto'     => $dados['posto'],
            'turno'     => $dados['turno'] ?? null,
            'viatura'   => $dados['viatura'] ?? null,
            'estado'    => 'Ativo',
            'password'  => Hash::make($dados['password'] ?? 'sicam123'),
        ]);

        return response()->json([
            'id'     => (string) $user->id,
            'agente' => $this->formatar($user, null, 0, 0, 0),
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
            'turno'    => 'nullable|string|in:Manhã,Tarde,Noite',
            'viatura'  => 'nullable|string|max:20',
        ]);

        $u->update([
            'name'      => $dados['nome'] ?? $u->name,
            'email'     => $dados['email'] ?? $u->email,
            'telefone'  => $dados['telefone'] ?? $u->telefone,
            'numero_id' => $dados['nSerie'] ?? $u->numero_id,
            'posto'     => $dados['posto'] ?? $u->posto,
            'turno'     => $dados['turno'] ?? $u->turno,
            'viatura'   => $dados['viatura'] ?? $u->viatura,
        ]);

        $patrulha = Patrulha::where('agente_id', $u->id)->where('estado', 'Em curso')->first();
        $multas = Multa::where('agente_id', $u->id)->count();
        $consultas = DB::table('consultas_agente')->where('agente_id', $u->id)->whereDate('consultado_em', now()->toDateString())->count();
        $abordagens = DB::table('abordagens')->where('agente_id', $u->id)->whereDate('created_at', now()->toDateString())->count();

        return response()->json(['id' => (string) $u->id, 'agente' => $this->formatar($u, $patrulha, $multas, $consultas, $abordagens)]);
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
        $patrulha = Patrulha::where('agente_id', $u->id)->where('estado', 'Em curso')->first();
        $multas = Multa::where('agente_id', $u->id)->count();
        $consultas = DB::table('consultas_agente')->where('agente_id', $u->id)->whereDate('consultado_em', now()->toDateString())->count();
        $abordagens = DB::table('abordagens')->where('agente_id', $u->id)->whereDate('created_at', now()->toDateString())->count();
        return response()->json(['id' => (string) $u->id, 'agente' => $this->formatar($u, $patrulha, $multas, $consultas, $abordagens)]);
    }

    /**
     * PUT /api/chefe/agentes/{id}/desactivar
     */
    public function desactivar(int $id)
    {
        $u = User::where('perfil_id', self::PERFIL_AGENTE)->findOrFail($id);
        $u->update(['estado' => 'Inactivo']);
        $patrulha = Patrulha::where('agente_id', $u->id)->where('estado', 'Em curso')->first();
        $multas = Multa::where('agente_id', $u->id)->count();
        $consultas = DB::table('consultas_agente')->where('agente_id', $u->id)->whereDate('consultado_em', now()->toDateString())->count();
        $abordagens = DB::table('abordagens')->where('agente_id', $u->id)->whereDate('created_at', now()->toDateString())->count();
        return response()->json(['id' => (string) $u->id, 'agente' => $this->formatar($u, $patrulha, $multas, $consultas, $abordagens)]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(User $u, ?Patrulha $patrulha = null, int $multasEmitidas = 0, int $consultasHoje = 0, int $abordagensHoje = 0): array
    {
        $estado = 'Offline';

        if ($patrulha) {
            $estado = 'Em patrulha';
        } elseif ($u->estado === 'Bloqueado') {
            $estado = 'Bloqueado';
        } elseif ($u->estado === 'Inactivo') {
            $estado = 'Inactivo';
        } elseif ($u->estado === 'Ativo' && $u->ultimo_acesso) {
            $ultimo = Carbon::parse($u->ultimo_acesso);
            if ($ultimo->diffInMinutes(now()) <= 5) {
                $estado = 'Online';
            }
        }

        return [
            'id'             => (string) $u->id,
            'nome'           => $u->name ?? '—',
            'email'          => $u->email ?? '—',
            'telefone'       => $u->telefone ?? '—',
            'nSerie'         => $u->numero_id ?? '—',
            'turno'          => $u->turno ?? $patrulha?->turno ?? '—',
            'viatura'        => $u->viatura ?? $patrulha?->viatura ?? '—',
            'posto'          => $u->posto ?? '—',
            'estado'         => $estado,
            'ultimaSinc'     => $u->ultimo_acesso ? Carbon::parse($u->ultimo_acesso)->format('d/m H:i') : '—',
            'consultasHoje'  => $consultasHoje,
            'multasEmitidas' => $multasEmitidas,
            'abordagensHoje' => $abordagensHoje,
            'admissao'       => $u->created_at?->format('d/m/Y') ?? '—',
            'observacoes'    => null,
            'criadoEm'       => $u->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function kpis($items): array
    {
        return [
            'total'          => $items->count(),
            'online'         => $items->where('estado', 'Online')->count(),
            'emPatrulha'     => $items->where('estado', 'Em patrulha')->count(),
            'offline'        => $items->where('estado', 'Offline')->count(),
            'inactivos'      => $items->where('estado', 'Inactivo')->count(),
            'bloqueados'     => $items->where('estado', 'Bloqueado')->count(),
            'consultasHoje'  => $items->sum('consultasHoje'),
            'multasHoje'     => $items->sum('multasEmitidas'),
            'abordagensHoje' => $items->sum('abordagensHoje'),
        ];
    }
}