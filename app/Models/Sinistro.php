<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sinistro extends Model
{
    protected $table = 'sinistros';

    protected $fillable = [
        'numero',
        'apolice_id',
        'matricula',
        'segurado',
        'seguradora',
        'tipo',
        'gravidade',
        'data',
        'local',
        'descricao',
        'valor_estimado',
        'valor_aprovado',
        'estado',
        'responsavel',
        'observacoes',
        'criado_por',
    ];

    protected $casts = [
        'data'           => 'date',
        'valor_estimado' => 'integer',
        'valor_aprovado' => 'integer',
    ];

    public function apolice(): BelongsTo
    {
        return $this->belongsTo(Apolice::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}