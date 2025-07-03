<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DeliveryOptionController;
use App\Http\Controllers\Api\AddressController;


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

// Public Delivery Options (Customers/Guests can view active options)
Route::get('delivery-options', [DeliveryOptionController::class, 'index']);
Route::get('delivery-options/{deliveryOption}', [DeliveryOptionController::class, 'show']);


// Paystack Webhook Route (Public but secured by signature verification)
Route::post('/paystack/webhook', [OrderController::class, 'handlePaystackWebhook']);


// --- Protected Routes (Require API Token) ---

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Customer profile management routes
    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers/{customer}', [CustomerController::class, 'show']);
    Route::put('customers/{customer}', [CustomerController::class, 'update']);
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
});

// Cart Routes (Authenticated & Guest Accessible)
// These routes handle logic for both logged-in users and guests
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']); // Get user's or guest's cart
    Route::post('/add', [CartController::class, 'store']); // Add item to cart
    Route::put('/update-item/{item}', [CartController::class, 'update']); // Update quantity of specific item
    Route::delete('/remove-item/{item}', [CartController::class, 'destroy']); // Remove specific item
    Route::post('/clear', [CartController::class, 'clear']); // Clear all items from cart

});

// Order Management (Customers can create/view their own orders)
    Route::post('orders', [OrderController::class, 'store']); // Create a new order
    Route::get('orders', [OrderController::class, 'index']); // View own orders
    Route::get('orders/{order}', [OrderController::class, 'show']); // View specific order

// Address Management (Customer specific)
    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::get('addresses/{address}', [AddressController::class, 'show']);
    Route::put('addresses/{address}', [AddressController::class, 'update']);
    Route::patch('addresses/{address}', [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);

// --- Admin Routes (Require Admin Role AND API Token) ---
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Admin dashboard route
    Route::get('/dashboard', function () {
        return response()->json(['message' => 'Welcome to the Admin Dashboard!'], 200);
    });

    // Category Management Routes (Admin Only for CUD operations)
    // 'index' and 'show' are now handled by the public routes above.
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);
    Route::patch('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

    // Product Management Routes (Admin Only for CUD operations)
    // 'index' and 'show' are now handled by the public routes above.
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::patch('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);

    // Order Management (Admin-only actions: update, delete, view all)
    Route::get('orders', [OrderController::class, 'adminIndex']); // View all orders (Admin)
    Route::get('orders/{order}/details', [OrderController::class, 'adminShow']); // View specific order details (Admin)
    // Admin can update order status, tracking etc
    // This is useful for managing orders, updating statuses, etc.
    Route::put('orders/{order}', [OrderController::class, 'update']); // Update order status, tracking etc.
    // Note: Using PUT for full updates, PATCH can be used for partial updates if needed
    Route::patch('orders/{order}', [OrderController::class, 'update']);
    Route::delete('orders/{order}', [OrderController::class, 'destroy']); // Delete an order

    // Delivery Option Management (Admin only)
    Route::post('delivery-options', [DeliveryOptionController::class, 'store']);
    Route::get('delivery-options', [DeliveryOptionController::class, 'index']); // Admin can view all delivery options
    Route::get('delivery-options/{deliveryOption}', [DeliveryOptionController::class, 'show']); // Admin can view any delivery option
    Route::put('delivery-options/{deliveryOption}', [DeliveryOptionController::class, 'update']);
    Route::patch('delivery-options/{deliveryOption}', [DeliveryOptionController::class, 'update']);
    Route::delete('delivery-options/{deliveryOption}', [DeliveryOptionController::class, 'destroy']);

});