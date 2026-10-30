<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escala extends Model
{
    protected $table = 'escalas';

    protected $fillable = [
        'agente_id',
        'posto',
        'semana',
        'estado',
        'atribuicoes',
        'notas',
        'criado_por',
    ];

    protected $casts = [
        'atribuicoes' => 'array',
    ];

    public function agente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agente_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}