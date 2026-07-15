<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User; // Importar Gate
use Illuminate\Support\Facades\Gate;                 // Importar el Modelo User
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Regla 1: Acceso de Administrador (Solo si su rol es 'admin')
        Gate::define('admin-access', function (User $user) {
            return ($user->status ?? 'active') === 'active' && $user->role === 'admin';
        });

        // Regla 2: Acceso de Comprador (Puede ser 'buyer' o 'admin')
        Gate::define('buyer-access', function (User $user) {
            return ($user->status ?? 'active') === 'active'
                && ($user->role === 'admin' || $user->role === 'buyer');
        });
    }
}
