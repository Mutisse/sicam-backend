<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provincia extends Model
{
    protected $table = 'provincias';

    protected $fillable = ['codigo', 'nome', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}