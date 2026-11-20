<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Notificacao extends Model
{
    use HasFactory;

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

    /**
     * ⭐ Cria uma notificação para vários utilizadores de uma só vez.
     */
    public static function criarMultiplas(
        array $userIds,
        string $titulo,
        string $mensagem,
        string $tipo = 'info',
        ?string $categoria = null,
        ?string $referencia = null,
        ?string $url = null
    ): void {
        if (empty($userIds)) return;

        try {
            $agora = now();
            $dados = array_map(function ($userId) use ($titulo, $mensagem, $tipo, $categoria, $referencia, $url, $agora) {
                return [
                    'user_id'    => $userId,
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
            }, $userIds);

            self::insert($dados);
        } catch (\Throwable $e) {
            Log::error('[SICAM-NOTIFICACAO] Falha ao inserir múltiplas notificações', [
                'erro' => $e->getMessage()
            ]);
        }
    }

    /**
     * Relação com o Utilizador
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}