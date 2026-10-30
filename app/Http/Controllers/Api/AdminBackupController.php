<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupTipo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBackupController extends Controller
{
    /**
     * GET /api/admin/backups
     */
    public function index()
    {
        $backups = Backup::query()
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($b) => $this->formatar($b));

        $tipos = BackupTipo::orderBy('id')->get()->map(fn ($t) => [
            'key'                  => $t->key,
            'label'                => $t->label,
            'descricao'            => $t->descricao,
            'icon'                 => $t->icon,
            'extensao'             => $t->extensao,
            'prefixo'              => $t->prefixo,
            'tamanhoAprox'         => $t->tamanho_aproximado,
            'periodicidade'        => $t->periodicidade,
            'horaExecucao'         => $t->hora_execucao,
            'ativo'                => (bool) $t->ativo,
        ]);

        return response()->json([
            'items'  => $backups,
            'tipos'  => $tipos,
            'kpis'   => $this->kpis(),
        ]);
    }

    /**
     * POST /api/admin/backups
     * Body: { origem, tipo?, autor? }
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'origem' => 'required|string|in:Completo,Base de dados,Ficheiros',
            'tipo'   => 'nullable|string|in:Automático,Manual',
        ]);

        $cfg = BackupTipo::where('key', $dados['origem'])->first();
        if (!$cfg) {
            return response()->json(['message' => 'Tipo de backup inválido.'], 422);
        }

        $dataHoje = now()->format('Y-m-d');
        $ficheiro = "{$cfg->prefixo}_{$dataHoje}_" . now()->format('His') . ".{$cfg->extensao}";

        $backup = Backup::create([
            'codigo'    => $this->gerarCodigo(),
            'ficheiro'  => $ficheiro,
            'tipo'      => $dados['tipo'] ?? 'Manual',
            'origem'    => $dados['origem'],
            'tamanho'   => $cfg->tamanho_aproximado,
            'estado'    => 'Concluído',
            'autor'     => auth()->user()?->name ?? 'sistema',
            'duracao'   => '3 min 42 s',
            'criado_por'=> auth()->id(),
        ]);

        return response()->json([
            'backup' => $this->formatar($backup),
            'kpis'   => $this->kpis(),
        ], 201);
    }

    /**
     * DELETE /api/admin/backups/{id}
     */
    public function destroy(int $id)
    {
        $b = Backup::findOrFail($id);
        $b->delete();

        return response()->json([
            'ok'   => true,
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * PUT /api/admin/backups/{id}/restaurar
     */
    public function restaurar(int $id)
    {
        $b = Backup::findOrFail($id);

        if ($b->estado !== 'Concluído') {
            return response()->json(
                ['message' => 'Só backups concluídos podem ser restaurados.'],
                422,
            );
        }

        return response()->json([
            'ok'      => true,
            'backup'  => $this->formatar($b),
            'mensagem' => "Restauro iniciado a partir de {$b->codigo}.",
        ]);
    }

    /**
     * GET /api/admin/backups/{id}/download
     * (devolve um ficheiro dummy de texto — em produção viria do disco)
     */
    public function download(int $id)
    {
        $b = Backup::findOrFail($id);

        if ($b->estado !== 'Concluído') {
            return response()->json(
                ['message' => 'Backup ainda não está pronto.'],
                422,
            );
        }

        $conteudo = "SICAM — Backup fictício\n"
            . "Código: {$b->codigo}\n"
            . "Ficheiro: {$b->ficheiro}\n"
            . "Origem: {$b->origem}\n"
            . "Tamanho: {$b->tamanho}\n"
            . "Criado em: {$b->created_at->format('d/m/Y H:i')}\n"
            . "Autor: {$b->autor}\n"
            . "Duracao: {$b->duracao}\n"
            . "\n"
            . "⚠️  Este ficheiro é uma demonstração. O backup real seria o dump real da BD.\n";

        return response($conteudo, 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $b->ficheiro . '"',
        ]);
    }

    /**
     * PUT /api/admin/backups/tipos/{key}
     * Body: { ativo?, periodicidade?, horaExecucao? }
     */
    public function actualizarTipo(Request $request, string $key)
    {
        $tipo = BackupTipo::where('key', $key)->firstOrFail();

        $dados = $request->validate([
            'ativo'         => 'sometimes|boolean',
            'periodicidade' => 'sometimes|string|in:Diário,Semanal,Mensal,Nunca',
            'horaExecucao'  => 'sometimes|string|max:5',
        ]);

        $update = [];
        if (isset($dados['ativo']))         $update['ativo']          = $dados['ativo'];
        if (isset($dados['periodicidade'])) $update['periodicidade']  = $dados['periodicidade'];
        if (isset($dados['horaExecucao']))  $update['hora_execucao']  = $dados['horaExecucao'];

        $tipo->update($update);

        return response()->json([
            'ok'   => true,
            'tipo' => [
                'key'           => $tipo->key,
                'ativo'         => (bool) $tipo->ativo,
                'periodicidade' => $tipo->periodicidade,
                'horaExecucao'  => $tipo->hora_execucao,
            ],
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function formatar(Backup $b): array
    {
        return [
            'id'       => (string) $b->id,
            'codigo'   => $b->codigo,
            'ficheiro' => $b->ficheiro,
            'tipo'     => $b->tipo,
            'origem'   => $b->origem,
            'tamanho'  => $b->tamanho,
            'estado'   => $b->estado,
            'autor'    => $b->autor,
            'duracao'  => $b->duracao,
            'criadoEm' => $b->created_at?->format('d/m H:i') ?? '—',
        ];
    }

    private function kpis(): array
    {
        $ultimo = Backup::orderByDesc('created_at')->first();

        // Soma tamanhos — pegando o valor em MB/GB
        $soma = Backup::query()
            ->where('estado', 'Concluído')
            ->get()
            ->sum(function ($b) {
                return $this->parseTamanhoMB($b->tamanho);
            });

        return [
            'total'    => Backup::count(),
            'ultimo'   => $ultimo?->created_at?->format('d/m H:i') ?? '—',
            'tamanho'  => $soma,
            'falhas'   => Backup::where('estado', 'Falhou')->count(),
        ];
    }

    private function parseTamanhoMB(string $t): float
    {
        $n = (float) str_replace(',', '.', $t);

        if (str_contains($t, 'GB')) return $n * 1024;
        if (str_contains($t, 'MB')) return $n;
        if (str_contains($t, 'KB')) return $n / 1024;
        return 0;
    }

    private function gerarCodigo(): string
    {
        $ano = now()->year;
        $ultimo = Backup::whereYear('created_at', $ano)->orderByDesc('id')->first();

        $seq = 1;
        if ($ultimo && preg_match('/(\d{4})$/', $ultimo->codigo, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('BK-%d-%04d', $ano, $seq);
    }
}