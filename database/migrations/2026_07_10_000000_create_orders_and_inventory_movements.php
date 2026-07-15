<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::create('orders', function (Blueprint $collection): void {
            $collection->index(['user_id' => 1, 'created_at' => -1]);
            $collection->index(['status' => 1, 'created_at' => -1]);
            $collection->index('items.product_id');
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['user_id', 'items', 'total', 'currency', 'status', 'created_at', 'updated_at'],
                'properties' => [
                    'user_id' => ['bsonType' => 'string'],
                    'items' => [
                        'bsonType' => 'array',
                        'minItems' => 1,
                        'items' => [
                            'bsonType' => 'object',
                            'required' => ['product_id', 'sku', 'name', 'unit_price', 'quantity', 'subtotal'],
                            'properties' => [
                                'product_id' => ['bsonType' => 'string'],
                                'sku' => ['bsonType' => 'string'],
                                'name' => ['bsonType' => 'string'],
                                'unit_price' => ['bsonType' => 'decimal', 'minimum' => 0],
                                'quantity' => ['bsonType' => ['int', 'long'], 'minimum' => 1],
                                'subtotal' => ['bsonType' => 'decimal', 'minimum' => 0],
                            ],
                        ],
                    ],
                    'total' => ['bsonType' => 'decimal', 'minimum' => 0],
                    'currency' => ['bsonType' => 'string'],
                    'status' => ['enum' => ['completed', 'cancelled']],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ]);
        });

        Schema::create('inventory_movements', function (Blueprint $collection): void {
            $collection->index(['product_id' => 1, 'created_at' => -1]);
            $collection->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('orders');
    }
};
