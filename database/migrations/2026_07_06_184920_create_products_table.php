<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::create('products', function (Blueprint $collection): void {
            $collection->unique('sku');
            $collection->index(['is_active' => 1, 'name' => 1]);
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
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
