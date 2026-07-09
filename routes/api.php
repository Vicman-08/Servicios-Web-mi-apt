<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;

// ==========================================
// RUTAS PÚBLICAS (Cualquiera puede entrar)
// ==========================================
Route::post('login', [UserController::class, 'login']);
Route::post('users', [UserController::class, 'store']); // Registro

// El Usuario Normal solo puede hacer GET a los productos
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);


// ==========================================
// RUTAS PROTEGIDAS (Requieren Token de Postman)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // --- ZONA EXCLUSIVA DEL ADMINISTRADOR ---
    Route::get('users', [UserController::class, 'index'])->can('admin-access');
    Route::get('users/{user}', [UserController::class, 'show'])->can('admin-access');
    Route::put('users/{user}', [UserController::class, 'update'])->can('admin-access');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->can('admin-access');

    Route::post('products', [ProductController::class, 'store'])->can('admin-access');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->can('admin-access');

    // --- ZONA DE COMPRAS ---
    // Tanto el Admin como el Comprador pueden modificar productos (simular compra)
    Route::put('products/{product}', [ProductController::class, 'update'])->can('buyer-access');
});
