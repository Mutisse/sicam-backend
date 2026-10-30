<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

        $agora = now();
        $linhas = [];

        foreach ($userIds as $uid) {
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

        // insert em massa — 1 query só
        return Notificacao::insert($linhas) ? count($linhas) : 0;
    }
}