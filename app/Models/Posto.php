<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Posto extends Model
{
    protected $table = 'postos';

    protected $fillable = [
        'nome', 'codigo', 'provincia', 'distrito',
        'endereco', 'latitude', 'longitude', 'telefone',
        'responsavel_id', 'estado',
         'cor', 'notas',   // ⬅️ ADICIONAR ESTES
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function veiculos(): HasMany
    {
        return $this->hasMany(Veiculo::class);
    }

    public function patrulhas(): HasMany
    {
        return $this->hasMany(Patrulha::class);
    }
}