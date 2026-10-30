<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartaConducao extends Model
{
    protected $table = 'cartas_conducao';

    protected $fillable = [
        'numero', 'nomes', 'nome_completo', 'nif',
        'data_nascimento', 'data_emissao', 'data_validade', 'data_primeira_emissao',
        'codigo_carta', 'categorias', 'restricoes', 'restricoes_condutor',
        'sexo', 'nacionalidade', 'foto_url', 'assinatura_url', 'estado',
    ];

    protected $casts = [
        'categorias'           => 'array',
        'data_nascimento'      => 'date',
        'data_emissao'         => 'date',
        'data_validade'        => 'date',
        'data_primeira_emissao'=> 'date',
    ];
}