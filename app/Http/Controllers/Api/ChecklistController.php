<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistSeccao;
use Illuminate\Support\Facades\Cache;

class ChecklistController extends Controller
{
    /**
     * GET /api/checklist
     *
     * Devolve toda a estrutura do checklist INATRO, com secções e campos.
     * Cache de 1 hora — o checklist raramente muda.
     */
    public function index()
    {
        $seccoes = Cache::remember('checklist.estrutura', now()->addHour(), function () {
            return ChecklistSeccao::query()
                ->where('ativo', true)
                ->with(['campos' => fn ($q) => $q->where('ativo', true)])
                ->orderBy('ordem')
                ->get()
                ->map(function ($s) {
                    return [
                        'chave'  => $s->chave,
                        'label'  => $s->label,
                        'ordem'  => $s->ordem,
                        'campos' => $s->campos->map(fn ($c) => [
                            'chave'          => $c->chave,
                            'label'          => $c->label,
                            'tipo'           => $c->tipo,
                            'opcoes'         => $c->opcoes,
                            'min'            => $c->min !== null ? (float) $c->min : null,
                            'max'            => $c->max !== null ? (float) $c->max : null,
                            'invertido'      => $c->invertido,
                            'so_se_gasolina' => $c->so_se_gasolina,
                            'so_se_diesel'   => $c->so_se_diesel,
                            'unidade'        => $c->unidade,
                            'ordem'          => $c->ordem,
                        ]),
                    ];
                });
        });

        return response()->json($seccoes);
    }
}