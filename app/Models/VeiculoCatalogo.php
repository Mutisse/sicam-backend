<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeiculoCatalogo extends Model
{
    protected $table = 'veiculos_catalogo';

    protected $fillable = [
        'marca', 'modelo', 'categoria', 'combustivel',
        'origem', 'popularidade', 'tipo_veiculo',
    ];
}