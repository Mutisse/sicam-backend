<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hotlist extends Model
{
    protected $table = 'hotlist';

    protected $fillable = [
        'veiculo_id',
        'motivo', 'perigo', 'prioridade',
        'agente', 'posto', 'zona',
        'multas_pendentes', 'valor_multas',
        'estado', 'descricao',
        'historico',
        'detectado_em', 'resolvido_em',
    ];

    protected $casts = [
        'detectado_em'     => 'datetime',
        'resolvido_em'     => 'datetime',
        'historico'        => 'array',
        'multas_pendentes' => 'integer',
        'valor_multas'     => 'integer',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}