<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inspecao extends Model
{
    protected $table = 'inspecoes';

    protected $fillable = [
        'codigo',
        'veiculo_id',
        'posto_id',
        'data',
        'validade',
        'proxima_inspecao',
        'resultado',
        'estado',
        'tipo',
        'observacoes',
        'checklist',
        'inspetor',
    ];

    protected $casts = [
        'data'             => 'date',
        'validade'         => 'date',
        'proxima_inspecao' => 'date',
        'checklist'        => 'array',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }
}