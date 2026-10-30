<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupTipo extends Model
{
    protected $table = 'backup_tipos';

    protected $fillable = [
        'key', 'label', 'descricao', 'icon',
        'extensao', 'prefixo', 'tamanho_aproximado',
        'periodicidade', 'hora_execucao', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}