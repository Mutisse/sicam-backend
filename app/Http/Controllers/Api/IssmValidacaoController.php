<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Apolice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class IssmValidacaoController extends Controller
{
    /**
     * GET /api/issm/validacao/{matricula}
     *
     * Endpoint público de consulta de seguro obrigatório.
     * NÃO devolve PII (nome/telefone/email do segurado).
     * Cache de 5 min por matrícula.
     */
    public function consultar(string $matricula)
    {
        $matricula = strtoupper(trim($matricula));

        $dados = Cache::remember(
            "issm.validacao:{$matricula}",
            now()->addMinutes(5),
            fn () => $this->construirResposta($matricula)
        );

        return response()->json($dados);
    }

    /**
     * Constrói a resposta (sem cache). Chamado pelo `consultar`.
     */
    private function construirResposta(string $matricula): array
    {
        // Procurar apólice activa (ignora Anuladas) mais recente
        $apolice = Apolice::query()
            ->whereHas('veiculo', fn ($q) => $q->where('matricula', $matricula))
            ->where('estado', '!=', 'Anulada')
            ->orderByDesc('validade')
            ->first();

        // ---------- Não encontrada ----------
        if (!$apolice) {
            return [
                'estado'     => 'nao-encontrada',
                'icone'      => 'help',
                'titulo'     => 'Sem seguro registado',
                'subtitulo'  => "Nenhuma apólice encontrada para {$matricula}",
                'matricula'  => $matricula,
                'apolice'    => '—',
                'seguradora' => '—',
                'tipo'       => '—',
                'inicio'     => '—',
                'fim'        => '—',
                'aviso'      => 'Esta matrícula não tem seguro registado no sistema. O proprietário deve contactar uma seguradora licenciada.',
            ];
        }

        // ---------- Validade em falta (dados incompletos) ----------
        $validade = $apolice->validade;
        if (!$validade) {
            return [
                'estado'     => 'sem-validade',
                'icone'      => 'help',
                'titulo'     => 'Apólice sem validade registada',
                'subtitulo'  => "A apólice {$apolice->numero} não tem data de validade.",
                'matricula'  => $matricula,
                'apolice'    => $apolice->numero,
                'seguradora' => $apolice->seguradora,
                'tipo'       => $apolice->tipo,
                'inicio'     => $apolice->inicio?->format('d/m/Y') ?? '—',
                'fim'        => '—',
                'aviso'      => 'Apólice com dados incompletos. Contacte a seguradora.',
            ];
        }

        // ---------- Calcular estado real ----------
        $hoje = Carbon::today();
        $dias = (int) $hoje->diffInDays($validade, false);

        if ($dias < 0) {
            $estado = 'expirada';
            $icone  = 'cancel';
            $titulo = 'Seguro expirado';
            $sub    = "Expirou em {$validade->format('d/m/Y')} (há " . abs($dias) . ' dias)';
            $aviso  = 'ATENÇÃO: Seguro expirado. Circulação sem seguro constitui infração grave.';
        } elseif ($dias <= 30) {
            $estado = 'a-expirar';
            $icone  = 'hourglass_top';
            $titulo = 'Seguro a expirar';
            $sub    = "Expira em {$validade->format('d/m/Y')} ({$dias} dias)";
            $aviso  = "A apólice expira em {$dias} dias. Renove para evitar coimas.";
        } else {
            $estado = 'valida';
            $icone  = 'verified_user';
            $titulo = 'Seguro válido';
            $sub    = "Válido até {$validade->format('d/m/Y')}";
            $aviso  = "O seguro obrigatório está em vigor. Faltam {$dias} dias para o fim da apólice.";
        }

        return [
            'estado'        => $estado,
            'icone'         => $icone,
            'titulo'        => $titulo,
            'subtitulo'     => $sub,
            'matricula'     => $matricula,
            'apolice'       => $apolice->numero,
            'seguradora'    => $apolice->seguradora,
            'tipo'          => $apolice->tipo,
            'inicio'        => $apolice->inicio?->format('d/m/Y') ?? '—',
            'fim'           => $apolice->validade?->format('d/m/Y') ?? '—',
            'diasRestantes' => $dias,
            'aviso'         => $aviso,
        ];
    }
}