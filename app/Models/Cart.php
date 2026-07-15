<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Cart extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'carts';

    protected $fillable = [
        'user_id',
        'items',
        'currency',
    ];
}
