<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoNoticia extends Model
{
    protected $table = 'autos_noticia';

    protected $fillable = [
        'codigo',
        'matricula',
        'infracao_codigo',
        'infracao_label',
        'valor',
        'local',
        'observacoes',
        'agente_id',
        'posto',
        'estado',      // Emitido | Impresso | Anulado
        'emitido_em',
    ];

    protected $casts = [
        'valor'      => 'decimal:2',
        'emitido_em' => 'datetime',
    ];

    // ── Relações ─────────────────────────────────────────
    public function agente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agente_id');
    }
}