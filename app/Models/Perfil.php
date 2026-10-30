<?php
// app/Models/Perfil.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Perfil extends Model
{
    use HasFactory;

    protected $table = 'perfis';

    protected $fillable = [
        'key',
        'label',
        'descricao',
        'icon',
        'cor',
        'estado',
        'sistema',
    ];

    protected function casts(): array
    {
        return [
            'sistema' => 'boolean',
        ];
    }

    // ─── Relações ─────────────────────────────────────────
    public function utilizadores(): HasMany
    {
        return $this->hasMany(User::class, 'perfil_id');
    }

    public function permissoes(): HasMany
    {
        return $this->hasMany(PerfilPermissao::class, 'perfil_id');
    }

    // ─── Helpers ──────────────────────────────────────────
    public function temPermissao(string $modulo, string $acao): bool
    {
        $perm = $this->permissoes->firstWhere('modulo', $modulo);
        if (!$perm) return false;

        return match ($acao) {
            'ver'    => $perm->ver,
            'criar'  => $perm->criar,
            'editar' => $perm->editar,
            'apagar' => $perm->apagar,
            default  => false,
        };
    }

    public function podeVer(string $modulo): bool
    {
        return $this->temPermissao($modulo, 'ver');
    }
}