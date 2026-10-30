<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exportacao extends Model
{
    protected $table = 'exportacoes';

    protected $fillable = [
        'codigo',
        'tipo', 'formato',
        'titulo', 'detalhe',
        'data_de', 'data_ate',
        'incluir_cabecalho', 'incluir_resumo', 'apenas_ativos',
        'total_registos', 'tamanho_bytes',
        'criado_por',
    ];

    protected $casts = [
        'data_de'            => 'date',
        'data_ate'           => 'date',
        'incluir_cabecalho'  => 'boolean',
        'incluir_resumo'     => 'boolean',
        'apenas_ativos'      => 'boolean',
        'total_registos'     => 'integer',
        'tamanho_bytes'      => 'integer',
    ];

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}