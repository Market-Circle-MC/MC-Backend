<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // Import Str for UUID generation

class CartController extends Controller
{
    /**
     * Display the user's cart (authenticated or guest).
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $guestCartId = $request->header('X-Guest-Cart-Id') ?? $request->query('guest_cart_id'); // Get from header or query param

        $cart = null;

        if ($user) {
            // Priority 1: Authenticated user's active cart
            $cart = Cart::with(['items.product.images', 'items.product.category'])
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->first();
        } elseif ($guestCartId) {
            // Priority 2: Guest user's cart identified by guestCartId
            $cart = Cart::with(['items.product.images', 'items.product.category'])
                        ->where('id', $guestCartId)
                        ->whereNull('user_id') // Ensure it's a guest cart
                        ->where('status', 'active')
                        ->first();
        }

        if (!$cart) {
            // If no cart found, create a new one. For guests, user_id will be NULL.
            $cartData = ['status' => 'active'];
            if ($user) {
                $cartData['user_id'] = $user->id;
            }

            $cart = Cart::create($cartData);
            $cart->load(['items.product.images', 'items.product.category']); // Eager load newly created relations

            $responseMessage = $user ? 'New cart created and retrieved successfully for authenticated user.' : 'New guest cart created and retrieved successfully.';
            $statusCode = 201; // Created

            // For guest users, return the new cart ID so the frontend can store it
            if (!$user) {
                return response()->json([
                    'message' => $responseMessage,
                    'data' => $cart,
                    'guest_cart_id' => $cart->id, // Return the ID for frontend to store
                ], $statusCode);
            }

            return response()->json([
                'message' => $responseMessage,
                'data' => $cart,
            ], $statusCode);
        }

        $responseMessage = $user ? 'Cart retrieved successfully for authenticated user.' : 'Guest cart retrieved successfully.';
        return response()->json([
            'message' => $responseMessage,
            'data' => $cart,
            'guest_cart_id' => $user ? null : $cart->id, // Always return guest_cart_id if it's a guest cart
        ], 200);
    }

    /**
     * Add a product to the cart or update its quantity if already exists.
     *
     * @param  StoreCartItemRequest  $request
     * @return JsonResponse
     */
    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $user = Auth::user();
        $guestCartId = $request->header('X-Guest-Cart-Id') ?? $request->input('guest_cart_id'); // Get from header or body

        $productId = $request->product_id;
        $quantity = $request->quantity;

        // Fetch the product to get its current details
        $product = Product::find($productId);

        // Basic check for product availability and stock
        if (!$product || !$product->is_active || $product->stock_quantity < $quantity) {
            return response()->json([
                'message' => 'Product is not available or requested quantity is out of stock.',
                'errors' => [
                    'quantity' => ['The requested quantity exceeds the available stock for this product.'],
                ]
            ], 422);
        }

        $cart = null;
        if ($user) {
            // Find or create the authenticated user's active cart
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id, 'status' => 'active'],
                ['status' => 'active']
            );
        } elseif ($guestCartId) {
            // Find the guest cart if identified, or create a new one
            $cart = Cart::firstOrCreate(
                ['id' => $guestCartId, 'user_id' => null, 'status' => 'active'],
                ['status' => 'active']
            );
            // If the provided guestCartId exists but is linked to a user, it's invalid for guest use
            if ($cart->user_id !== null) {
                 return response()->json([
                    'message' => 'Invalid guest cart ID provided. Please provide a valid guest cart ID or authenticate.',
                    'data' => null,
                ], 400);
            }
        } else {
            // No user and no guest_cart_id, create a new guest cart
            $cart = Cart::create(['user_id' => null, 'status' => 'active']);
            $guestCartId = $cart->id; // Get the ID of the newly created guest cart
        }


        DB::beginTransaction();
        try {
            // Check if the item already exists in the cart
            $cartItem = $cart->items()->where('product_id', $productId)->first();

            if ($cartItem) {
                // If item exists, update quantity
                $newQuantity = $cartItem->quantity + $quantity;

                // Re-check stock with the combined quantity
                if ($product->stock_quantity < $newQuantity) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Cannot add more. Combined quantity exceeds available stock.',
                        'errors' => [
                            'quantity' => ['Adding this quantity would exceed the available stock.'],
                        ]
                    ], 422);
                }

                $cartItem->update([
                    'quantity' => $newQuantity,
                    // Recalculate line_item_total via saving event in CartItem model
                ]);
                $message = 'Product quantity updated in cart successfully.';
            } else {
                // If item does not exist, add new cart item
                if ($quantity < $product->min_order_quantity) {
                    $quantity = $product->min_order_quantity; // Enforce minimum order quantity
                }

                // Final stock check before creating new item
                if ($product->stock_quantity < $quantity) {
                     DB::rollBack();
                     return response()->json([
                         'message' => 'Requested quantity exceeds available stock for this product.',
                         'errors' => [
                             'quantity' => ['The requested quantity exceeds the available stock for this product.'],
                         ]
                     ], 422);
                }

                $cartItem = $cart->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price_per_unit_at_addition' => $product->current_price, // Use product accessor for current price
                    'unit_of_measure_at_addition' => $product->unit_of_measure,
                    // line_item_total will be calculated automatically by model event
                ]);
                $message = 'Product added to cart successfully.';
            }

            DB::commit();

            // Reload cart to get updated totals and relationships
            $cart->load(['items.product.images', 'items.product.category']);

            return response()->json([
                'message' => $message,
                'data' => $cart,
                'guest_cart_id' => $user ? null : $cart->id, // Only return if it's a guest cart
            ], $cartItem->wasRecentlyCreated ? 201 : 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Cart add/update error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to add/update item in cart.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the quantity of a specific item in the cart.
     *
     * @param  UpdateCartItemRequest  $request
     * @param  CartItem  $item  (Route model binding: 'item' refers to CartItem)
     * @return JsonResponse
     */
    public function update(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        $user = Auth::user();
        $guestCartId = $request->header('X-Guest-Cart-Id') ?? $request->input('guest_cart_id');
        /**if ($user) {
            dd([
                'cart_item_id' => $item->id,
                'cart_id_from_item' => $item->cart->id ?? 'N/A (cart relation missing)',
                'user_id_from_item_cart' => $item->cart->user_id ?? 'N/A (user_id on cart relation missing)',
                'authenticated_user_id' => $user->id,
                'is_user_authenticated' => Auth::check(),
                'acting_as_guard' => Auth::getDefaultDriver(), // Should be 'sanctum' in tests
                'item_cart_is_null' => ($item->cart === null),
                'item_cart_user_id_type' => gettype($item->cart->user_id ?? null),
                'authenticated_user_id_type' => gettype($user->id),
                    ]);
                }*/

        // Authorization: Ensure the user owns this cart item's cart or it's a guest cart with matching ID
        if ($user) {
            // Authenticated user: must own the cart
            if ($item->cart->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized to update this cart item.',
                    'data' => null,
                ], 403);
            }
        } elseif ($guestCartId) {
            // Guest user: cart must be a guest cart and the ID must match
            if ($item->cart->id != $guestCartId || $item->cart->user_id !== null) {
                return response()->json([
                    'message' => 'Unauthorized to update this cart item or invalid guest cart ID.',
                    'data' => null,
                ], 403);
            }
        } else {
            // Neither authenticated nor guest cart ID provided
            return response()->json([
                'message' => 'Authentication required or guest cart ID missing.',
                'data' => null,
            ], 401);
        }

        $newQuantity = $request->quantity;
        $product = $item->product; // Get the associated product

        // Additional stock check with the new quantity
        if (!$product || !$product->is_active || $product->stock_quantity < $newQuantity) {
            return response()->json([
                'message' => 'Requested quantity exceeds available stock for this product.',
                'errors' => [
                    'quantity' => ['The requested quantity exceeds the available stock for this product.'],
                ]
            ], 422);
        }

        DB::beginTransaction();
        try {
            $item->update([
                'quantity' => $newQuantity,
                // price_per_unit_at_addition and unit_of_measure_at_addition should not change on quantity update
                // line_item_total will be recalculated by model event
            ]);

            DB::commit();

            // Reload cart to get updated totals and relationships
            $cart = $item->cart->load(['items.product.images', 'items.product.category']);

            return response()->json([
                'message' => 'Cart item quantity updated successfully.',
                'data' => $cart,
                'guest_cart_id' => $user ? null : $cart->id, // Only return if it's a guest cart
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Cart item update error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to update cart item.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a specific item from the cart.
     *
     * @param  CartItem  $item  (Route model binding: 'item' refers to CartItem)
     * @return JsonResponse
     */
    public function destroy(CartItem $item): JsonResponse
    {
        $user = Auth::user();
        $guestCartId = request()->header('X-Guest-Cart-Id') ?? request()->query('guest_cart_id');

        // Authorization: Ensure the user owns this cart item's cart or it's a guest cart with matching ID
        if ($user) {
            // Authenticated user: must own the cart
            if ($item->cart->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized to remove this cart item.',
                    'data' => null,
                ], 403);
            }
        } elseif ($guestCartId) {
            // Guest user: cart must be a guest cart and the ID must match
            if ($item->cart->id != $guestCartId || $item->cart->user_id !== null) {
                return response()->json([
                    'message' => 'Unauthorized to remove this cart item or invalid guest cart ID.',
                    'data' => null,
                ], 403);
            }
        } else {
            // Neither authenticated nor guest cart ID provided
            return response()->json([
                'message' => 'Authentication required or guest cart ID missing.',
                'data' => null,
            ], 401);
        }

        DB::beginTransaction();
        try {
            $cart = $item->cart; // Get the cart before deleting the item
            $item->delete();

            // If the cart becomes empty after deleting the last item, you might want to delete the cart as well.
            if ($cart->items()->count() === 0) {
                $cart->delete();
                $message = 'Cart item removed and cart is now empty and deleted.';
                $data = null;
            } else {
                $message = 'Cart item removed successfully.';
                // Reload cart to get updated totals and relationships
                $cart->load(['items.product.images', 'items.product.category']);
                $data = $cart;
            }

            DB::commit();

            return response()->json([
                'message' => $message,
                'data' => $data,
                'guest_cart_id' => $user ? null : $cart->id, // Only return if it's a guest cart
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Cart item delete error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to remove cart item.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all items from the user's cart (authenticated or guest).
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function clear(Request $request): JsonResponse
    {
        $user = Auth::user();
        $guestCartId = $request->header('X-Guest-Cart-Id') ?? $request->query('guest_cart_id');

        $cart = null;

        if ($user) {
            $cart = Cart::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->first();
        } elseif ($guestCartId) {
            $cart = Cart::where('id', $guestCartId)
                        ->whereNull('user_id')
                        ->where('status', 'active')
                        ->first();
             // If the provided guestCartId exists but is linked to a user, it's invalid for guest use
            if ($cart && $cart->user_id !== null) {
                 return response()->json([
                    'message' => 'Invalid guest cart ID provided. Please provide a valid guest cart ID or authenticate.',
                    'data' => null,
                ], 400);
            }
        } else {
            return response()->json([
                'message' => 'Authentication required or guest cart ID missing to clear cart.',
                'data' => null,
            ], 401);
        }

        if (!$cart) {
            return response()->json([
                'message' => 'No active cart found to clear.',
                'data' => null,
            ], 404);
        }

        DB::beginTransaction();
        try {
            $cart->items()->delete(); // Delete all associated cart items
            $cart->delete(); // Delete the cart itself

            DB::commit();

            return response()->json([
                'message' => 'Cart cleared successfully.',
                'data' => null,
                'guest_cart_id' => $user ? null : null, // No guest cart ID needed after clearing
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Cart clear error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to clear cart.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
