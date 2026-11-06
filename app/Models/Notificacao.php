<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected $fillable = [
        'user_id',
        'titulo',
        'mensagem',
        'tipo',
        'categoria',
        'referencia',
        'url',
        'lida',
        'lida_em',
    ];

    protected $casts = [
        'lida'    => 'boolean',
        'lida_em' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cria a mesma notificação para vários utilizadores.
     *
     * Optimizações:
     *  - insert em massa (1 query só, via Query Builder — ignora casts/events)
     *  - chunks de 500 para evitar pacotes demasiado grandes (TiDB/MySQL)
     *  - sem overhead do Eloquent (não instancia models)
     */
    public static function criarMultiplas(
        array $userIds,
        string $titulo,
        string $mensagem,
        string $tipo = 'info',
        ?string $categoria = null,
        ?string $referencia = null,
        ?string $url = null
    ): int {
        if (empty($userIds)) return 0;

        // Remove duplicados e garante inteiros
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) return 0;

        $agora  = now();
        $total  = 0;
        $chunks = array_chunk($userIds, 500);

        foreach ($chunks as $chunk) {
            $linhas = [];
            foreach ($chunk as $uid) {
                $linhas[] = [
                    'user_id'    => $uid,
                    'titulo'     => $titulo,
                    'mensagem'   => $mensagem,
                    'tipo'       => $tipo,
                    'categoria'  => $categoria,
                    'referencia' => $referencia,
                    'url'        => $url,
                    'lida'       => false,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }

            // Query Builder directo — evita overhead do Eloquent
            DB::table('notificacoes')->insert($linhas);
            $total += count($linhas);
        }

        return $total;
    }
}