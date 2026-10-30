<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use Illuminate\Http\Request;

class AlertaController extends Controller
{
    /**
     * GET /api/alertas
     */
    public function index(Request $request)
    {
        $query = Alerta::query();

        if ($request->filled('nivel')) {
            $query->where('nivel', $request->nivel);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('pesquisa')) {
            $q = $request->pesquisa;
            $query->where(function ($sub) use ($q) {
                $sub->where('titulo', 'like', "%{$q}%")
                    ->orWhere('mensagem', 'like', "%{$q}%")
                    ->orWhere('referencia', 'like', "%{$q}%");
            });
        }

        // ⭐ Limite de 100 para não travar
        $items = $query->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($a) => $this->formatar($a));

        return response()->json([
            'items' => $items,
            'kpis'  => $this->kpis(),
        ]);
    }

    /**
     * ⭐ KPIs — 1 query com SUM(CASE WHEN)
     */
    private function kpis(): array
    {
        $row = Alerta::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN nivel = 'critico' AND lido = 0 THEN 1 ELSE 0 END) as criticos,
            SUM(CASE WHEN nivel = 'atencao' AND lido = 0 THEN 1 ELSE 0 END) as atencao,
            SUM(CASE WHEN nivel = 'info'    AND lido = 0 THEN 1 ELSE 0 END) as info,
            SUM(CASE WHEN lido = 1 THEN 1 ELSE 0 END) as lidos
        ")->first();

        return [
            'criticos' => (int) $row->criticos,
            'atencao'  => (int) $row->atencao,
            'info'     => (int) $row->info,
            'lidos'    => (int) $row->lidos,
            'total'    => (int) $row->total,
        ];
    }

    private function formatar(Alerta $a): array
    {
        return [
            'id'         => (string) $a->id,
            'nivel'      => $a->nivel,
            'tipo'       => $a->tipo,
            'titulo'     => $a->titulo,
            'mensagem'   => $a->mensagem,
            'hora'       => $this->tempoRelativo($a->created_at),
            'referencia' => $a->referencia,
            'origem'     => $a->origem,
            'lido'       => $a->lido,
            'criadoEm'   => $a->created_at?->format('d/m H:i') ?? '—',
        ];
    }

    private function tempoRelativo($data): string
    {
        if (!$data) return '—';
        $diff = now()->diffInMinutes($data);
        if ($diff < 1) return 'agora';
        if ($diff < 60) return "há {$diff} min";
        $horas = floor($diff / 60);
        if ($horas < 24) return "há {$horas} h";
        $dias = floor($horas / 24);
        return "há {$dias} d";
    }

    /**
     * PUT /api/alertas/{id}/ler
     */
    public function marcarLido(int $id)
    {
        Alerta::where('id', $id)->update([
            'lido'    => true,
            'lido_em' => now(),
        ]);
        return response()->noContent();
    }

    /**
     * PUT /api/alertas/{id}/nao-lido
     */
    public function marcarNaoLido(int $id)
    {
        Alerta::where('id', $id)->update([
            'lido'    => false,
            'lido_em' => null,
        ]);
        return response()->noContent();
    }

    /**
     * PUT /api/alertas/ler-todos
     */
    public function marcarTodosLidos()
    {
        // ⭐ 1 query só — o retorno do update() já nos dá o total
        $total = Alerta::where('lido', false)->update([
            'lido'    => true,
            'lido_em' => now(),
        ]);

        return response()->json(['total' => $total]);
    }
}