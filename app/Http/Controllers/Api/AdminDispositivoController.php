<?php

namespace App\Http\Controllers\Api;

use App\Exports\DadosExport;
use App\Http\Controllers\Controller;
use App\Models\Dispositivo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AdminDispositivoController extends Controller
{
    private const VERSAO_ATUAL = '2.5.0';

    /**
     * GET /api/admin/dispositivos
     */
    public function index(Request $request)
    {
        $query = Dispositivo::query();

        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('serie', 'like', "%{$q}%")
                    ->orWhere('agente', 'like', "%{$q}%")
                    ->orWhere('modelo', 'like', "%{$q}%");
            });
        }

        if ($request->filled('estado'))   $query->where('estado', $request->estado);
        if ($request->filled('versao'))   $query->where('versao_app', $request->versao);

        $items = $query
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get()
            ->map(fn ($d) => $this->formatar($d));

        return response()->json([
            'items'  => $items,
            'kpis'   => $this->kpis(),
            'versao' => self::VERSAO_ATUAL,
        ]);
    }

    /**
     * GET /api/admin/dispositivos/{id}
     */
    public function show(int $id)
    {
        $d = Dispositivo::findOrFail($id);
        return response()->json($this->formatar($d));
    }

    /**
     * POST /api/admin/dispositivos
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'serie'   => 'required|string|max:40|unique:dispositivos,serie',
            'modelo'  => 'required|string|max:120',
            'agente'  => 'nullable|string|max:160',
            'notas'   => 'nullable|string',
        ]);

        $temAgente = !empty($dados['agente']);

        $d = Dispositivo::create([
            'serie'                => strtoupper($dados['serie']),
            'modelo'               => $dados['modelo'],
            'agente'               => $temAgente ? $dados['agente'] : null,
            'estado'               => $temAgente ? 'Ativo' : 'Por registar',
            'versao_app'           => self::VERSAO_ATUAL,
            'ultima_sincronizacao' => $temAgente ? now() : null,
            'notas'                => $dados['notas'] ?? null,
            'criado_por'           => $request->user()?->id,
        ]);

        return response()->json([
            'id'           => (string) $d->id,
            'dispositivo'  => $this->formatar($d),
            'kpis'         => $this->kpis(),
        ], 201);
    }

    /**
     * PUT /api/admin/dispositivos/{id}
     */
    public function update(Request $request, int $id)
    {
        $d = Dispositivo::findOrFail($id);

        $dados = $request->validate([
            'serie'   => 'sometimes|string|max:40|unique:dispositivos,serie,' . $id,
            'modelo'  => 'sometimes|string|max:120',
            'agente'  => 'nullable|string|max:160',
            'notas'   => 'nullable|string',
        ]);

        $update = [];
        if (isset($dados['serie']))  $update['serie']  = strtoupper($dados['serie']);
        if (isset($dados['modelo'])) $update['modelo'] = $dados['modelo'];
        if (array_key_exists('agente', $dados)) {
            $update['agente'] = $dados['agente'] ?: null;
            $update['estado'] = $dados['agente'] ? 'Ativo' : 'Por registar';
        }
        if (array_key_exists('notas', $dados)) $update['notas'] = $dados['notas'];

        $d->update($update);

        return response()->json([
            'id'          => (string) $d->id,
            'dispositivo' => $this->formatar($d->fresh()),
            'kpis'        => $this->kpis(),
        ]);
    }

    /**
     * DELETE /api/admin/dispositivos/{id}
     */
    public function destroy(int $id)
    {
        $d = Dispositivo::findOrFail($id);
        $d->delete();

        return response()->json([
            'ok'   => true,
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/admin/dispositivos/{id}/actualizar-versao
     */
    public function marcarAtualizado(int $id)
    {
        $d = Dispositivo::findOrFail($id);
        $d->update([
            'versao_app'           => self::VERSAO_ATUAL,
            'ultima_sincronizacao' => now(),
        ]);

        return response()->json([
            'id'          => (string) $d->id,
            'dispositivo' => $this->formatar($d->fresh()),
            'kpis'        => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/admin/dispositivos/{id}/desassociar
     */
    public function desassociar(int $id)
    {
        $d = Dispositivo::findOrFail($id);
        $d->update([
            'agente' => null,
            'estado' => 'Por registar',
        ]);

        return response()->json([
            'id'          => (string) $d->id,
            'dispositivo' => $this->formatar($d->fresh()),
            'kpis'        => $this->kpis(),
        ]);
    }

    /**
     * GET /api/admin/dispositivos/xlsx
     */
    public function xlsx()
    {
        $items = Dispositivo::orderBy('serie')->limit(1000)->get();

        $cabecalhos = ['Nº Série', 'Modelo', 'Agente', 'Estado', 'Versão', 'Última sincronização'];
        $linhas = $items->map(fn ($d) => [
            $d->serie,
            $d->modelo,
            $d->agente ?? '—',
            $d->estado,
            $d->versao_app,
            $d->ultima_sincronizacao
                ? Carbon::parse($d->ultima_sincronizacao)->format('d/m/Y H:i')
                : 'Nunca',
        ])->toArray();

        return Excel::download(
            new DadosExport($linhas, $cabecalhos, 'Dispositivos'),
            'admin-dispositivos-' . date('Y-m-d') . '.xlsx',
        );
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Dispositivo $d): array
    {
        return [
            'id'                    => (string) $d->id,
            'serie'                 => $d->serie,
            'modelo'                => $d->modelo,
            'agente'                => $d->agente ?? '—',
            'estado'                => $d->estado,
            'versaoApp'             => $d->versao_app,
            'ultimaSincronizacao'   => $d->ultima_sincronizacao
                ? Carbon::parse($d->ultima_sincronizacao)->format('d/m H:i')
                : 'Nunca',
            'ultimaSincronizacaoTs' => $d->ultima_sincronizacao?->timestamp * 1000,
            'notas'                 => $d->notas,
        ];
    }

    private function kpis(): array
    {
        return [
            'total'            => Dispositivo::count(),
            'activos'          => Dispositivo::where('estado', 'Ativo')->count(),
            'porRegistar'      => Dispositivo::where('estado', 'Por registar')->count(),
            'desatualizados'   => Dispositivo::where('versao_app', '!=', self::VERSAO_ATUAL)->count(),
        ];
    }
}