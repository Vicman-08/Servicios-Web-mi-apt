<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        // Las cuentas normales existentes pasan a ser clientes. El observador
        // es una visita pública y, por lo tanto, no se guarda en MongoDB.
        User::where('role', 'user')->update(['role' => 'buyer', 'updated_at' => now()]);

        Schema::table('users', function (Blueprint $collection): void {
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
        Schema::table('users', function (Blueprint $collection): void {
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['name', 'email', 'password', 'role', 'status', 'created_at', 'updated_at'],
                'properties' => [
                    'name' => ['bsonType' => 'string', 'minLength' => 2, 'maxLength' => 100],
                    'email' => ['bsonType' => 'string', 'maxLength' => 150],
                    'password' => ['bsonType' => 'string'],
                    'role' => ['enum' => ['admin', 'buyer', 'user']],
                    'status' => ['enum' => ['active', 'disabled']],
                    'email_verified_at' => ['bsonType' => ['date', 'null']],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ]);
        });
    }
};
