<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Veiculo extends Model
{
    protected $table = 'veiculos';

    protected $fillable = [
        'matricula', 'marca', 'modelo', 'ano', 'cor',
        'proprietario_nome', 'proprietario_numero_id',
        'posto_id', 'provincia',
        'score', 'estado', 'ultima_sincronizacao',
    ];

    protected $casts = [
        'ultima_sincronizacao' => 'datetime',
        'score'                => 'integer',
    ];

    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }
}