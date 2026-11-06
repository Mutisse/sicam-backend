<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use Illuminate\Http\Request;

class PrmAlertaController extends Controller
{
    /**
     * GET /api/alertas
     * Devolve { items, kpis } — contrato do alertas.service.ts.
     */
    public function index(Request $request)
    {
        $query = Alerta::query()
            ->when($request->filled('nivel'), fn ($q) => $q->where('nivel', $request->nivel))
            ->when($request->filled('tipo'),  fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('lido'),
                fn ($q) => $q->where('lido', filter_var($request->lido, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $items = $query->get()->map(function (Alerta $a) {
            return [
                'id'         => (string) $a->id,
                'nivel'      => $a->nivel,
                'tipo'       => $a->tipo,
                'titulo'     => $a->titulo,
                'mensagem'   => $a->mensagem,
                'hora'       => $a->created_at ? $a->created_at->format('H:i') : '',
                'referencia' => $a->referencia,
                'origem'     => $a->origem,
                'lido'       => (bool) $a->lido,
                'criadoEm'   => $a->created_at ? $a->created_at->format('d/m/Y H:i') : '',
            ];
        })->values();

        $kpis = [
            'criticos' => $items->where('nivel', 'critico')->count(),
            'atencao'  => $items->where('nivel', 'atencao')->count(),
            'info'     => $items->where('nivel', 'info')->count(),
            'lidos'    => $items->where('lido', true)->count(),
            'total'    => $items->count(),
        ];

        return response()->json([
            'items' => $items,
            'kpis'  => $kpis,
        ]);
    }

    /**
     * PUT /api/alertas/ler-todos
     * Devolve { total: N } — o store usa esse valor.
     */
    public function marcarTodosLidos()
    {
        $afetados = Alerta::where('lido', false)->update([
            'lido'    => true,
            'lido_em' => now(),
        ]);

        return response()->json(['total' => $afetados]);
    }

    /**
     * PUT /api/alertas/{id}/ler
     */
    public function marcarLido($id)
    {
        $alerta = Alerta::find($id);
        if (!$alerta) {
            return response()->json(['message' => 'Alerta não encontrado.'], 404);
        }

        if (!$alerta->lido) {
            $alerta->update(['lido' => true, 'lido_em' => now()]);
        }

        return response()->json(['data' => $alerta->fresh()]);
    }

    /**
     * PUT /api/alertas/{id}/nao-lido
     */
    public function marcarNaoLido($id)
    {
        $alerta = Alerta::find($id);
        if (!$alerta) {
            return response()->json(['message' => 'Alerta não encontrado.'], 404);
        }

        $alerta->update(['lido' => false, 'lido_em' => null]);

        return response()->json(['data' => $alerta->fresh()]);
    }
}