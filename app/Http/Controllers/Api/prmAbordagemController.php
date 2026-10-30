<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Abordagem;
use App\Models\Hotlist;
use App\Models\Posto;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\NotificacaoService;
use Illuminate\Http\Request;

class AbordagemController extends Controller
{
    private const COORDS = [
        'Posto Baixa'          => ['lat' => -25.9683, 'lng' => 32.5758],
        'Posto KaMpfumo'       => ['lat' => -25.9722, 'lng' => 32.5733],
        'Posto Matola'         => ['lat' => -25.9622, 'lng' => 32.4589],
        'Posto Machava'        => ['lat' => -25.9218, 'lng' => 32.4812],
        'Posto Namaacha (EN4)' => ['lat' => -26.0164, 'lng' => 32.0297],
    ];

    // ═══════════════════════════════════════════════════════════
    // GET /api/abordagens
    // ═══════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $abordagens = Abordagem::with([
                'veiculo:id,matricula',
                'agente:id,name',
                'posto:id,nome',
            ])
            ->orderByDesc('data')
            ->get();

        $veiculosHotlist = Hotlist::where('estado', 'Activo')
            ->pluck('veiculo_id')
            ->flip()
            ->toArray();

        $items = $abordagens->map(fn ($a) => $this->formatar($a, $veiculosHotlist));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis($veiculosHotlist),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /api/abordagens
    // ═══════════════════════════════════════════════════════════
    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula'   => 'required|string|max:20',
            'agente'      => 'required|string',
            'posto'       => 'required|string',
            'local'       => 'required|string',
            'resultado'   => 'required|in:Sem incidentes,Com detenção,Em curso',
            'observacoes' => 'nullable|string',
        ]);

        $veiculo = Veiculo::firstOrCreate(
            ['matricula' => strtoupper($dados['matricula'])],
            ['marca' => '—', 'modelo' => '—', 'score' => 0, 'estado' => 'sem-dados']
        );

        $agente = User::where('name', 'like', '%' . str_replace('Agente ', '', $dados['agente']) . '%')->first();
        $posto  = Posto::where('nome', $dados['posto'])->first();

        $maxId  = Abordagem::max('id') ?? 0;
        $codigo = 'AB-' . str_pad($maxId + 1, 3, '0', STR_PAD_LEFT);

        $abordagem = Abordagem::create([
            'codigo'      => $codigo,
            'veiculo_id'  => $veiculo->id,
            'agente_id'   => $agente?->id,
            'posto_id'    => $posto?->id,
            'local'       => $dados['local'],
            'resultado'   => $dados['resultado'],
            'data'        => now(),
            'observacoes' => $dados['observacoes'] ?? null,
        ]);

        // ═══════════════════════════════════════════════════════
        // 🔔 GATILHO — via NotificacaoService (centralizado)
        // ═══════════════════════════════════════════════════════
        $this->dispararNotificacoes($abordagem, $veiculo, $posto, $dados);

        return response()->json([
            'id'   => (string) $abordagem->id,
            'kpis' => $this->kpis([]),
        ], 201);
    }

    // ═══════════════════════════════════════════════════════════
    // DELETE /api/abordagens/{id}
    // ═══════════════════════════════════════════════════════════
    public function destroy(int $id)
    {
        Abordagem::findOrFail($id)->delete();
        return response()->noContent();
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers internos
    // ═══════════════════════════════════════════════════════════
    private function kpis(array $veiculosHotlist): array
    {
        $row = Abordagem::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN resultado = 'Sem incidentes' THEN 1 ELSE 0 END) as sem_incidentes,
            SUM(CASE WHEN resultado = 'Com detenção' THEN 1 ELSE 0 END) as com_detencao,
            SUM(CASE WHEN resultado = 'Em curso' THEN 1 ELSE 0 END) as em_curso
        ")->first();

        $hotlist = 0;
        if (!empty($veiculosHotlist)) {
            $hotlist = Abordagem::whereIn('veiculo_id', array_keys($veiculosHotlist))->count();
        }

        return [
            'total'         => (int) $row->total,
            'semIncidentes' => (int) $row->sem_incidentes,
            'comDetencao'   => (int) $row->com_detencao,
            'emCurso'       => (int) $row->em_curso,
            'hotlist'       => (int) $hotlist,
        ];
    }

    private function formatar(Abordagem $a, array $veiculosHotlist): array
    {
        $veiculo = $a->veiculo;
        $emHotlist = $veiculo ? isset($veiculosHotlist[$veiculo->id]) : false;

        $postoNome = $a->posto?->nome ?? 'Posto Baixa';
        $coord = self::COORDS[$postoNome] ?? ['lat' => -25.9653, 'lng' => 32.5892];

        return [
            'id'          => (string) $a->id,
            'codigo'      => $a->codigo,
            'dataHora'    => $a->data?->format('d/m H:i') ?? '—',
            'matricula'   => $veiculo?->matricula ?? '—',
            'agente'      => $a->agente?->name ?? '—',
            'posto'       => $postoNome,
            'local'       => $a->local ?? '—',
            'resultado'   => $a->resultado,
            'emHotlist'   => $emHotlist,
            'observacoes' => $a->observacoes ?? null,
            'diasAtras'   => $a->data ? now()->diffInDays($a->data) : 0,
            'lat'         => $coord['lat'],
            'lng'         => $coord['lng'],
        ];
    }

    private function dispararNotificacoes(
        Abordagem $abordagem,
        Veiculo $veiculo,
        ?Posto $posto,
        array $dados
    ): void {
        // ═══ CASO 1 — Com detenção ═══
        if ($dados['resultado'] === 'Com detenção') {
            NotificacaoService::abordagemComDetencao(
                $abordagem->codigo,
                $veiculo->matricula,
                $posto?->nome ?? 'posto',
                $dados['local']
            );
        }

        // ═══ CASO 2 — Veículo da hotlist ═══
        $emHotlist = Hotlist::where('veiculo_id', $veiculo->id)
            ->where('estado', 'Activo')
            ->exists();

        if ($emHotlist) {
            NotificacaoService::veiculoHotlistAbordado(
                $veiculo->matricula,
                $dados['local'],
                $dados['resultado']
            );
        }
    }
}