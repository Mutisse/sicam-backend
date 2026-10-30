<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Patrulha extends Model
{
    protected $table = 'patrulhas';

    protected $fillable = [
        'codigo',
        'nome',
        'turno',
        'posto_id',
        'agente_id',
        'viatura',
        'local',
        'prioridade',
        'pontos_previstos',
        'pontos_concluidos',
        'paragens',
        'notas',
        'estado',
        'inicio',
        'fim',
    ];

    protected $casts = [
        'inicio'            => 'datetime',
        'fim'               => 'datetime',
        'paragens'          => 'array',
        'pontos_previstos'  => 'integer',
        'pontos_concluidos' => 'integer',
    ];

    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agente_id');
    }
}