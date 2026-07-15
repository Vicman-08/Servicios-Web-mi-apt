<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::create('categories', function (Blueprint $collection): void {
            $collection->unique('slug');
            $collection->index(['is_active' => 1, 'name' => 1]);
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['name', 'slug', 'is_active', 'created_at', 'updated_at'],
                'properties' => [
                    'name' => ['bsonType' => 'string', 'minLength' => 2, 'maxLength' => 100],
                    'slug' => ['bsonType' => 'string', 'minLength' => 2, 'maxLength' => 120],
                    'description' => ['bsonType' => ['string', 'null']],
                    'is_active' => ['bsonType' => 'bool'],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ]);
        });

        Schema::create('carts', function (Blueprint $collection): void {
            $collection->unique('user_id');
            $collection->index('items.product_id');
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['user_id', 'items', 'currency', 'created_at', 'updated_at'],
                'properties' => [
                    'user_id' => ['bsonType' => 'string'],
                    'currency' => ['bsonType' => 'string', 'minLength' => 3, 'maxLength' => 3],
                    'items' => [
                        'bsonType' => 'array',
                        'items' => [
                            'bsonType' => 'object',
                            'required' => ['product_id', 'quantity'],
                            'properties' => [
                                'product_id' => ['bsonType' => 'string'],
                                'quantity' => ['bsonType' => ['int', 'long'], 'minimum' => 1],
                                'added_at' => ['bsonType' => ['date', 'null']],
                            ],
                        ],
                    ],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ]);
        });

        Schema::create('ai_interactions', function (Blueprint $collection): void {
            $collection->index(['user_id' => 1, 'created_at' => -1]);
            $collection->index(['status' => 1, 'created_at' => -1]);
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['query', 'provider', 'model', 'status', 'created_at'],
                'properties' => [
                    'user_id' => ['bsonType' => ['string', 'null']],
                    'query' => ['bsonType' => 'string', 'minLength' => 2, 'maxLength' => 500],
                    'response' => ['bsonType' => ['string', 'null']],
                    'provider' => ['bsonType' => 'string', 'maxLength' => 50],
                    'model' => ['bsonType' => 'string', 'maxLength' => 100],
                    'status' => ['enum' => ['success', 'error']],
                    'duration_ms' => ['bsonType' => ['int', 'long', 'null'], 'minimum' => 0],
                    'metadata' => ['bsonType' => ['object', 'null']],
                    'created_at' => ['bsonType' => 'date'],
                ],
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('categories');
    }
};
