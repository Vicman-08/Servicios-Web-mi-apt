<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // Agregamos esto para permitir guardar estos campos
   protected $fillable = [
        'name',
        'price',
        'stock',
        'description'
    ];
}
