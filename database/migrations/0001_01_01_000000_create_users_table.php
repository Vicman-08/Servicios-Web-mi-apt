<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::create('users', function (Blueprint $collection): void {
            $collection->unique('email');
            $collection->index(['role' => 1, 'status' => 1]);
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['name', 'email', 'password', 'role', 'status', 'created_at', 'updated_at'],
                'properties' => [
                    'name' => ['bsonType' => 'string', 'minLength' => 2, 'maxLength' => 100],
                    'email' => ['bsonType' => 'string', 'maxLength' => 150],
                    'password' => ['bsonType' => 'string'],
                    'role' => ['enum' => ['admin', 'buyer']],
                    'status' => ['enum' => ['active', 'disabled']],
                    'email_verified_at' => ['bsonType' => ['date', 'null']],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
