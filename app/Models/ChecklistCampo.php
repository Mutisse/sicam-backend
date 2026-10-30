<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistCampo extends Model
{
    protected $table = 'checklist_campos';

    protected $fillable = [
        'seccao_id', 'chave', 'label', 'tipo',
        'opcoes', 'min', 'max', 'invertido',
        'so_se_gasolina', 'so_se_diesel', 'unidade',
        'ordem', 'ativo',
    ];

    protected $casts = [
        'opcoes'         => 'array',
        'min'            => 'decimal:3',
        'max'            => 'decimal:3',
        'invertido'      => 'boolean',
        'so_se_gasolina' => 'boolean',
        'so_se_diesel'   => 'boolean',
        'ordem'          => 'integer',
        'ativo'          => 'boolean',
    ];

    public function seccao(): BelongsTo
    {
        return $this->belongsTo(ChecklistSeccao::class, 'seccao_id');
    }
}