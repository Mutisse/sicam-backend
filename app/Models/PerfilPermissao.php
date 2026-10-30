<?php
// app/Models/PerfilPermissao.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilPermissao extends Model
{
    use HasFactory;

    protected $table = 'perfil_permissoes';

    protected $fillable = [
        'perfil_id',
        'modulo',
        'ver',
        'criar',
        'editar',
        'apagar',
    ];

    protected function casts(): array
    {
        return [
            'ver'    => 'boolean',
            'criar'  => 'boolean',
            'editar' => 'boolean',
            'apagar' => 'boolean',
        ];
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class, 'perfil_id');
    }
}