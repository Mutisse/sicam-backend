<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Abordagem extends Model
{
    protected $table = 'abordagens';

    protected $fillable = [
        'codigo', 'veiculo_id', 'agente_id', 'posto_id',
        'local', 'resultado', 'data', 'observacoes',
    ];

    protected $casts = [
        'data' => 'date',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agente_id');
    }

    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }
}