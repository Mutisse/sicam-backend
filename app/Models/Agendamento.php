<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agendamento extends Model
{
    protected $table = 'agendamentos';

    protected $fillable = [
        'codigo',
        'veiculo_id',
        'matricula', 'marca', 'modelo',
        'proprietario', 'nif', 'telefone',
        'data', 'hora', 'duracao', 'tipo',
        'posto_id', 'posto_nome', 'inspetor',
        'estado', 'observacoes', 'criado_por',
    ];

    protected $casts = [
        'data'    => 'date',
        'duracao' => 'integer',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}