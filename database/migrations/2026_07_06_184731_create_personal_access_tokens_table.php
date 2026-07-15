<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $collection): void {
            $collection->unique('token');
            $collection->index(['tokenable_type' => 1, 'tokenable_id' => 1]);
            $collection->index('expires_at', options: ['sparse' => true, 'expireAfterSeconds' => 0]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
