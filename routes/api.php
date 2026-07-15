<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Autenticación y catálogo público para observadores.
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('login', [UserController::class, 'login'])->name('login');
        Route::post('register', [UserController::class, 'register'])->name('register');
    });

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [UserController::class, 'me'])->name('me.show');
        Route::patch('me', [UserController::class, 'updateProfile'])->name('me.update');
        Route::post('auth/logout', [UserController::class, 'logout'])->name('auth.logout');

        // Clientes y administradores pueden crear, consultar y cancelar compras.
        Route::get('orders', [OrderController::class, 'index'])->can('buyer-access')->name('orders.index');
        Route::post('orders', [OrderController::class, 'store'])->can('buyer-access')->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->can('buyer-access')->name('orders.show');
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->can('buyer-access')->name('orders.destroy');

        Route::prefix('admin')->name('admin.')->group(function (): void {
            // CRUD de productos exclusivo del administrador.
            Route::post('products', [ProductController::class, 'store'])->can('admin-access')->name('products.store');
            Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->can('admin-access')->name('products.update');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->can('admin-access')->name('products.destroy');

            // CRUD de usuarios exclusivo del administrador.
            Route::get('users', [UserController::class, 'index'])->can('admin-access')->name('users.index');
            Route::post('users', [UserController::class, 'store'])->can('admin-access')->name('users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->can('admin-access')->name('users.show');
            Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->can('admin-access')->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->can('admin-access')->name('users.destroy');
        });
    });
});
