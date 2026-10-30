<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Multa extends Model
{
    protected $table = 'multas';

    protected $fillable = [
        'numero', 'veiculo_id', 'agente_id', 'posto_id',
        'infracao', 'provincia', 'valor', 'estado',
        'data', 'data_pagamento', 'observacoes',
    ];

    protected $casts = [
        'valor'          => 'decimal:2',
        'data'           => 'date',
        'data_pagamento' => 'date',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agente_id');
    }

    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }
}