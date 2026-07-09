<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; // Importar Gate
use App\Models\User;                 // Importar el Modelo User

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Regla 1: Acceso de Administrador (Solo si su rol es 'admin')
        Gate::define('admin-access', function (User $user) {
            return $user->role === 'admin';
        });

        // Regla 2: Acceso de Comprador (Puede ser 'buyer' o 'admin')
        Gate::define('buyer-access', function (User $user) {
            return $user->role === 'admin' || $user->role === 'buyer';
        });
    }
}
