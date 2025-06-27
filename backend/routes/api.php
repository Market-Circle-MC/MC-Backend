<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CartController;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

// --- Publicly Accessible Routes (No Authentication Required) ---

// Public Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public Product Routes (Anyone can view products)
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

// Public Category Routes (Anyone can view categories)
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);

// Cart Routes (Authenticated & Guest Accessible)
// These routes handle logic for both logged-in users and guests
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']); // Get user's or guest's cart
    Route::post('/add', [CartController::class, 'store']); // Add item to cart
    Route::put('/update-item/{item}', [CartController::class, 'update']); // Update quantity of specific item
    Route::delete('/remove-item/{item}', [CartController::class, 'destroy']); // Remove specific item
    Route::post('/clear', [CartController::class, 'clear']); // Clear all items from cart

});
// --- Protected Routes (Require API Token) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Customer profile management routes
    // (Authorization logic for who can store/update/view which profile should be in CustomerController/Request)
    Route::apiResource('customers', CustomerController::class); // Assumes CustomerController handles internal auth
});


// --- Admin Routes (Require Admin Role AND API Token) ---
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Admin dashboard route
    Route::get('/admin/dashboard', function () {
        return response()->json(['message' => 'Welcome to the Admin Dashboard!'], 200);
    });

    // Category Management Routes (Admin Only for CUD operations)
    // 'index' and 'show' are now handled by the public routes above.
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);

    // Product Management Routes (Admin Only for CUD operations)
    // 'index' and 'show' are now handled by the public routes above.
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);

});