<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistSeccao extends Model
{
    protected $table = 'checklist_seccoes';

    protected $fillable = ['chave', 'label', 'ordem', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public function campos(): HasMany
    {
        return $this->hasMany(ChecklistCampo::class, 'seccao_id')
            ->orderBy('ordem');
    }
}