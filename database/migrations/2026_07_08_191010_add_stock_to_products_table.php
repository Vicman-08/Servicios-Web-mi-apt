<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $connection = 'mongodb';

    // El campo stock ya forma parte de la colección products en MongoDB.
    public function up(): void {}

    public function down(): void {}
};
