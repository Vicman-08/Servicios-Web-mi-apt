<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $collection): void {
            $collection->index('queue');
            $collection->index('available_at');
        });

        Schema::create('job_batches', function (Blueprint $collection): void {
            $collection->unique('id');
        });

        Schema::create('failed_jobs', function (Blueprint $collection): void {
            $collection->unique('uuid');
            $collection->index(['connection' => 1, 'queue' => 1, 'failed_at' => -1]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
