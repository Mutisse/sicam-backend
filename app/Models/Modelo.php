<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Modelo extends Model
{
    protected $table = 'modelos';

    protected $fillable = ['marca_id', 'nome', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }
}