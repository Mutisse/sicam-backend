<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provincia;
use App\Models\Seguradora;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class IssmCatalogoController extends Controller
{
    /**
     * GET /api/issm/catalogos
     *
     * Devolve tudo o que as stores ISSM precisam num só pedido.
     *
     * Optimizações:
     *  - Cache de 5 min (catálogos mudam pouco)
     *  - Select explícito de colunas (menos dados na rede)
     *  - Eager loading controlado (perfil só para distinguir prefixo)
     *  - Sem N+1
     */
    public function index()
    {
        $dados = Cache::remember('issm.catalogos:v1', now()->addMinutes(5), function () {
            // Seguradoras activas — select só do necessário
            $seguradoras = Seguradora::query()
                ->select('id', 'nome', 'sigla')
                ->where('estado', 'Activa')
                ->orderBy('nome')
                ->get();

            // Províncias activas
            $provincias = Provincia::query()
                ->select('codigo', 'nome')
                ->where('ativo', true)
                ->orderBy('nome')
                ->get();

            // Responsáveis — mantém string[], mas prefixa "Insp. " só para inspetores
            $responsaveis = User::query()
                ->select('id', 'name')
                ->with('perfil:id,key')
                ->whereRelation('perfil', fn ($q) => $q->whereIn('key', ['issm', 'inspector']))
                ->where('estado', 'Ativo')
                ->orderBy('name')
                ->get()
                ->map(fn ($u) => $u->perfil?->key === 'inspector'
                    ? 'Insp. ' . $u->name
                    : $u->name
                )
                ->values();

            return [
                'seguradoras'  => $seguradoras,
                'provincias'   => $provincias,
                'responsaveis' => $responsaveis,

                'tiposSinistro' => [
                    'Colisão',
                    'Furto',
                    'Incêndio',
                    'Inundação',
                    'Vandalismo',
                ],
                'estadosSinistro' => [
                    'Aberto',
                    'Em análise',
                    'Resolvido',
                    'Recusado',
                ],
                'gravidades' => ['Leve', 'Moderado', 'Grave'],

                'tiposApolice' => [
                    'Terceiros',
                    'Terceiros + Roubo',
                    'Todos os riscos',
                ],
                'estadosApolice' => [
                    'Activa',
                    'A expirar',
                    'Expirada',
                    'Anulada',
                ],

                'categoriasSeguradora' => ['Nacional', 'Internacional', 'Mútua'],
                'estadosSeguradora'    => ['Activa', 'Suspensa', 'Inactiva'],
            ];
        });

        return response()->json($dados);
    }
}