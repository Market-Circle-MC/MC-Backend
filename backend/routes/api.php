<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerController;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});


// Public routes for authentication

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);


// Protected routes (require API token)

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {

        return $request->user();
 });

Route::post('/logout', [AuthController::class, 'logout']);

// Customer profile management routes
Route::post('/customers', [CustomerController::class, 'store']);

Route::put('/customers/{customer}', [CustomerController::class, 'update']);

Route::get('/customers', [CustomerController::class, 'index']);

Route::get('/customers/{customer}', [CustomerController::class, 'show']);

Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
});


// Admin routes (require admin role) and API token
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Admin dashboard route
    Route::get('/admin/dashboard', function () {
        return response()->json(['message' => 'Welcome to the Admin Dashboard!'], 200);
    });

    // Category Management Routes (Admin Only)
    Route::apiResource('categories', CategoryController::class);

    // Product Management Routes (Admin Only)
    Route::apiResource('products', ProductController::class);

    // Add more admin-specific routes as needed
    
});