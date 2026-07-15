<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Estas cuentas eran únicamente perfiles de demostración anteriores.
        User::whereIn('email', ['buyer@subarg.test', 'user@subarg.test'])->delete();

        User::updateOrCreate(
            ['email' => 'admin@subarg.test'],
            [
                'name' => 'Administrador Demo',
                'password' => 'password123',
                'role' => 'admin',
                'status' => 'active',
            ],
        );
    }
}
