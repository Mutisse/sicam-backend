<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contestacao extends Model
{
    protected $table = 'contestacoes';

    protected $fillable = [
        'numero', 'multa_codigo', 'matricula', 'infracao', 'valor_multa',
        'contestante', 'nif', 'contacto',
        'motivo', 'descricao', 'prioridade',
        'data_submissao', 'prazo_limite', 'estado',
        'decisao', 'decisao_em', 'decisao_por',
        'observacoes', 'criado_por',
    ];

    protected $casts = [
        'valor_multa'    => 'decimal:2',
        'data_submissao' => 'date',
        'prazo_limite'   => 'date',
        'decisao_em'     => 'datetime',
    ];

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}