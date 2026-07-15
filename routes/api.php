<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
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
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [UserController::class, 'me'])->name('me.show');
        Route::patch('me', [UserController::class, 'updateProfile'])->name('me.update');
        Route::post('auth/logout', [UserController::class, 'logout'])->name('auth.logout');

        // Clientes y administradores pueden crear, consultar y cancelar compras.
        Route::get('orders', [OrderController::class, 'index'])->can('buyer-access')->name('orders.index');
        Route::post('orders', [OrderController::class, 'store'])->can('buyer-access')->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->can('buyer-access')->name('orders.show');
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->can('buyer-access')->name('orders.destroy');

        Route::prefix('admin')->name('admin.')->middleware('can:admin-access')->group(function (): void {
            Route::get('dashboard', DashboardController::class)->name('dashboard');

            // CRUD de categorías exclusivo del administrador.
            Route::get('categories', [CategoryController::class, 'adminIndex'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('categories/{category}', [CategoryController::class, 'adminShow'])->name('categories.show');
            Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            // CRUD de productos exclusivo del administrador.
            Route::get('products', [ProductController::class, 'adminIndex'])->name('products.index');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::get('products/{product}', [ProductController::class, 'adminShow'])->name('products.show');
            Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

            // CRUD de usuarios exclusivo del administrador.
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            // Gestión administrativa de compras.
            Route::get('orders', [OrderController::class, 'adminIndex'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');

            // Historial y ajustes manuales de inventario.
            Route::get('inventory-movements', [InventoryController::class, 'index'])->name('inventory.index');
            Route::post('inventory-adjustments', [InventoryController::class, 'adjust'])->name('inventory.adjust');
            Route::get('inventory-movements/{inventoryMovement}', [InventoryController::class, 'show'])->name('inventory.show');
        });
    });
});
