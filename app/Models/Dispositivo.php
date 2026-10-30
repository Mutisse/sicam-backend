<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    protected $fillable = [
        'serie', 'modelo', 'agente', 'estado',
        'versao_app', 'ultima_sincronizacao',
        'notas', 'criado_por',
    ];

    protected $casts = [
        'ultima_sincronizacao' => 'datetime',
    ];

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}