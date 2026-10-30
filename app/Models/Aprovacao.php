<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aprovacao extends Model
{
    protected $table = 'aprovacoes';

    protected $fillable = [
        'codigo',
        'tipo', 'titulo', 'matricula',
        'agente', 'posto',
        'prioridade', 'estado',
        'detalhes',
        'motivo_rejeicao', 'decidido_por', 'decidido_em',
        'criado_por',
    ];

    protected $casts = [
        'detalhes'    => 'array',
        'decidido_em' => 'datetime',
    ];

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}