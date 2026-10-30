<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recibo extends Model
{
    protected $table = 'recibos';

    protected $fillable = [
        'numero', 'multa_id',
        'metodo', 'pagador', 'referencia', 'valor',
        'estado', 'observacoes',
        'criado_por',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
    ];

    public function multa(): BelongsTo
    {
        return $this->belongsTo(Multa::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}