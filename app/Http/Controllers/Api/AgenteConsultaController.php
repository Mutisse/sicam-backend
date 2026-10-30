<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AgenteConsultaController extends Controller
{
    /**
     * GET /api/agente/consulta/{matricula}
     * Consulta um veículo pela matrícula
     */
    public function consultar(Request $request, string $matricula)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $matricula = strtoupper(trim($matricula));
        $veiculo = Veiculo::where('matricula', $matricula)->first();

        if (!$veiculo) {
            return response()->json([
                'matricula' => $matricula,
                'encontrado' => false,
                'score' => null,
                'tipo' => null,
                'posto' => null,
                'entidades' => [],
            ]);
        }

        $score = (int) ($veiculo->score ?? 0);

        // Determina o "tipo" a partir da marca/modelo
        $tipo = $this->tipoVeiculo($veiculo);

        // Posto — da hotlist ou do veículo
        $posto = $this->postoDoVeiculo($veiculo, $user);

        // Entidades — cada uma é verificada em tabela real
        $entidades = $this->verificarEntidades($veiculo);

        // Regista no histórico do agente (Cache)
        $this->registarNoHistorico($user, $matricula, $posto, $score);

        return response()->json([
            'matricula'  => $matricula,
            'encontrado' => true,
            'score'      => $score,
            'tipo'       => $tipo,
            'posto'      => $posto,
            'entidades'  => $entidades,
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================
    private function tipoVeiculo(Veiculo $v): string
    {
        // 1. Coluna `tipo` do próprio veículo
        if (!empty($v->tipo)) return $v->tipo;

        // 2. Procura no catálogo
        if (!empty($v->marca) && !empty($v->modelo)) {
            $cat = \App\Models\VeiculoCatalogo::query()
                ->whereRaw('LOWER(marca) = ?', [mb_strtolower($v->marca)])
                ->whereRaw('LOWER(modelo) = ?', [mb_strtolower($v->modelo)])
                ->first();

            if ($cat) return $cat->tipo_veiculo;
        }

        // 3. Fallback razoável
        return 'Ligeiro';
    }

    private function postoDoVeiculo(Veiculo $v, $user): string
    {
        // Se o veículo tem posto_id → vai buscar o nome
        if ($v->posto_id) {
            $posto = DB::table('postos')->find($v->posto_id);
            if ($posto) return $posto->nome;
        }

        // Senão, usa a hotlist
        $hot = DB::table('hotlist')
            ->where('veiculo_id', $v->id)
            ->where('estado', 'Activo')
            ->first();
        if ($hot) return $hot->posto ?? $hot->zona ?? '—';

        // Senão, usa o posto do user
        return $user->posto ?? '—';
    }

    private function verificarEntidades(Veiculo $v): array
    {
        // 1. INATRO — inspecção
        $temInspecaoValida = false;
        if (DB::getSchemaBuilder()->hasTable('inspecoes')) {
            $ultima = DB::table('inspecoes')
                ->where('veiculo_id', $v->id)
                ->orderByDesc('data')
                ->first();
            $temInspecaoValida = $ultima && Carbon::parse($ultima->data)->isFuture();
        }

        // 2. Gestão de Multas — pendentes
        $temMultasPendentes = DB::table('multas')
            ->where('veiculo_id', $v->id)
            ->where('estado', 'Pendente')
            ->exists();

        // 3. ISSM — apólice válida
        $temSeguro = false;
        if (DB::getSchemaBuilder()->hasTable('apolices')) {
            $apolice = DB::table('apolices')
                ->where('veiculo_id', $v->id)
                ->orderByDesc('validade')
                ->first();
            $temSeguro = $apolice && Carbon::parse($apolice->validade)->isFuture();
        }

        // 4. PRM — hotlist
        $naHotlist = DB::table('hotlist')
            ->where('veiculo_id', $v->id)
            ->where('estado', 'Activo')
            ->exists();

        return [
            [
                'nome'      => 'INATRO',
                'descricao' => 'Inspeção técnica',
                'ok'        => $temInspecaoValida,
                'okTexto'   => 'Válida',
                'erroTexto' => 'Expirada',
            ],
            [
                'nome'      => 'Gestão de Multas',
                'descricao' => 'Multas',
                'ok'        => !$temMultasPendentes,
                'okTexto'   => 'Sem pendentes',
                'erroTexto' => 'Por pagar',
            ],
            [
                'nome'      => 'ISSM / Seguradora',
                'descricao' => 'Seguro obrigatório',
                'ok'        => $temSeguro,
                'okTexto'   => 'Válido',
                'erroTexto' => 'Expirado',
            ],
            [
                'nome'      => 'PRM',
                'descricao' => 'Hotlist',
                'ok'        => !$naHotlist,
                'okTexto'   => 'Não consta',
                'erroTexto' => 'Consta',
            ],
        ];
    }

    private function registarNoHistorico(
        $user,
        string $matricula,
        string $posto,
        int $score,
    ): void {
        $chave = "agente:{$user->id}:ultimas_consultas";
        $historico = Cache::get($chave, []);

        // Remove duplicado se já existir
        $historico = array_values(array_filter(
            $historico,
            fn($h) => ($h['matricula'] ?? '') !== $matricula,
        ));

        array_unshift($historico, [
            'matricula' => $matricula,
            'posto'     => $posto,
            'ultima'    => now()->format('d/m H:i'),
            'timestamp' => now()->timestamp,
            'score'     => $score,
        ]);

        $historico = array_slice($historico, 0, 10);
        Cache::put($chave, $historico, now()->addDays(7));

        // Incrementa contador diário
        $hoje = now()->toDateString();
        $chaveCont = "agente:{$user->id}:consultas:{$hoje}";
        $contador = (int) Cache::get($chaveCont, 0) + 1;
        Cache::put($chaveCont, $contador, now()->endOfDay());
    }

    /**
     * GET /api/agente/consulta/{matricula}/detalhe
     * Consulta completa — todos os dados do veículo agregados
     */
    public function detalheCompleto(Request $request, string $matricula)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $matricula = strtoupper(trim($matricula));
        $veiculo = Veiculo::where('matricula', $matricula)->first();

        if (!$veiculo) {
            return response()->json([
                'encontrado' => false,
                'matricula'  => $matricula,
            ], 404);
        }

        // Score + tipo (usa catálogo)
        $score = (int) ($veiculo->score ?? 0);
        $tipo  = $this->tipoVeiculo($veiculo);

        // Proprietário — do veículo + apólice mais recente
        $apoliceRecente = DB::table('apolices')
            ->where('veiculo_id', $veiculo->id)
            ->orderByDesc('validade')
            ->first();

        $proprietario = [
            'nome'      => $veiculo->proprietario_nome ?? ($apoliceRecente->segurado ?? '—'),
            'numeroId'  => $veiculo->proprietario_numero_id ?? ($apoliceRecente->nif ?? '—'),
            'contacto'  => $apoliceRecente->telefone ?? '—',
            'email'     => $apoliceRecente->email ?? '—',
        ];

        // Inspecções
        $inspecoes = DB::table('inspecoes')
            ->where('veiculo_id', $veiculo->id)
            ->orderByDesc('data')
            ->get()
            ->map(function ($i) {
                $validade = $i->validade ? Carbon::parse($i->validade) : null;
                return [
                    'codigo'        => $i->codigo,
                    'data'          => $i->data ? Carbon::parse($i->data)->format('d/m/Y') : '—',
                    'validade'      => $validade ? $validade->format('d/m/Y') : '—',
                    'proxima'       => $i->proxima_inspecao ? Carbon::parse($i->proxima_inspecao)->format('d/m/Y') : '—',
                    'resultado'     => $i->resultado ?? '—',
                    'estado'        => $i->estado ?? '—',
                    'inspetor'      => $i->inspetor ?? '—',
                    'observacoes'   => $i->observacoes,
                    'valida'        => $validade ? $validade->isFuture() : false,
                ];
            });

        // Apólices
        $apolices = DB::table('apolices')
            ->where('veiculo_id', $veiculo->id)
            ->orderByDesc('validade')
            ->get()
            ->map(function ($a) {
                $validade = $a->validade ? Carbon::parse($a->validade) : null;
                return [
                    'numero'      => $a->numero,
                    'seguradora'  => $a->seguradora ?? '—',
                    'tipo'        => $a->tipo ?? '—',
                    'inicio'      => $a->inicio ? Carbon::parse($a->inicio)->format('d/m/Y') : '—',
                    'validade'    => $validade ? $validade->format('d/m/Y') : '—',
                    'premio'      => (float) ($a->premio ?? 0),
                    'estado'      => $a->estado ?? '—',
                    'valida'      => $validade ? $validade->isFuture() : false,
                    'observacoes' => $a->observacoes,
                ];
            });

        // Multas
        $multasTodas = DB::table('multas')
            ->where('veiculo_id', $veiculo->id)
            ->orderByDesc('data')
            ->get();

        $multasPendentes = $multasTodas->where('estado', 'Pendente');
        $multasPagas     = $multasTodas->where('estado', 'Paga');

        $multas = [
            'pendentes'      => $multasPendentes->count(),
            'pagas'          => $multasPagas->count(),
            'total'          => $multasTodas->count(),
            'valorTotal'     => (float) $multasTodas->sum('valor'),
            'valorPendente'  => (float) $multasPendentes->sum('valor'),
            'lista'          => $multasTodas->map(function ($m) {
                return [
                    'numero'         => $m->numero,
                    'infracao'       => $m->infracao ?? '—',
                    'valor'          => (float) ($m->valor ?? 0),
                    'data'           => $m->data ? Carbon::parse($m->data)->format('d/m/Y') : '—',
                    'dataPagamento'  => $m->data_pagamento ? Carbon::parse($m->data_pagamento)->format('d/m/Y') : null,
                    'estado'         => $m->estado,
                    'provincia'      => $m->provincia,
                ];
            })->values(),
        ];

        // Hotlist
        $hot = DB::table('hotlist')
            ->where('veiculo_id', $veiculo->id)
            ->orderByDesc('created_at')
            ->first();

        $hotlist = $hot ? [
            'activo'       => $hot->estado === 'Activo',
            'motivo'       => $hot->motivo ?? '—',
            'perigo'       => $hot->perigo ?? '—',
            'prioridade'   => $hot->prioridade ?? '—',
            'posto'        => $hot->posto ?? '—',
            'zona'         => $hot->zona ?? '—',
            'descricao'    => $hot->descricao ?? '—',
            'detectadoEm'  => $hot->detectado_em ? Carbon::parse($hot->detectado_em)->format('d/m/Y') : '—',
            'estado'       => $hot->estado,
        ] : null;

        // Resumo — 4 entidades
        $inspecaoValida = $inspecoes->firstWhere('valida', true) !== null;
        $apoliceValida  = $apolices->firstWhere('valida', true) !== null;
        $temMultas      = $multas['pendentes'] > 0;
        $naHotlist      = $hotlist && $hotlist['activo'];

        $entidades = [
            [
                'nome'    => 'INATRO',
                'ok'      => $inspecaoValida,
                'texto'   => $inspecaoValida ? 'Válida' : 'Expirada',
                'detalhe' => $inspecoes->isNotEmpty()
                    ? 'Última inspecção: ' . $inspecoes->first()['data']
                    : 'Sem registos',
            ],
            [
                'nome'    => 'GESTMUL',
                'ok'      => !$temMultas,
                'texto'   => $temMultas ? "{$multas['pendentes']} pendentes" : 'Sem pendentes',
                'detalhe' => $temMultas
                    ? number_format($multas['valorPendente'], 0, ',', '.') . ' MT em dívida'
                    : 'Nada a pagar',
            ],
            [
                'nome'    => 'ISSM',
                'ok'      => $apoliceValida,
                'texto'   => $apoliceValida ? 'Válida' : 'Expirada',
                'detalhe' => $apolices->isNotEmpty()
                    ? 'Validade: ' . $apolices->first()['validade']
                    : 'Sem apólices',
            ],
            [
                'nome'    => 'PRM',
                'ok'      => !$naHotlist,
                'texto'   => $naHotlist ? 'Consta' : 'Não consta',
                'detalhe' => $naHotlist
                    ? 'Motivo: ' . $hotlist['motivo']
                    : 'Sem alertas',
            ],
        ];

        return response()->json([
            'encontrado'    => true,
            'veiculo'       => [
                'id'           => (string) $veiculo->id,
                'matricula'    => $veiculo->matricula,
                'marca'        => $veiculo->marca ?? '—',
                'modelo'       => $veiculo->modelo ?? '—',
                'ano'          => $veiculo->ano,
                'cor'          => $veiculo->cor ?? '—',
                'chassis'      => $veiculo->chassis ?? '—',
                'combustivel'  => $veiculo->combustivel ?? '—',
                'tipo'         => $tipo,
                'provincia'    => $veiculo->provincia ?? '—',
                'score'        => $score,
                'estado'       => $veiculo->estado ?? '—',
            ],
            'proprietario'  => $proprietario,
            'inspecoes'     => $inspecoes,
            'apolices'      => $apolices,
            'multas'        => $multas,
            'hotlist'       => $hotlist,
            'resumo'        => [
                'score'     => $score,
                'estado'    => $veiculo->estado ?? '—',
                'entidades' => $entidades,
            ],
        ]);
    }
}
