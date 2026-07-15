<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::create('cache', function (Blueprint $collection): void {
            $collection->unique('key');
            $collection->index('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $collection): void {
            $collection->unique('key');
            $collection->index('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
