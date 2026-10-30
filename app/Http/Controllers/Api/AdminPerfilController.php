<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Perfil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AdminPerfilController extends Controller
{
    /**
     * Módulos disponíveis (o frontend espera estes 13)
     */
    private const MODULOS = [
        'dashboard', 'monitorizacao', 'relatorios', 'backups',
        'utilizadores', 'postos', 'dispositivos', 'integracoes', 'auditoria',
        'hotlist', 'abordagens', 'alertas', 'mapa', 'perfis',
    ];

    /**
     * GET /api/admin/perfis
     */
    public function index()
    {
        $perfis = Perfil::orderBy('label')->get();

        // Contagem de utilizadores por perfil
        $contagem = DB::table('users')
            ->select('perfil_id', DB::raw('COUNT(*) as total'))
            ->groupBy('perfil_id')
            ->pluck('total', 'perfil_id')
            ->toArray();

        $items = $perfis->map(function ($p) use ($contagem) {
            $permissoes = DB::table('perfil_permissoes')
                ->where('perfil_id', $p->id)
                ->get()
                ->keyBy('modulo');

            // Constrói array de permissões apenas para os módulos do frontend
            $permArray = [];
            foreach (self::MODULOS as $m) {
                $perm = $permissoes->get($m);
                $permArray[] = [
                    'modulo' => $m,
                    'ver'    => (bool) ($perm->ver ?? false),
                    'criar'  => (bool) ($perm->criar ?? false),
                    'editar' => (bool) ($perm->editar ?? false),
                    'apagar' => (bool) ($perm->apagar ?? false),
                ];
            }

            return [
                'id'           => (string) $p->id,
                'key'          => $p->key,
                'label'        => $p->label,
                'descricao'    => $p->descricao ?? '',
                'icon'         => $p->icon,
                'cor'          => $p->cor,
                'estado'       => $p->estado,
                'sistema'      => (bool) $p->sistema,
                'utilizadores' => (int) ($contagem[$p->id] ?? 0),
                'permissoes'   => $permArray,
            ];
        });

        return response()->json([
            'items'  => $items,
            'acoes'  => [
                ['key' => 'ver',    'label' => 'Ver'],
                ['key' => 'criar',  'label' => 'Criar'],
                ['key' => 'editar', 'label' => 'Editar'],
                ['key' => 'apagar', 'label' => 'Apagar'],
            ],
        ]);
    }

    /**
     * GET /api/admin/perfis/{id}
     */
    public function show(int $id)
    {
        $p = Perfil::findOrFail($id);

        $contagem = DB::table('users')->where('perfil_id', $p->id)->count();

        $permissoes = DB::table('perfil_permissoes')
            ->where('perfil_id', $p->id)
            ->get()
            ->keyBy('modulo');

        $permArray = [];
        foreach (self::MODULOS as $m) {
            $perm = $permissoes->get($m);
            $permArray[] = [
                'modulo' => $m,
                'ver'    => (bool) ($perm->ver ?? false),
                'criar'  => (bool) ($perm->criar ?? false),
                'editar' => (bool) ($perm->editar ?? false),
                'apagar' => (bool) ($perm->apagar ?? false),
            ];
        }

        return response()->json([
            'id'           => (string) $p->id,
            'key'          => $p->key,
            'label'        => $p->label,
            'descricao'    => $p->descricao ?? '',
            'icon'         => $p->icon,
            'cor'          => $p->cor,
            'estado'       => $p->estado,
            'sistema'      => (bool) $p->sistema,
            'utilizadores' => $contagem,
            'permissoes'   => $permArray,
        ]);
    }

    /**
     * POST /api/admin/perfis
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'key'         => 'required|string|max:60|unique:perfis,key',
            'label'       => 'required|string|max:120',
            'descricao'   => 'nullable|string|max:255',
            'icon'        => 'required|string|max:60',
            'cor'         => 'required|string|max:20',
            'estado'      => 'nullable|string|in:Ativo,Bloqueado',
            'permissoes'  => 'required|array',
            'permissoes.*.modulo' => 'required|string',
            'permissoes.*.ver'    => 'boolean',
            'permissoes.*.criar'  => 'boolean',
            'permissoes.*.editar' => 'boolean',
            'permissoes.*.apagar' => 'boolean',
        ]);

        $p = DB::transaction(function () use ($dados) {
            $perfil = Perfil::create([
                'key'       => strtolower($dados['key']),
                'label'     => $dados['label'],
                'descricao' => $dados['descricao'] ?? null,
                'icon'      => $dados['icon'],
                'cor'       => $dados['cor'],
                'estado'    => $dados['estado'] ?? 'Ativo',
                'sistema'   => false,
            ]);

            // Inserir permissões
            foreach ($dados['permissoes'] as $perm) {
                DB::table('perfil_permissoes')->insert([
                    'perfil_id'  => $perfil->id,
                    'modulo'     => $perm['modulo'],
                    'ver'        => (bool) ($perm['ver'] ?? false),
                    'criar'      => (bool) ($perm['criar'] ?? false),
                    'editar'     => (bool) ($perm['editar'] ?? false),
                    'apagar'     => (bool) ($perm['apagar'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $perfil;
        });

        return response()->json([
            'id'    => (string) $p->id,
            'perfil'=> $this->show((int) $p->id)->getData(true),
        ], 201);
    }

    /**
     * PUT /api/admin/perfis/{id}
     */
    public function update(Request $request, int $id)
    {
        $p = Perfil::findOrFail($id);

        $dados = $request->validate([
            'key'         => 'sometimes|string|max:60|unique:perfis,key,' . $id,
            'label'       => 'sometimes|string|max:120',
            'descricao'   => 'nullable|string|max:255',
            'icon'        => 'sometimes|string|max:60',
            'cor'         => 'sometimes|string|max:20',
            'estado'      => 'sometimes|string|in:Ativo,Bloqueado',
            'permissoes'  => 'sometimes|array',
            'permissoes.*.modulo' => 'required|string',
            'permissoes.*.ver'    => 'boolean',
            'permissoes.*.criar'  => 'boolean',
            'permissoes.*.editar' => 'boolean',
            'permissoes.*.apagar' => 'boolean',
        ]);

        DB::transaction(function () use ($p, $dados) {
            $update = [];
            if (isset($dados['key']))       $update['key']       = strtolower($dados['key']);
            if (isset($dados['label']))     $update['label']     = $dados['label'];
            if (array_key_exists('descricao', $dados)) $update['descricao'] = $dados['descricao'];
            if (isset($dados['icon']))      $update['icon']      = $dados['icon'];
            if (isset($dados['cor']))       $update['cor']       = $dados['cor'];
            if (isset($dados['estado']))    $update['estado']    = $dados['estado'];

            if (!empty($update)) $p->update($update);

            // Atualizar permissões — remove antigas e insere as novas
            if (!empty($dados['permissoes'])) {
                DB::table('perfil_permissoes')->where('perfil_id', $p->id)->delete();

                foreach ($dados['permissoes'] as $perm) {
                    DB::table('perfil_permissoes')->insert([
                        'perfil_id'  => $p->id,
                        'modulo'     => $perm['modulo'],
                        'ver'        => (bool) ($perm['ver'] ?? false),
                        'criar'      => (bool) ($perm['criar'] ?? false),
                        'editar'     => (bool) ($perm['editar'] ?? false),
                        'apagar'     => (bool) ($perm['apagar'] ?? false),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return response()->json([
            'id'    => (string) $p->id,
            'perfil'=> $this->show((int) $p->id)->getData(true),
        ]);
    }

    /**
     * DELETE /api/admin/perfis/{id}
     */
    public function destroy(int $id)
    {
        $p = Perfil::findOrFail($id);

        if ($p->sistema) {
            return response()->json(
                ['message' => 'Não é possível remover um perfil de sistema.'],
                422,
            );
        }

        $utilizadores = DB::table('users')->where('perfil_id', $p->id)->count();
        if ($utilizadores > 0) {
            return response()->json(
                ['message' => "Este perfil tem {$utilizadores} utilizador(es) atribuído(s)."],
                422,
            );
        }

        DB::transaction(function () use ($p) {
            DB::table('perfil_permissoes')->where('perfil_id', $p->id)->delete();
            $p->delete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/admin/perfis/{id}/clonar
     */
    public function clonar(int $id)
    {
        $original = Perfil::findOrFail($id);

        $novo = DB::transaction(function () use ($original) {
            $novo = Perfil::create([
                'key'       => $original->key . '-copia-' . uniqid(),
                'label'     => $original->label . ' (cópia)',
                'descricao' => $original->descricao,
                'icon'      => $original->icon,
                'cor'       => $original->cor,
                'estado'    => 'Ativo',
                'sistema'   => false,
            ]);

            $perms = DB::table('perfil_permissoes')
                ->where('perfil_id', $original->id)
                ->get();

            foreach ($perms as $perm) {
                DB::table('perfil_permissoes')->insert([
                    'perfil_id'  => $novo->id,
                    'modulo'     => $perm->modulo,
                    'ver'        => $perm->ver,
                    'criar'      => $perm->criar,
                    'editar'     => $perm->editar,
                    'apagar'     => $perm->apagar,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $novo;
        });

        return response()->json([
            'id'    => (string) $novo->id,
            'perfil'=> $this->show((int) $novo->id)->getData(true),
        ], 201);
    }

    /**
     * PUT /api/admin/perfis/{id}/bloquear
     */
    public function bloquear(int $id)
    {
        $p = Perfil::findOrFail($id);
        $p->update(['estado' => 'Bloqueado']);

        return response()->json([
            'id'    => (string) $p->id,
            'perfil'=> $this->show((int) $p->id)->getData(true),
        ]);
    }

    /**
     * PUT /api/admin/perfis/{id}/activar
     */
    public function activar(int $id)
    {
        $p = Perfil::findOrFail($id);
        $p->update(['estado' => 'Ativo']);

        return response()->json([
            'id'    => (string) $p->id,
            'perfil'=> $this->show((int) $p->id)->getData(true),
        ]);
    }

    /**
     * GET /api/admin/perfis/xlsx
     */
    public function xlsx()
    {
        $perfis = Perfil::orderBy('label')->get();

        $cabecalhos = ['Perfil', 'Módulo', 'Ver', 'Criar', 'Editar', 'Apagar'];
        $linhas = [];

        foreach ($perfis as $p) {
            $perms = DB::table('perfil_permissoes')
                ->where('perfil_id', $p->id)
                ->get()
                ->keyBy('modulo');

            foreach (self::MODULOS as $m) {
                $perm = $perms->get($m);
                $linhas[] = [
                    $p->label,
                    $m,
                    ($perm->ver ?? false) ? '✓' : '—',
                    ($perm->criar ?? false) ? '✓' : '—',
                    ($perm->editar ?? false) ? '✓' : '—',
                    ($perm->apagar ?? false) ? '✓' : '—',
                ];
            }
        }

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Perfis'),
            'admin-perfis-' . date('Y-m-d') . '.xlsx',
        );
    }
}