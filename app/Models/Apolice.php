<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Apolice extends Model
{
    protected $table = 'apolices';

    protected $fillable = [
        'numero',
        'veiculo_id',
        'segurado',
        'nif',
        'telefone',
        'email',
        'seguradora',
        'tipo',
        'inicio',
        'validade',
        'premio',
        'estado',
        'observacoes',
    ];

    protected $casts = [
        'inicio'   => 'date',
        'validade' => 'date',
        'premio'   => 'integer',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}