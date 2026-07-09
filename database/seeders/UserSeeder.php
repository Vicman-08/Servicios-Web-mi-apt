<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear el usuario Administrador
        User::create([
            'name' => 'Admin Principal',
            'email' => 'admin@proyecto.com',
            'password' => Hash::make('password123'),
            'role' => 'admin'
        ]);

        // 2. Crear un Usuario Registrado normal
        User::create([
            'name' => 'Cliente Frecuente',
            'email' => 'cliente@proyecto.com',
            'password' => Hash::make('password123'),
            'role' => 'user'
        ]);
    }
}

