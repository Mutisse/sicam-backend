<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $table = 'backups';

    protected $fillable = [
        'codigo', 'ficheiro', 'tipo', 'origem',
        'tamanho', 'estado', 'autor', 'duracao',
        'criado_por',
    ];
}