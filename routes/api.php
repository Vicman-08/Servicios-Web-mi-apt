<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Acciones públicas.
Route::post('login', [UserController::class, 'login']);
Route::post('register', [UserController::class, 'register']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [UserController::class, 'me']);
    Route::post('logout', [UserController::class, 'logout']);

    // Compradores y administradores pueden crear, consultar y cancelar compras.
    Route::get('orders', [OrderController::class, 'index'])->can('buyer-access');
    Route::post('orders', [OrderController::class, 'store'])->can('buyer-access');
    Route::get('orders/{order}', [OrderController::class, 'show'])->can('buyer-access');
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])->can('buyer-access');

    // CRUD de productos exclusivo del administrador.
    Route::post('products', [ProductController::class, 'store'])->can('admin-access');
    Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->can('admin-access');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->can('admin-access');

    // CRUD de usuarios exclusivo del administrador.
    Route::get('users', [UserController::class, 'index'])->can('admin-access');
    Route::post('users', [UserController::class, 'store'])->can('admin-access');
    Route::get('users/{user}', [UserController::class, 'show'])->can('admin-access');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->can('admin-access');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->can('admin-access');
});
