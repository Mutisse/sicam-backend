<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seguradora extends Model
{
    protected $table = 'seguradoras';

    protected $fillable = [
        'codigo', 'sigla', 'nome', 'nif', 'categoria',
        'email', 'telefone', 'website',
        'endereco', 'cidade', 'provincia',
        'responsavel', 'contacto_responsavel',
        'numero_apoles', 'numero_sinistros',
        'estado', 'observacoes', 'registada_em',
        'criado_por',
    ];

    protected $casts = [
        'numero_apoles'    => 'integer',
        'numero_sinistros' => 'integer',
        'registada_em'     => 'datetime',
    ];

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}