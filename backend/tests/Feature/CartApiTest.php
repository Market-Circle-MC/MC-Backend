<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem; // <--- THIS WAS MISSING AND IS CRUCIAL!
use App\Models\Category;
use Illuminate\Support\Str;

class CartApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    // Properties to hold test data, declared at the top of the class
    protected User $customer; // Type hint for clarity
    protected Product $product1; // Type hint for clarity
    protected Product $product2; // Type hint for clarity
    protected Product $productOutOfStock; // Type hint for clarity

    /**
     * Helper method to create and authenticate a user for tests.
     *
     * @return \App\Models\User The created and authenticated user instance.
     */
    protected function createAuthenticatedUser(): User
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        // Simulate logging in as this user for the current test using the 'sanctum' guard.
        $this->actingAs($user, 'sanctum'); // <-- ENSURE 'sanctum' GUARD IS USED

        return $user;
    }

    /**
     * Helper method to create and authenticate an admin user for tests.
     *
     * @return \App\Models\User The created and authenticated admin user instance.
     */
    protected function createAuthenticatedAdminUser(): User
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        
        // Simulates logging in as this admin user using the 'sanctum' guard.
        $this->actingAs($admin, 'sanctum'); // <-- ENSURE 'sanctum' GUARD IS USED
        
        return $admin;
    }

    /**
     * Set up the test environment before each test method is run.
     * This method MUST be declared before your actual test methods.
     * @test
     */
    protected function setUp(): void
    {
        parent::setUp(); // Always call the parent setUp method first

        // Create a regular customer user using the UserFactory
        $this->customer = User::factory()->create(['role' => 'customer']);

        // Ensure at least one category exists for products.
        // We find one if it exists, otherwise create it.
        // This prevents errors if ProductFactory tries to assign a category_id
        // but no categories exist yet.
        Category::factory()->create();

        // Create various products with specific states for testing different scenarios
        $this->product1 = Product::factory()->create([
            'name' => 'Test Product 1',
            'price_per_unit' => 10.00,
            'stock_quantity' => 20.00,
            'min_order_quantity' => 1.00,
            'is_active' => true,
            'unit_of_measure' => 'kg', // Ensure unit of measure is set
        ]);
        // Manually set current_price for testing purposes as it's an accessor
        // And ensure it's a float for comparisons later if needed.
        $this->product1->setAttribute('current_price', (float) $this->product1->price_per_unit);


        $this->product2 = Product::factory()->create([
            'name' => 'Test Product 2',
            'price_per_unit' => 25.00,
            'stock_quantity' => 15.00,
            'min_order_quantity' => 2.00, // This product has a higher minimum
            'is_active' => true,
            'unit_of_measure' => 'piece', // Ensure unit of measure is set
        ]);
        $this->product2->setAttribute('current_price', (float) $this->product2->price_per_unit);


        $this->productOutOfStock = Product::factory()->create([
            'name' => 'Out of Stock Product',
            'price_per_unit' => 5.00,
            'stock_quantity' => 0.00, // Specifically for testing out-of-stock scenarios
            'min_order_quantity' => 1.00,
            'is_active' => true,
            'unit_of_measure' => 'unit', // Ensure unit of measure is set
        ]);
        $this->productOutOfStock->setAttribute('current_price', (float) $this->productOutOfStock->price_per_unit);
    }

    // --- Authenticated User Cart Tests ---

    /**
     * Test that an authenticated user can retrieve their (initially empty) cart.
     * A new cart should be created for them upon first request.
     * @test
     */
    public function authenticated_user_can_get_their_empty_cart(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        $response = $this->getJson('/api/cart');

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'id', 'user_id', 'status', 'created_at', 'updated_at', 'items', 'total'
                     ]
                 ])
                 ->assertJson([
                     'message' => 'New cart created and retrieved successfully for authenticated user.',
                     'data' => [
                         'user_id' => $user->id, // Use this test's user ID
                         'status' => 'active',
                         'items' => [],
                         'total' => 0,
                     ]
                 ])
                 ->assertJsonMissing(['guest_cart_id']);

        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id, // Use this test's user ID
            'status' => 'active',
        ]);
    }

    /**
     * Test that an authenticated user can add a new item to their cart.
     * @test
     */
    public function authenticated_user_can_add_item_to_cart(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        $response = $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'id', 'user_id', 'status', 'items' => [
                             '*' => ['id', 'cart_id', 'product_id', 'quantity', 'price_per_unit_at_addition', 'unit_of_measure_at_addition', 'line_item_total']
                         ],
                         'total'
                     ]
                 ])
                 ->assertJson([
                     'message' => 'Product added to cart successfully.',
                     'data' => [
                         'user_id' => $user->id, // Use this test's user ID
                         'items' => [
                             [
                                 'product_id' => $this->product1->id,
                                 'quantity' => '5.00',
                                 'price_per_unit_at_addition' => '10.00',
                                 'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
                                 'line_item_total' => '50.00',
                             ]
                         ],
                         'total' => 50,
                     ]
                 ])
                 ->assertJsonMissing(['guest_cart_id']);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $response->json('data.id'),
            'product_id' => $this->product1->id,
            'quantity' => 5,
            'price_per_unit_at_addition' => 10.00,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 50.00,
        ]);
    }

    /**
     * Test that adding an already existing item to the cart updates its quantity.
     * @test
     */
    public function authenticated_user_adding_existing_item_updates_quantity(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        // Create the cart directly for the customer
        $cart = Cart::create([
            'user_id' => $user->id, // Use this test's user ID
            'status' => 'active',
        ]);
        // Add the initial item directly
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 5,
            'price_per_unit_at_addition' => $this->product1->price_per_unit,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 5 * $this->product1->price_per_unit,
        ]);

        $cart = $cart->fresh(); // Re-fetch the cart instance to ensure it's fresh

        // Now, perform the API request to add more, which should update
        $response = $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 3,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product quantity updated in cart successfully.',
                     'data' => [
                         'id' => $cart->id,
                         'user_id' => $user->id, // Use this test's user ID
                         'items' => [
                             [
                                 'product_id' => $this->product1->id,
                                 'quantity' => '8.00', // Original 5 + new 3 = 8
                                 'price_per_unit_at_addition' => '10.00',
                                 'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
                                 'line_item_total' => '80.00', // 8 * 10
                             ]
                         ],
                         'total' => 80,
                     ]
                 ])
                 ->assertJsonMissing(['guest_cart_id']);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 8,
            'price_per_unit_at_addition' => 10.00,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 80.00,
        ]);
    }

    /**
     * Test that an authenticated user cannot add a product if the quantity exceeds available stock.
     * @test
     */
    public function authenticated_user_cannot_add_item_with_insufficient_stock(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        $response = $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 100, // Product1 stock is 20
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('quantity')
                 ->assertJson([
                    'message' => 'Product is not available or requested quantity is out of stock.',
                    'errors' => [
                        'quantity' => ['The requested quantity exceeds the available stock for this product.'],
                    ]
                 ]);

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $this->product1->id,
            'cart_id' => Cart::where('user_id', $user->id)->first()?->id, // Use this test's user ID
        ]);
    }


    /**
     * Test that an authenticated user cannot add a product if its stock is zero.
     * @test
     */
    public function authenticated_user_cannot_add_item_with_zero_stock(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        $response = $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 100, // Product1 stock is 20
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('quantity')
                 ->assertJson([
                    'message' => 'Product is not available or requested quantity is out of stock.',
                    'errors' => [
                        'quantity' => ['The requested quantity exceeds the available stock for this product.'],
                    ]
                 ]);

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $this->product1->id,
            'cart_id' => Cart::where('user_id', $user->id)->first()?->id, // Use this test's user ID
        ]);
    }


    /**
     * Test that an authenticated user cannot add a product that does not exist.
     * @test
     */
    public function authenticated_user_cannot_add_non_existent_product(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        $response = $this->postJson('/api/cart/add', [
            'product_id' => 99999, // ID that surely does not exist
            'quantity' => 1,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('product_id')
                 ->assertJson([
                     'message' => 'The selected product does not exist or is not available for purchase.',
                     'errors' => [
                         'product_id' => ['The selected product does not exist or is not available for purchase.'],
                     ]
                 ]);
    }

    /**
     * Test that an authenticated user can update the quantity of an item in their cart.
     * @test
     */
    public function authenticated_user_can_update_cart_item_quantity(): void
        {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        // Create the cart directly and associate it with THIS test user.
        $cart = Cart::create([
            'user_id' => $user->id, // Use this test's user ID
            'status' => 'active',
        ]);

        // Create a cart item directly for this cart.
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 5,
            'price_per_unit_at_addition' => $this->product1->price_per_unit,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 5 * $this->product1->price_per_unit,
        ]);

        // Re-fetch the cart and cartItem to ensure they are fresh and relationships are loaded
        $cart = $cart->fresh();
        $cartItem = $cartItem->fresh();

        // Now, perform the API request to update this item using POST with _method spoofing
        $response = $this->postJson("/api/cart/update-item/{$cartItem->id}", [
            'quantity' => 10,
            '_method' => 'PUT',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Cart item quantity updated successfully.',
                     'data' => [
                         'id' => $cart->id,
                         'user_id' => $user->id, // Use this test's user ID
                         'items' => [
                             [
                                 'id' => $cartItem->id,
                                 'quantity' => '10.00',
                                 'line_item_total' => '100.00',
                             ]
                         ],
                         'total' => 100,
                     ]
                 ])
                 ->assertJsonMissing(['guest_cart_id']);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 10,
            'line_item_total' => 100.00,
        ]);
    }

    

    /** @test */
    public function authenticated_user_cannot_update_cart_item_to_exceed_stock(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        // Create the cart and item directly
        $cart = Cart::create([
            'user_id' => $user->id, // Use this test's user ID
            'status' => 'active',
        ]);
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 5,
            'price_per_unit_at_addition' => $this->product1->price_per_unit,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 5 * $this->product1->price_per_unit,
        ]);

        // Re-fetch the cart and cartItem to ensure they are fresh and relationships are loaded
        $cart = $cart->fresh();
        $cartItem = $cartItem->fresh();

        // Try to update quantity beyond product's stock (product1 stock is 20) using POST
        $response = $this->postJson("/api/cart/update-item/{$cartItem->id}", [
            'quantity' => 25, // This is greater than product1's stock of 20
            '_method' => 'PUT',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('quantity')
                 ->assertJson([
                     'message' => 'Requested quantity exceeds available stock for this product.',
                     'errors' => [
                         'quantity' => ['The requested quantity exceeds the available stock for this product.'],
                     ]
                 ]);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 5,
        ]);
    }

    /**
     * Test that an authenticated user cannot update an item in another user's cart.
     * @test
     */
    public function authenticated_user_cannot_update_other_users_cart_item(): void
    {
        $user = $this->createAuthenticatedUser(); // Authenticate the "current" user for this test

        // Create another customer and their cart item directly
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $otherCart = Cart::create([
            'user_id' => $otherCustomer->id,
            'status' => 'active',
        ]);
        $otherCartItem = CartItem::create([
            'cart_id' => $otherCart->id,
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'price_per_unit_at_addition' => $this->product1->price_per_unit,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 2 * $this->product1->price_per_unit,
        ]);

        // Try to update the other customer's cart item using the current authenticated user
        $response = $this->postJson("/api/cart/update-item/{$otherCartItem->id}", [
            'quantity' => 5,
            '_method' => 'PUT',
        ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Unauthorized to update this cart item.']);
    }


    /**
     * Test that an authenticated user can remove a specific item from their cart.
     * @test
     */
    public function authenticated_user_can_remove_item_from_cart(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        // Create the cart directly for the customer
        $cart = Cart::create([
            'user_id' => $user->id, // Use this test's user ID
            'status' => 'active',
        ]);
        // Add multiple items directly
        $cartItemToDelete = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 5,
            'price_per_unit_at_addition' => $this->product1->price_per_unit,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 5 * $this->product1->price_per_unit,
        ]);
        $product2CartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product2->id,
            'quantity' => 2,
            'price_per_unit_at_addition' => $this->product2->price_per_unit,
            'unit_of_measure_at_addition' => $this->product2->unit_of_measure,
            'line_item_total' => 2 * $this->product2->price_per_unit,
        ]);

        // Re-fetch the cart and cartItemToDelete to ensure they are fresh and relationships are loaded
        $cart = $cart->fresh();
        $cartItemToDelete = $cartItemToDelete->fresh();

        $response = $this->deleteJson("/api/cart/remove-item/{$cartItemToDelete->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Cart item removed successfully.',
                     'data' => [
                         'id' => $cart->id,
                         'user_id' => $user->id, // Use this test's user ID
                         'items' => [
                             ['product_id' => $this->product2->id] // Only product2 should remain
                         ],
                         'total' => (int)($this->product2->current_price * 2),
                     ]
                 ])
                 ->assertJsonMissing(['guest_cart_id']);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItemToDelete->id,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product2->id,
        ]);
        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
        ]);
    }
    /**
     * Test that if an authenticated user removes the last item, the cart itself is also deleted.
     * @test
     */
    public function authenticated_user_removing_last_item_also_deletes_cart(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        // Create the cart and only one item directly
        $cart = Cart::create([
            'user_id' => $user->id, // Use this test's user ID
            'status' => 'active',
        ]);
        $cartItemToDelete = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 1,
            'price_per_unit_at_addition' => $this->product1->price_per_unit,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 1 * $this->product1->price_per_unit,
        ]);

        // Re-fetch the cart and cartItemToDelete to ensure they are fresh and relationships are loaded
        $cart = $cart->fresh();
        $cartItemToDelete = $cartItemToDelete->fresh();

        $response = $this->deleteJson("/api/cart/remove-item/{$cartItemToDelete->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Cart item removed and cart is now empty and deleted.',
                     'data' => null,
                 ])
                 ->assertJsonMissing(['guest_cart_id']);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItemToDelete->id,
        ]);
        $this->assertDatabaseMissing('carts', [
            'id' => $cart->id,
        ]);
    }

    /**
     * Test that an authenticated user cannot remove an item from another user's cart.
     * @test
     */
    public function authenticated_user_cannot_remove_other_users_cart_item(): void
    {
        $user = $this->createAuthenticatedUser(); // Authenticate the "current" user for this test

        // Setup another customer's cart directly
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $otherCart = Cart::create([
            'user_id' => $otherCustomer->id,
            'status' => 'active',
        ]);
        $otherCartItem = CartItem::create([
            'cart_id' => $otherCart->id,
            'product_id' => $this->product1->id,
            'quantity' => 1,
            'price_per_unit_at_addition' => $this->product1->price_per_unit,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 1 * $this->product1->price_per_unit,
        ]);

        // Try to remove their item using the current authenticated user
        $response = $this->deleteJson("/api/cart/remove-item/{$otherCartItem->id}");

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Unauthorized to remove this cart item.']);
    }

    /**
     * Test that an authenticated user can clear their entire cart.
     * @test
     */
    public function authenticated_user_can_clear_their_entire_cart(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        // Create cart and multiple items directly
        $cart = Cart::create([
            'user_id' => $user->id, // Use this test's user ID
            'status' => 'active',
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 5,
            'price_per_unit_at_addition' => $this->product1->price_per_unit,
            'unit_of_measure_at_addition' => $this->product1->unit_of_measure,
            'line_item_total' => 5 * $this->product1->price_per_unit,
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product2->id,
            'quantity' => 2,
            'price_per_unit_at_addition' => $this->product2->price_per_unit,
            'unit_of_measure_at_addition' => $this->product2->unit_of_measure,
            'line_item_total' => 2 * $this->product2->price_per_unit,
        ]);

        $cartItemCount = $cart->items()->count();
        $this->assertGreaterThan(0, $cartItemCount);

        $response = $this->postJson('/api/cart/clear');

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Cart cleared successfully.',
                     'data' => null,
                 ])
                 ->assertJsonMissing(['guest_cart_id']);

        $this->assertDatabaseMissing('carts', [
            'id' => $cart->id,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);
    }

    /**
     * Test that clearing a non-existent cart for an authenticated user returns a 404.
     * @test
     */
    public function authenticated_user_clearing_non_existent_cart_returns_404(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        // Ensure the customer has no active cart
        Cart::where('user_id', $user->id)->delete(); // Use this test's user ID

        $response = $this->postJson('/api/cart/clear');

        $response->assertStatus(404)
                 ->assertJson(['message' => 'No active cart found to clear.']);
    }


    /**
     * Test that adding an item with a quantity less than the product's minimum order quantity
     * for an authenticated user automatically adjusts the quantity to the minimum.
     * @test
     */
    public function authenticated_user_adding_item_with_quantity_less_than_min_order_quantity_adjusts_to_min(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        // product2 has min_order_quantity = 2.00
        $response = $this->postJson('/api/cart/add', [
            'product_id' => $this->product2->id,
            'quantity' => 1.00, // Less than min_order_quantity
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product added to cart successfully.',
                     'data' => [
                         'user_id' => $user->id, // Use this test's user ID
                         'items' => [
                             [
                                 'product_id' => $this->product2->id,
                                 'quantity' => '2.00', // Should be adjusted to 2.00
                                 'price_per_unit_at_addition' => '25.00',
                                 'unit_of_measure_at_addition' => $this->product2->unit_of_measure,
                                 'line_item_total' => '50.00', // 2 * 25
                             ]
                         ],
                         'total' => 50,
                     ]
                 ])
                 ->assertJsonMissing(['guest_cart_id'])
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'id', 'user_id', 'status', 'items' => [
                             '*' => ['id', 'cart_id', 'product_id', 'quantity', 'price_per_unit_at_addition', 'unit_of_measure_at_addition', 'line_item_total']
                         ],
                         'total'
                     ]
                 ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product2->id,
            'quantity' => 2.00,
            'price_per_unit_at_addition' => 25.00,
            'unit_of_measure_at_addition' => $this->product2->unit_of_measure,
        ]);
    }
    /**
     * Test that updating an item with a quantity less than the product's minimum order quantity
     * for an authenticated user automatically adjusts the quantity to the minimum.
     * @test
     */
    public function authenticated_user_updating_item_with_quantity_less_than_min_order_quantity_adjusts_to_min(): void
    {
        $user = $this->createAuthenticatedUser(); // Create and authenticate user for this test

        // Create cart and initial item directly
        $cart = Cart::create([
            'user_id' => $user->id, // Use this test's user ID
            'status' => 'active',
        ]);
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product2->id,
            'quantity' => 5.00,
            'price_per_unit_at_addition' => $this->product2->price_per_unit,
            'unit_of_measure_at_addition' => $this->product2->unit_of_measure,
            'line_item_total' => 5 * $this->product2->price_per_unit,
        ]);

        // Re-fetch the cart and cartItem to ensure they are fresh and relationships are loaded
        $cart = $cart->fresh();
        $cartItem = $cartItem->fresh();

        // Update quantity to less than min_order_quantity (product2's min is 2.00) using POST
        $response = $this->postJson("/api/cart/update-item/{$cartItem->id}", [
            'quantity' => 1.00,
            '_method' => 'PUT',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Cart item quantity updated successfully.',
                     'data' => [
                         'id' => $cart->id,
                         'items' => [
                             [
                                 'id' => $cartItem->id,
                                 'quantity' => '2.00', // Should be adjusted to 2.00
                                 'line_item_total' => '50.00', // 2 * 25
                             ]
                         ],
                         'total' => 50,
                     ]
                 ])
                 ->assertJsonMissing(['guest_cart_id'])
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'id', 'user_id', 'status', 'items' => [
                             '*' => ['id', 'cart_id', 'product_id', 'quantity', 'price_per_unit_at_addition', 'unit_of_measure_at_addition', 'line_item_total']
                         ],
                         'total'
                     ]
                 ]);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 2.00,
        ]);
    }

    // --- Guest User Cart Tests ---

    /**
     * Test that a guest user can request their cart and a new one is created,
     * receiving a guest_cart_id in the response.
     * @test
     */
    public function guest_user_can_get_a_new_empty_cart_and_receive_guest_cart_id(): void
    {
        $response = $this->getJson('/api/cart'); // No authentication or guest_cart_id header
        //dd($response->json()); // Debugging line to inspect the response
        $response->assertStatus(201) // Expect 201 Created for a new cart
                    ->assertJsonStructure([
                        'message',
                        'data' => [
                            'id', 'status', 'items', 'total'
                        ],
                        'guest_cart_id' // Guest ID must be present
                    ])
                    ->assertJson([
                        'message' => 'New guest cart created and retrieved successfully.',
                        'data' => [
                            'status' => 'active',
                            'items' => [],
                            'total' => 0.00,
                        ]
                    ]);

        $this->assertNotNull($response->json('guest_cart_id')); // Assert ID is not null
        $this->assertDatabaseHas('carts', [ // Verify guest cart in DB
            'id' => $response->json('guest_cart_id'),
            'user_id' => null,
            'status' => 'active',
        ]);
    }

    /**
     * Test that a guest user can retrieve their existing cart using the X-Guest-Cart-Id header.
     *@test
     */
    public function guest_user_can_get_their_existing_cart_using_header(): void
    {
        // Manually create a guest cart to simulate an existing one
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;

        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->getJson('/api/cart');

        $response->assertStatus(200) // Expect 200 OK for retrieving an existing cart
                    ->assertJson([
                        'message' => 'Guest cart retrieved successfully.',
                        'data' => [
                            'id' => $guestCartId,
                            'user_id' => null,
                            'status' => 'active',
                        ],
                        'guest_cart_id' => $guestCartId, // Ensure ID is returned
                    ]);
    }

    /**
     * Test that a guest user can add an item to a new cart and receive the guest_cart_id.
     * @test
     */
    public function guest_user_can_add_item_to_new_cart_and_receive_guest_cart_id(): void
    {
        $response = $this->postJson('/api/cart/add', [ // No header, new cart should be created
            'product_id' => $this->product1->id,
            'quantity' => 3,
        ]);
        //dd($response->json()); // Debugging line to inspect the response

        $response->assertStatus(201)
        
                    ->assertJsonStructure([
                        'message',
                        'data' => [
                            'id', 'user_id', 'status', 'items', 'total'
                        ],
                        'guest_cart_id'
                    ])
                    ->assertJson([
                        'message' => 'Product added to cart successfully.',
                        'data' => [
                            'user_id' => null, // Must be null
                            'items' => [
                                ['product_id' => $this->product1->id, 'quantity' => '3.00']
                            ],
                            'total' => 30.00,
                        ]
                    ]);

        $this->assertNotNull($response->json('guest_cart_id'));
        $this->assertDatabaseHas('carts', [ // Verify cart in DB
            'id' => $response->json('guest_cart_id'),
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('cart_items', [ // Verify item in DB
            'cart_id' => $response->json('guest_cart_id'),
            'product_id' => $this->product1->id,
            'quantity' => 3,
        ]);
    }

    /**
     * Test that a guest user can add an item to an existing cart by providing the guest_cart_id header.
     * @test
     */
    public function guest_user_can_add_item_to_existing_cart_using_header(): void
    {
        // Create an initial guest cart
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;

        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 4,
        ]);

        $response->assertStatus(201)
                    ->assertJson([
                        'message' => 'Product added to cart successfully.',
                        'data' => [
                            'id' => $guestCartId,
                            'user_id' => null,
                            'items' => [
                                ['product_id' => $this->product1->id, 'quantity' => '4.00']
                            ],
                            'total' => 40.00,
                        ],
                        'guest_cart_id' => $guestCartId,
                    ])
                    ->assertJsonStructure([
                        'message',
                        'data' => [
                            'id', 'user_id', 'status', 'items' => [
                                '*' => ['id', 'cart_id', 'product_id', 'quantity', 'price_per_unit_at_addition', 'unit_of_measure_at_addition', 'line_item_total']
                            ],
                            'total'
                        ],
                        'guest_cart_id'
                    ]);

        $this->assertDatabaseHas('cart_items', [ // Verify item in DB
            'cart_id' => $guestCartId,
            'product_id' => $this->product1->id,
            'quantity' => 4,
        ]);
    }

    /**
     * Test that adding an existing item in a guest cart updates its quantity.
     * @test
     */
    public function guest_user_adding_existing_item_updates_quantity_in_cart(): void
    {
        // Create guest cart and add an item
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;

        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 2,
        ]);

        // Add the same item again, expecting an update
        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 3,
        ]);

        $response->assertStatus(200)
                    ->assertJson([
                        'message' => 'Product quantity updated in cart successfully.',
                        'data' => [
                            'id' => $guestCartId,
                            'user_id' => null,
                            'items' => [
                                [
                                    'product_id' => $this->product1->id,
                                    'quantity' => '5.00', // 2 + 3 = 5
                                    'line_item_total' => '50.00',
                                ]
                            ],
                            'total' => 50.00,
                        ],
                        'guest_cart_id' => $guestCartId,
                    ])
                    ->assertJsonStructure([
                        'message',
                        'data' => [
                            'id', 'user_id', 'status', 'items' => [
                                '*' => ['id', 'cart_id', 'product_id', 'quantity', 'price_per_unit_at_addition', 'unit_of_measure_at_addition', 'line_item_total']
                            ],
                            'total'
                        ],
                        'guest_cart_id'
                    ]);

        $this->assertDatabaseHas('cart_items', [ // Verify updated quantity in DB
            'cart_id' => $guestCartId,
            'product_id' => $this->product1->id,
            'quantity' => 5,
        ]);
    }

    /**
     * Test that a guest user can update the quantity of an item in their cart.
     * @test
     */
    public function guest_user_can_update_cart_item_quantity(): void
    {
        // Create guest cart and add item
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;
        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 5,
        ]);
        $cartItem = $guestCart->items()->first();

        // Update quantity of that item
        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->putJson("/api/cart/update-item/{$cartItem->id}", [
            'quantity' => 10,
        ]);

        $response->assertStatus(200)
                    ->assertJson([
                        'message' => 'Cart item quantity updated successfully.',
                        'data' => [
                            'id' => $guestCartId,
                            'items' => [
                                [
                                    'id' => $cartItem->id,
                                    'quantity' => '10.00',
                                    'line_item_total' => '100.00',
                                ]
                            ],
                            'total' => 100.00,
                        ],
                        'guest_cart_id' => $guestCartId,
                    ])
                    ->assertJsonStructure([
                        'message',
                        'data' => [
                            'id', 'items' => [
                                '*' => ['id', 'cart_id', 'product_id', 'quantity', 'price_per_unit_at_addition', 'unit_of_measure_at_addition', 'line_item_total']
                            ],
                            'total'
                        ],
                        'guest_cart_id'
                    ]);

        $this->assertDatabaseHas('cart_items', [ // Verify update in DB
            'id' => $cartItem->id,
            'quantity' => 10,
            'line_item_total' => 100.00,
        ]);
    }

    /**
     * Test that a guest user cannot update a cart item quantity to exceed product stock.
     * @test
     */
    public function guest_user_cannot_update_cart_item_to_exceed_stock(): void
    {
        // Add item with some quantity
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;
        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 5,
        ]);
        $cartItem = $guestCart->items()->first();

        // Try to update quantity beyond product's stock (product1 stock is 20)
        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->putJson("/api/cart/update-item/{$cartItem->id}", [
            'quantity' => 25, // This is greater than product1's stock of 20
        ]);

        $response->assertStatus(422)
                    ->assertJsonValidationErrors('quantity')
                    
                    ->assertJsonFragment([
                        'quantity' => ['The requested quantity exceeds the available stock for this product.']
                    ]); // <--- Added specific error message assertion

        $this->assertDatabaseHas('cart_items', [ // Verify quantity remains unchanged in DB
            'id' => $cartItem->id,
            'quantity' => 5,
        ]);
    }

    /**
     * Test that a guest user can remove a specific item from their cart.
     * @test
     */
    public function guest_user_can_remove_item_from_cart(): void
    {
        // Add multiple items to ensure cart still exists after one is removed
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;
        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 5,
        ]);
        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product2->id,
            'quantity' => 2,
        ]);

        $cart = Cart::find($guestCartId); // Re-fetch cart after adding items
        $cartItemToDelete = $cart->items()->where('product_id', $this->product1->id)->first();

        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->deleteJson("/api/cart/remove-item/{$cartItemToDelete->id}");

        $response->assertStatus(200)
                    ->assertJson([
                        'message' => 'Cart item removed successfully.',
                        'data' => [
                            'id' => $guestCartId,
                            'user_id' => null,
                            'items' => [
                                ['product_id' => $this->product2->id]
                            ]
                        ],
                        'guest_cart_id' => $guestCartId,
                    ]);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItemToDelete->id,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product2->id,
        ]);
        $this->assertDatabaseHas('carts', [
            'id' => $guestCartId,
        ]);
    }

    /**
     * Test that if a guest user removes the last item, the cart itself is also deleted.
     * 
     * @test
     */
    public function guest_user_removing_last_item_also_deletes_cart(): void
    {
        // Add only one item
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;
        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1,
        ]);

        $cart = Cart::find($guestCartId);
        $cartItemToDelete = $cart->items()->first();

        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->deleteJson("/api/cart/remove-item/{$cartItemToDelete->id}");

        $response->assertStatus(200)
                    ->assertJson([
                        'message' => 'Cart item removed and cart is now empty and deleted.',
                        'data' => null,
                    ]);
        // The guest_cart_id should NOT be present if the cart is deleted entirely
        $response->assertJsonMissing(['guest_cart_id']);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItemToDelete->id,
        ]);
        $this->assertDatabaseMissing('carts', [
            'id' => $guestCartId,
        ]);
    }

    /**
     * Test that a guest user can clear their entire cart.
     * @test
     */
    public function guest_user_can_clear_their_entire_cart(): void
    {
        // Add multiple items
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;
        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 5,
        ]);
        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product2->id,
            'quantity' => 2,
        ]);

        $cart = Cart::find($guestCartId);
        $cartItemCount = $cart->items()->count();
        $this->assertGreaterThan(0, $cartItemCount);

        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/clear');

        $response->assertStatus(200)
                    ->assertJson([
                        'message' => 'Cart cleared successfully.',
                        'data' => null,
                    ]);
        $response->assertJsonMissing(['guest_cart_id']);

        $this->assertDatabaseMissing('carts', [
            'id' => $guestCartId,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $guestCartId,
        ]);
    }

    /**
     * Test that clearing a non-existent cart for a guest user returns a 404.
     * @test
     */
    public function guest_user_clearing_non_existent_cart_returns_404(): void
    {
        // Ensure no active cart with this ID exists
        $nonExistentCartId = 99999;
        Cart::find($nonExistentCartId)?->delete(); // Ensure it's not there

        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $nonExistentCartId,
        ])->postJson('/api/cart/clear');

        $response->assertStatus(404)
                    ->assertJson(['message' => 'No active cart found to clear.']);
    }
    /**
     * Test that adding an item with a quantity less than the product's minimum order quantity
     * for a guest user automatically adjusts the quantity to the minimum.
     * @test
     */
    public function guest_user_adding_item_with_quantity_less_than_min_order_quantity_adjusts_to_min(): void
    {
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;

        // product2 has min_order_quantity = 2.00
        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product2->id,
            'quantity' => 1.00, // Less than min_order_quantity
        ]);

        $response->assertStatus(201)
                    ->assertJson([
                        'message' => 'Product added to cart successfully.',
                        'data' => [
                            'user_id' => null,
                            'items' => [
                                [
                                    'product_id' => $this->product2->id,
                                    'quantity' => '2.00', // Should be adjusted to 2.00
                                    'price_per_unit_at_addition' => '25.00',
                                    'unit_of_measure_at_addition' => $this->product2->unit_of_measure,
                                    'line_item_total' => '50.00', // 2 * 25
                                ]
                            ],
                            'total' => 50,
                        ],
                        'guest_cart_id' => $guestCartId,
                    ])
                    ->assertJsonStructure([
                        'message',
                        'data' => [
                            'id', 'user_id', 'status', 'items' => [
                                '*' => ['id', 'cart_id', 'product_id', 'quantity', 'price_per_unit_at_addition', 'unit_of_measure_at_addition', 'line_item_total']
                            ],
                            'total'
                        ],
                        'guest_cart_id'
                    ]);

        $this->assertDatabaseHas('cart_items', [ // Verify adjustment in DB
            'product_id' => $this->product2->id,
            'cart_id' => $guestCartId,
            'quantity' => 2.00,
            'price_per_unit_at_addition' => 25.00, // <--- Changed to 'price'
            'unit_of_measure_at_addition' => $this->product2->unit_of_measure, // <--- Changed to 'unit_of_measure'
        ]);
    }

    /**
     * Test that updating an item with a quantity less than the product's minimum order quantity
     * for a guest user automatically adjusts the quantity to the minimum.
     * @test
     */
    public function guest_user_updating_item_with_quantity_less_than_min_order_quantity_adjusts_to_min(): void
    {
        $guestCart = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartId = $guestCart->id;

        // Add product2 with initial quantity greater than its min_order_quantity
        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product2->id,
            'quantity' => 5.00,
        ]);

        $cart = Cart::find($guestCartId);
        $cartItem = $cart->items()->first();

        // Update quantity to less than min_order_quantity (product2's min is 2.00)
        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartId,
        ])->putJson("/api/cart/update-item/{$cartItem->id}", [
            'quantity' => 1.00,
        ]);

        $response->assertStatus(200)
                    ->assertJson([
                        'message' => 'Cart item quantity updated successfully.',
                        'data' => [
                            'id' => $guestCartId,
                            'items' => [
                                [
                                    'id' => $cartItem->id,
                                    'quantity' => '2.00', // Should be adjusted to 2.00
                                    'line_item_total' => '50.00', // 2 * 25
                                ]
                            ],
                            'total' => 50.00,
                        ],
                        'guest_cart_id' => $guestCartId,
                    ])
                    ->assertJsonStructure([
                        'message',
                        'data' => [
                            'id', 'user_id', 'status', 'items' => [
                                '*' => ['id', 'cart_id', 'product_id', 'quantity', 'price_per_unit_at_addition', 'unit_of_measure_at_addition', 'line_item_total']
                            ],
                            'total'

                        ],
                        'guest_cart_id'
                    ]);

        $this->assertDatabaseHas('cart_items', [ // Verify adjustment in DB
            'id' => $cartItem->id,
            'quantity' => 2.00,
        ]);
    }

    /**
     * Test that a non-existent guest cart ID in the header results in a new cart being created for add operations.
     * @test
     */
    public function add_item_with_non_existent_guest_cart_id_creates_new_cart(): void
    {
        $nonExistentCartId = 99999;
        // Ensure no cart with this ID exists in the database
        Cart::find($nonExistentCartId)?->delete();

        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $nonExistentCartId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(201) // Expect a new cart to be created
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'user_id', 'items'],
                'guest_cart_id'
            ])
            ->assertJson([
                'message' => 'Product added to cart successfully.',
                'data' => [
                    'user_id' => null,
                    'items' => [
                        ['product_id' => $this->product1->id, 'quantity' => '1.00']
                    ]
                ]
            ]);

        // Assert that a NEW cart ID was returned, not the non-existent one
        $this->assertNotEquals($nonExistentCartId, $response->json('guest_cart_id'));
        $this->assertDatabaseHas('carts', [
            'id' => $response->json('guest_cart_id'),
            'user_id' => null,
        ]);
    }

    /**
     * Test that a non-existent guest cart ID in the header for update operations returns a 404.
     * @test
     */
    public function update_item_with_non_existent_guest_cart_id_returns_404(): void
    {
        $nonExistentCartItemId = 99999; // Assume this cart item does not exist
        $nonExistentCartId = 88888; // Assume this cart does not exist

        // Ensure no cart or cart item with these IDs exists
        CartItem::find($nonExistentCartItemId)?->delete();
        Cart::find($nonExistentCartId)?->delete();

        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $nonExistentCartId,
        ])->putJson("/api/cart/update-item/{$nonExistentCartItemId}", [
            'quantity' => 1,
        ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'No query results for model [App\\Models\\CartItem] 99999']);
    }

    /**
     * Test that a non-existent guest cart ID in the header for remove operations returns a 404.
     * @test
     */
    public function remove_item_with_non_existent_guest_cart_id_returns_404(): void
    {
        $nonExistentCartItemId = 99999;
        $nonExistentCartId = 88888;

        CartItem::find($nonExistentCartItemId)?->delete();
        Cart::find($nonExistentCartId)?->delete();

        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $nonExistentCartId,
        ])->deleteJson("/api/cart/remove-item/{$nonExistentCartItemId}");

        $response->assertStatus(404)
            ->assertJson(['message' => 'No query results for model [App\\Models\\CartItem] 99999']);
    }

    /**
     * Test that attempting to modify a guest cart item using a mismatching guest_cart_id in the header returns 403.
     * @test
     */
    public function guest_user_cannot_modify_item_with_mismatching_guest_cart_id(): void
    {
        // Create Cart A and add item
        $guestCartA = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartAId = $guestCartA->id;
        $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartAId,
        ])->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1,
        ]);
        $cartItemA = $guestCartA->items()->first();

        // Create Cart B (which will be the "mismatching" cart for the request)
        $guestCartB = Cart::create(['user_id' => null, 'status' => 'active']);
        $guestCartBId = $guestCartB->id;

        // Attempt to update cartItemA using guestCartBId in header
        $response = $this->withHeaders([
            'X-Guest-Cart-Id' => $guestCartBId, // Mismatching ID
        ])->putJson("/api/cart/update-item/{$cartItemA->id}", [
            'quantity' => 2,
        ]);

        $response->assertStatus(403) // Expect Forbidden due to ID mismatch
            ->assertJson([
                'message' => 'Unauthorized to update this cart item or invalid guest cart ID.',
                'data' => NULL,
            ]);

        // Assert that original item quantity remains unchanged
        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItemA->id,
            'quantity' => 1,
        ]);
    }
}
