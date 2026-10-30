<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = [
        'nivel',
        'tipo',
        'titulo',
        'mensagem',
        'referencia',
        'origem',
        'lido',
        'lido_em',
    ];

    protected $casts = [
        'lido'    => 'boolean',
        'lido_em' => 'datetime',
    ];
}