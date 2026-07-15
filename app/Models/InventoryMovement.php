<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'mongodb';

    protected $table = 'inventory_movements';

    protected $fillable = [
        'product_id',
        'order_id',
        'user_id',
        'type',
        'quantity_delta',
        'stock_before',
        'stock_after',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
        ];
    }
}
