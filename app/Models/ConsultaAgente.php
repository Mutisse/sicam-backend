<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultaAgente extends Model
{
    protected $table = 'consultas_agente';

    protected $fillable = [
        'agente_id',
        'veiculo_id',
        'matricula',
        'posto',
        'score',
        'estado',
        'origem',
        'consultado_em',
    ];

    protected $casts = [
        'consultado_em' => 'datetime',
    ];

    public function agente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agente_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }
}