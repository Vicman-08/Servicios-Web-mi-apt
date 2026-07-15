<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Order extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'items',
        'subtotal',
        'tax',
        'shipping_cost',
        'total',
        'currency',
        'status',
        'payment_status',
        'shipping_address',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
