<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 
        'email', 
        'password', 
        'numero_id', 
        'telefone',
        'perfil_id', 
        'posto', 
        'funcao', 
        'estado',
        'turno',      // ⭐ NOVO
        'viatura',    // ⭐ NOVO
        'ultimo_acesso', 
        'foto_url', 
        'preferencias',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acesso'     => 'datetime',
            'password'          => 'hashed',
            'preferencias'      => 'array',
        ];
    }

    // ─── Relações ─────────────────────────────────────────
    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class, 'perfil_id');
    }

    public function notificacoes(): HasMany
    {
        return $this->hasMany(Notificacao::class);
    }

    // ─── Helpers ──────────────────────────────────────────
    public function temPermissao(string $modulo, string $acao): bool
    {
        if (!$this->perfil) return false;
        return $this->perfil->temPermissao($modulo, $acao);
    }

    public function podeVer(string $modulo): bool
    {
        return $this->temPermissao($modulo, 'ver');
    }

    // ─── Helper para disparar notificações ────────────────
    public function notificar(string $titulo, string $mensagem, string $tipo = 'info', string $categoria = 'sistema', ?string $url = null): void
    {
        $this->notificacoes()->create([
            'titulo'     => $titulo,
            'mensagem'   => $mensagem,
            'tipo'       => $tipo,
            'categoria'  => $categoria,
            'referencia' => null,
            'url'        => $url,
            'lida'       => false,
        ]);
    }

    // ─── Acessores para o frontend ────────────────────────
    protected $appends = ['nome', 'perfil_key', 'iniciais'];

    public function getNomeAttribute(): string
    {
        return $this->attributes['name'] ?? '';
    }

    public function getPerfilKeyAttribute(): ?string
    {
        return $this->perfil?->key;
    }

    public function getIniciaisAttribute(): string
    {
        $nome = trim($this->name ?? '');
        if (!$nome) return '?';
        $partes = preg_split('/\s+/', $nome);
        $p = mb_substr($partes[0] ?? '', 0, 1);
        $u = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';
        return mb_strtoupper($p . $u);
    }
}