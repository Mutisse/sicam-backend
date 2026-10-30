<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartaConducao;
use App\Models\Noticia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PortalCidadaoController extends Controller
{
    /**
     * GET /api/portal/noticias
     * Lista as notícias publicadas (mais recentes)
     */
    public function noticias()
    {
        $items = Noticia::query()
            ->where('publicada', true)
            ->orderByDesc('data_publicacao')
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'id'       => (string) $n->id,
                'titulo'   => $n->titulo,
                'resumo'   => $n->resumo,
                'categoria'=> $n->categoria,
                'autor'    => $n->autor,
                'data'     => $this->formatarData($n->data_publicacao),
                'destaque' => (bool) $n->destaque,
            ]);

        return response()->json(['items' => $items]);
    }

    /**
     * GET /api/portal/cartas/{numero}
     * Consulta pública de uma carta + as suas multas associadas
     */
    public function consultarCarta(string $numero)
    {
        $numero = preg_replace('/\D/', '', $numero);

        if (strlen($numero) !== 9) {
            return response()->json(
                ['message' => 'O número da carta tem de ter 9 dígitos.'],
                422,
            );
        }

        $carta = CartaConducao::where('numero', $numero)->first();

        if (!$carta) {
            return response()->json(
                ['message' => 'Nenhuma carta encontrada com esse número.'],
                404,
            );
        }

        // Multas associadas — via NIF do proprietário dos veículos
        $multas = [];
        if ($carta->nif) {
            $multas = DB::table('multas')
                ->join('veiculos', 'veiculos.id', '=', 'multas.veiculo_id')
                ->leftJoin('users', 'users.id', '=', 'multas.agente_id')
                ->leftJoin('postos', 'postos.id', '=', 'multas.posto_id')
                ->where('veiculos.proprietario_numero_id', $carta->nif)
                ->select(
                    'multas.numero as codigo',
                    'veiculos.matricula',
                    'multas.infracao',
                    'users.name as agente',
                    'multas.data',
                    'multas.valor',
                    'multas.estado',
                    'postos.nome as posto',
                )
                ->orderByDesc('multas.data')
                ->limit(100)
                ->get()
                ->map(fn ($m) => [
                    'codigo'   => $m->codigo,
                    'matricula'=> $m->matricula ?? '—',
                    'infracao' => $m->infracao ?? '—',
                    'agente'   => $m->agente ?? '—',
                    'posto'    => $m->posto ?? '—',
                    'data'     => $m->data
                        ? Carbon::parse($m->data)->format('d/m H:i')
                        : '—',
                    'valor'    => $this->formatarMoeda((float) $m->valor),
                    'estado'   => $m->estado,
                ])
                ->toArray();
        }

        // Renovações — validade da carta
        $diasParaExpirar = $carta->data_validade
            ? Carbon::today()->diffInDays($carta->data_validade, false)
            : null;

        return response()->json([
            'cidadao' => [
                'numero'      => $carta->numero,
                'nomes'       => $carta->nomes,
                'nome'        => $carta->nome_completo,
                'nif'         => $carta->nif,
                'categoria'   => implode(', ', $carta->categorias ?? []),
                'categorias'  => $carta->categorias ?? [],
                'sexo'        => $carta->sexo,
                'nacionalidade'=> $carta->nacionalidade,
                'dataNascimento' => $carta->data_nascimento?->format('d/m/Y'),
                'dataEmissao' => $carta->data_emissao?->format('d/m/Y'),
                'dataValidade'=> $carta->data_validade?->format('d/m/Y'),
                'codigoCarta' => $carta->codigo_carta,
                'estado'      => $carta->estado,
                'diasParaExpirar'=> $diasParaExpirar,
            ],
            'multas'  => $multas,
            'totais'  => [
                'total'      => count($multas),
                'pendentes'  => count(array_filter($multas, fn ($m) => $m['estado'] === 'Pendente')),
                'pagas'      => count(array_filter($multas, fn ($m) => $m['estado'] === 'Paga')),
                'contestadas'=> count(array_filter($multas, fn ($m) => $m['estado'] === 'Contestada')),
            ],
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatarData($data): string
    {
        if (!$data) return '—';
        $d = Carbon::parse($data);
        $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        return $d->day . ' ' . $meses[$d->month - 1];
    }

    private function formatarMoeda(float $valor): string
    {
        return number_format($valor, 0, ',', "\u{00a0}") . ' MT';
    }
}