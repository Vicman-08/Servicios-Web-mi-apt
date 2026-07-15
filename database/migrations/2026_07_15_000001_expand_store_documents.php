<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $collection): void {
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['name', 'email', 'password', 'role', 'status', 'created_at', 'updated_at'],
                'properties' => [
                    'name' => ['bsonType' => 'string', 'minLength' => 2, 'maxLength' => 100],
                    'email' => ['bsonType' => 'string', 'maxLength' => 150],
                    'password' => ['bsonType' => 'string'],
                    'phone' => ['bsonType' => ['string', 'null'], 'maxLength' => 30],
                    'addresses' => ['bsonType' => 'array', 'maxItems' => 5],
                    'role' => ['enum' => ['admin', 'buyer']],
                    'status' => ['enum' => ['active', 'disabled']],
                    'email_verified_at' => ['bsonType' => ['date', 'null']],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ]);
        });

        Schema::table('products', function (Blueprint $collection): void {
            $collection->index(['category_id' => 1, 'is_active' => 1]);
            $collection->index('tags');
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['sku', 'name', 'price', 'currency', 'stock', 'is_active', 'created_at', 'updated_at'],
                'properties' => [
                    'sku' => ['bsonType' => 'string', 'maxLength' => 40],
                    'name' => ['bsonType' => 'string', 'minLength' => 2, 'maxLength' => 120],
                    'description' => ['bsonType' => ['string', 'null']],
                    'price' => ['bsonType' => 'decimal', 'minimum' => 0],
                    'currency' => ['bsonType' => 'string', 'minLength' => 3, 'maxLength' => 3],
                    'stock' => ['bsonType' => ['int', 'long'], 'minimum' => 0],
                    'is_active' => ['bsonType' => 'bool'],
                    'category_id' => ['bsonType' => ['string', 'null']],
                    'images' => ['bsonType' => 'array', 'maxItems' => 10, 'items' => ['bsonType' => 'string']],
                    'tags' => ['bsonType' => 'array', 'maxItems' => 20, 'items' => ['bsonType' => 'string']],
                    'attributes' => ['bsonType' => ['object', 'array']],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ]);
        });

        Schema::table('orders', function (Blueprint $collection): void {
            $collection->index(['payment_status' => 1, 'created_at' => -1]);
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
                    'subtotal' => ['bsonType' => 'decimal', 'minimum' => 0],
                    'tax' => ['bsonType' => 'decimal', 'minimum' => 0],
                    'shipping_cost' => ['bsonType' => 'decimal', 'minimum' => 0],
                    'total' => ['bsonType' => 'decimal', 'minimum' => 0],
                    'currency' => ['bsonType' => 'string', 'minLength' => 3, 'maxLength' => 3],
                    'status' => ['enum' => ['pending', 'confirmed', 'completed', 'shipped', 'delivered', 'cancelled']],
                    'payment_status' => ['enum' => ['pending', 'paid', 'failed', 'refunded']],
                    'shipping_address' => ['bsonType' => ['object', 'null']],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ]);
        });

        Schema::table('inventory_movements', function (Blueprint $collection): void {
            $collection->index(['type' => 1, 'created_at' => -1]);
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['product_id', 'type', 'quantity_delta', 'stock_before', 'stock_after', 'created_at'],
                'properties' => [
                    'product_id' => ['bsonType' => 'string'],
                    'order_id' => ['bsonType' => ['string', 'null']],
                    'user_id' => ['bsonType' => ['string', 'null']],
                    'type' => ['enum' => ['sale', 'cancellation', 'restock', 'adjustment']],
                    'quantity_delta' => ['bsonType' => ['int', 'long']],
                    'stock_before' => ['bsonType' => ['int', 'long'], 'minimum' => 0],
                    'stock_after' => ['bsonType' => ['int', 'long'], 'minimum' => 0],
                    'reason' => ['bsonType' => ['string', 'null'], 'maxLength' => 500],
                    'metadata' => ['bsonType' => ['object', 'null']],
                    'created_at' => ['bsonType' => 'date'],
                ],
            ]);
        });
    }

    public function down(): void
    {
        // Los campos nuevos son opcionales y pueden permanecer en los documentos.
        // Una reversión no elimina datos del catálogo, usuarios ni compras.
    }
};
