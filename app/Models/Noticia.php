<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Noticia extends Model
{
    protected $table = 'noticias';

    protected $fillable = [
        'titulo', 'resumo', 'conteudo', 'categoria', 'autor',
        'imagem_url', 'data_publicacao', 'destaque', 'publicada',
    ];

    protected $casts = [
        'data_publicacao' => 'date',
        'destaque'        => 'boolean',
        'publicada'       => 'boolean',
    ];
}