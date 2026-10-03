<?php

use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Customer
    Route::get('/my-orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);

    // Admin: full product CRUD. Manager: stock updates only (enforced in the controller).
    Route::middleware('role:admin')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    });
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('role:admin,manager');

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('role:admin,manager');

    // Order fulfilment is shared with managers; payment status stays admin-only (enforced in the controller).
    Route::middleware('role:admin,manager')->prefix('admin')->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/export', [AdminOrderController::class, 'export']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::put('/orders/{order}', [AdminOrderController::class, 'update']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    });
});
