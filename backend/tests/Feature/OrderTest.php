<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Address;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\OrderAddressSnapshot;
use App\Services\PaystackService;
use Mockery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test; // Import the attribute

class OrderTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the config for Paystack is loaded for testing
        config()->set('services.paystack.secret_key', 'sk_test_mock_secret');
        config()->set('services.paystack.public_key', 'pk_test_mock_public');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');
        config()->set('services.paystack.webhook_secret', 'sk_test_mock_webhook_secret'); // Use the same for simplicity in test

        // Set a frontend URL for the callback
        config()->set('app.frontend_url', 'http://localhost:5173');

        // Mock the PaystackService
        $this->mock(PaystackService::class, function ($mock) {
            // Default mock for initializePayment
            $mock->shouldReceive('initializePayment')
                 ->andReturn([
                     'authorization_url' => 'http://paystack.com/pay/mock_auth_url',
                     'access_code' => 'mock_access_code',
                     'reference' => 'mock_paystack_ref_' . Str::random(10),
                 ])
                 ->byDefault(); // Allow this to be called multiple times

            // Default mock for verifyPayment
            $mock->shouldReceive('verifyPayment')
                 ->andReturn([
                     'status' => 'success',
                     'amount' => 10000, // Example amount in pesewas (GHS 100.00)
                     'currency' => 'GHS',
                     'reference' => 'mock_paystack_ref',
                     'gateway_response' => 'Approved',
                     'id' => 12345,
                 ])
                 ->byDefault();

            // Default mock for verifyWebhookSignature
            $mock->shouldReceive('verifyWebhookSignature')
                 ->andReturn(true) // Always valid signature for tests by default
                 ->byDefault();
        });
    }

    /**
     * Helper method to create a user and customer.
     */
    protected function createCustomerUser(string $role = 'customer'): User
    {
        $user = User::factory()->create(['role' => $role]);
        Customer::factory()->create(['user_id' => $user->id]);
        return $user;
    }

    /**
     * Helper method to create a product.
     */
    protected function createProduct(array $attributes = []): Product
    {
        return Product::factory()->create($attributes);
    }

    /**
     * Helper method to create an address for a customer.
     * Note: 'recipient_name' and 'phone_number' are NOT on the addresses table.
     * They are derived from the User model for OrderAddressSnapshot.
     */
    protected function createAddress(Customer $customer, array $attributes = []): Address
    {
        // Use the `for` method to correctly associate the address with the given customer.
        // The AddressFactory itself should not try to create a new customer if one is provided.
        return Address::factory()->for($customer)->create($attributes);
    }

    /**
     * Helper method to create a delivery option.
     */
    protected function createDeliveryOption(array $attributes = []): DeliveryOption
    {
        // Ensure the 'active' state is correctly called from the factory
        return DeliveryOption::factory()->active()->create($attributes);
    }

    /**
     * Helper method to populate a cart for a user.
     */
    protected function populateCart(User $user, Product $product, float $quantity = 1.0): Cart
    {
        $cart = Cart::firstOrCreate(['user_id' => $user->id, 'status' => 'active']);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'product_name' => $product->name,
            'price_per_unit_at_addition' => $product->current_price,
            'unit_of_measure_at_addition' => $product->unit_of_measure,
            'line_item_total' => round($quantity * $product->current_price, 2), // Ensure rounding
        ]);
        return $cart;
    }

    #[Test]
    public function a_customer_can_place_a_new_order_with_paystack_payment()
    {
        $user = $this->createCustomerUser();
        $product = $this->createProduct(attributes: ['price_per_unit' => 50.00, 'stock_quantity' => 10, 'is_active' => true, 'min_order_quantity' => 1.0, 'unit_of_measure' => 'liter']);
        $this->populateCart($user, $product, 2.0); // 2 * 50 = 100 GHS
        $address = $this->createAddress($user->customer()->first()); // Address no longer has recipient_name/phone_number
        $deliveryOption = $this->createDeliveryOption(['cost' => 10.00]); // 10 GHS

        $orderTotalExpected = (2.0 * 50.00) + 10.00; // 100 + 10 = 110 GHS

        // Mock PaystackService to expect initializePayment call
        $this->mock(PaystackService::class, function ($mock) use ($orderTotalExpected, $user) {
            $mock->shouldReceive('initializePayment')
                 ->once()
                 ->withArgs(function ($amount, $email, $reference, $callbackUrl) use ($orderTotalExpected, $user) {
                     return $amount == $orderTotalExpected &&
                            $email == $user->email &&
                            Str::startsWith($reference, 'MC-ORD-') && // Order number format
                            Str::contains($callbackUrl, '/payment/callback?order_ref='); // Frontend callback
                 })
                 ->andReturn([
                     'authorization_url' => 'http://paystack.com/pay/test_auth_url',
                     'access_code' => 'test_access_code',
                     'reference' => 'test_paystack_ref_123',
                 ]);
            $mock->shouldReceive('verifyPayment')->byDefault();
            $mock->shouldReceive('verifyWebhookSignature')->andReturn(true)->byDefault();
        });

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'delivery_address_id' => $address->id,
            //'billing_address_id' => $address->id, // Same as delivery for simplicity
            'delivery_option_id' => $deliveryOption->id,
            'payment_method' => 'Card', // Using a gateway payment method
            'notes' => 'Please deliver early.',
        ]);

        
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'id', 'order_number', 'order_total', 'payment_status', 'payment_method',
                         'delivery_address_id', 'delivery_option_id', 'customer_id',
                         'payment_gateway_transaction_id', 'payment_details',
                         'items' => [
                             '*' => ['id', 'product_id', 'quantity', 'line_item_total']
                         ],
                         'address_snapshots' => [
                             '*' => ['id', 'order_id', 'address_type', 'recipient_name']
                         ]
                     ],
                     'authorization_url'
                 ])
                 ->assertJson([
                     'message' => 'Order placed. Redirecting to payment gateway.',
                     'authorization_url' => 'http://paystack.com/pay/test_auth_url',
                     'data' => [
                         'order_total' => (float)$orderTotalExpected,
                         'payment_status' => 'pending_gateway_payment',
                         'payment_method' => 'Card',
                         'payment_gateway_transaction_id' => 'test_paystack_ref_123',
                     ]
                 ]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $user->customer->id,
            'order_total' => $orderTotalExpected,
            'payment_status' => 'pending_gateway_payment',
            'payment_method' => 'Card',
            'delivery_address_id' => $address->id,
            'delivery_option_id' => $deliveryOption->id,
            'payment_gateway_transaction_id' => 'test_paystack_ref_123',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2.0,
            'price_per_unit_at_purchase' => 50.00,
            'line_item_total' => 100.00,
        ]);

        // Assert snapshots were created with user's name/phone
        $this->assertDatabaseHas('order_address_snapshots', [
            'order_id' => Order::first()->id,
            'address_type' => 'Shipping',
            'address_line1' => $address->address_line1,
            'recipient_name' => $user->name, // Now comes from User
            'phone_number' => $user->phone_number, // Now comes from User
        ]);
        $this->assertDatabaseHas('order_address_snapshots', [
            'order_id' => Order::first()->id,
            'address_type' => 'Billing',
            'address_line1' => $address->address_line1,
            'recipient_name' => $user->name, // Now comes from User
            'phone_number' => $user->phone_number, // Now comes from User
        ]);

        // Verify cart is cleared
        $this->assertDatabaseMissing('carts', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);

        // Verify stock is decremented
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8, // Original 10 - 2 = 8
        ]);
    }

    #[Test]
    public function a_customer_can_place_a_new_order_with_cash_on_delivery()
    {
        $user = $this->createCustomerUser();
        $product = $this->createProduct(['price_per_unit' => 25.00, 'stock_quantity' => 5, 'is_active' => true, 'min_order_quantity' => 1.0]);
        $this->populateCart($user, $product, 3.0); // 3 * 25 = 75 GHS
        $address = $this->createAddress($user->customer()->first());
        $deliveryOption = $this->createDeliveryOption(['cost' => 5.00]); // 5 GHS

        $orderTotalExpected = (3.0 * 25.00) + 5.00; // 75 + 5 = 80 GHS

        // Ensure PaystackService initializePayment is NOT called for COD
        $this->mock(PaystackService::class, function ($mock) {
            $mock->shouldNotReceive('initializePayment');
            $mock->shouldReceive('verifyPayment')->byDefault();
            $mock->shouldReceive('verifyWebhookSignature')->andReturn(true)->byDefault();
        });

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'delivery_address_id' => $address->id,
            //'billing_address_id' => $address->id,
            'delivery_option_id' => $deliveryOption->id,
            'payment_method' => 'Cash on Delivery',
            'notes' => 'Call before delivery.',
        ]);
       


        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'id', 'order_number', 'order_total', 'payment_status', 'payment_method',
                         // No authorization_url for COD
                     ],
                 ])
                 ->assertJson([
                     'message' => 'Order placed successfully (Cash on Delivery)!',
                     'data' => [
                         'order_total' => $orderTotalExpected,
                         'payment_status' => 'unpaid', // COD starts as unpaid
                         'payment_method' => 'Cash on Delivery',
                     ]
                 ]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $user->customer->id,
            'order_total' => $orderTotalExpected,
            'payment_status' => 'unpaid',
            'payment_method' => 'Cash on Delivery',
        ]);

        // Verify cart is cleared
        $this->assertDatabaseMissing('carts', ['user_id' => $user->id]);

        // Verify stock is decremented
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 2, // Original 5 - 3 = 2
        ]);
    }

    #[Test]
    public function placing_an_order_fails_if_cart_is_empty()
    {
        $user = $this->createCustomerUser();
        $address = $this->createAddress($user->customer()->first());
        $deliveryOption = $this->createDeliveryOption();

        // No products added to cart
        $cart = Cart::firstOrCreate(['user_id' => $user->id, 'status' => 'active']);
        // Ensure cart is empty
        $cart->items()->delete();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'delivery_address_id' => $address->id,
            'delivery_option_id' => $deliveryOption->id,
            'payment_method' => 'Card',
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'message' => 'Your cart is empty. Please add items before placing an order.',
                 ]);
    }

    #[Test]
    public function placing_an_order_fails_if_product_is_out_of_stock()
    {
        $user = $this->createCustomerUser();
        $product = $this->createProduct(['price_per_unit' => 50.00, 'stock_quantity' => 1, 'is_active' => true]); // Only 1 in stock
        $this->populateCart($user, $product, 2.0); // Tries to order 2
        $address = $this->createAddress($user->customer()->first());
        $deliveryOption = $this->createDeliveryOption();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'delivery_address_id' => $address->id,
            'delivery_option_id' => $deliveryOption->id,
            'payment_method' => 'Card',
        ]);

        $response->assertStatus(422)
                 ->assertJson([
                     'message' => "Product '{$product->product_name}' is out of stock or unavailable for the requested quantity.",
                     'product_id' => $product->id,
                 ]);

        // Verify stock was NOT decremented
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 1,
        ]);
        // Verify no order was created
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function a_customer_can_view_their_own_orders()
    {
        $user = $this->createCustomerUser();
        $order = Order::factory()->create(['customer_id' => $user->customer->id]);
        OrderAddressSnapshot::factory()->create(['order_id' => $order->id, 'address_type' => 'Shipping']);
        OrderAddressSnapshot::factory()->create(['order_id' => $order->id, 'address_type' => 'Billing']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/orders');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'current_page',
                         'data' => [
                             '*' => ['id', 'order_number', 'customer_id', 'address_snapshots']
                         ]
                     ]
                 ])
                 ->assertJsonFragment([
                     'order_number' => $order->order_number,
                 ]);
    }

    #[Test]
    public function a_customer_cannot_view_another_customers_order()
    {
        $customer1 = $this->createCustomerUser();
        $customer2 = $this->createCustomerUser();
        $orderOfCustomer2 = Order::factory()->create(['customer_id' => $customer2->customer->id]);

        $response = $this->actingAs($customer1, 'sanctum')->getJson("/api/orders/{$orderOfCustomer2->id}");

        $response->assertStatus(403)
                 ->assertJson([
                     'message' => 'Unauthorized to view this order.',
                 ]);
    }

    #[Test]
    public function an_admin_can_view_all_orders()
    {
        $adminUser = $this->createCustomerUser('admin');
        $customer1 = $this->createCustomerUser();
        $customer2 = $this->createCustomerUser();

        $order1 = Order::factory()->create(['customer_id' => $customer1->customer->id]);
        $order2 = Order::factory()->create(['customer_id' => $customer2->customer->id]);

        $response = $this->actingAs($adminUser, 'sanctum')->getJson('/api/orders');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'current_page',
                         'data' => [
                             '*' => ['id', 'order_number', 'customer_id']
                         ]
                     ]
                 ])
                 ->assertJsonFragment(['order_number' => $order1->order_number])
                 ->assertJsonFragment(['order_number' => $order2->order_number]);
    }

    #[Test]
    public function an_admin_can_update_an_order_status()
    {
        $adminUser = $this->createCustomerUser('admin');
        $customer = $this->createCustomerUser();
        $order = Order::factory()->create([
            'customer_id' => $customer->customer->id,
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($adminUser, 'sanctum')->putJson("/api/admin/orders/{$order->id}", [
            'order_status' => 'shipped',
            'payment_status' => 'paid',
            'delivery_tracking_number' => 'TRACK123XYZ',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Order updated successfully.',
                     'data' => [
                         'id' => $order->id,
                         'order_status' => 'shipped',
                         'payment_status' => 'paid',
                         'delivery_tracking_number' => 'TRACK123XYZ',
                     ]
                 ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'shipped',
            'payment_status' => 'paid',
            'delivery_tracking_number' => 'TRACK123XYZ',
        ]);
    }

    #[Test]
    public function an_admin_can_delete_an_order()
    {
        $adminUser = $this->createCustomerUser('admin');
        $customer = $this->createCustomerUser();
        $order = Order::factory()->create(['customer_id' => $customer->customer->id]);
        OrderAddressSnapshot::factory()->create(['order_id' => $order->id, 'address_type' => 'Shipping']);
        OrderAddressSnapshot::factory()->create(['order_id' => $order->id, 'address_type' => 'Billing']);

        $response = $this->actingAs($adminUser, 'sanctum')->deleteJson("/api/admin/orders/{$order->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Order deleted successfully.',
                 ]);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_address_snapshots', ['order_id' => $order->id]);
    }

    #[Test]
    public function a_non_admin_cannot_update_an_order()
    {
        $customerUser = $this->createCustomerUser();
        $order = Order::factory()->create(['customer_id' => $customerUser->customer->id]);

        $response = $this->actingAs($customerUser, 'sanctum')->putJson("/api/admin/orders/{$order->id}", [
            'order_status' => 'shipped',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function a_non_admin_cannot_delete_an_order()
    {
        $customerUser = $this->createCustomerUser();
        $order = Order::factory()->create(['customer_id' => $customerUser->customer->id]);

        $response = $this->actingAs($customerUser, 'sanctum')->deleteJson("/api/admin/orders/{$order->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function paystack_webhook_successfully_updates_order_status()
    {
        $user = $this->createCustomerUser();
        $order = Order::factory()->create([
            'customer_id' => $user->customer->id,
            'order_number' => 'MC-ORD-TEST12345',
            'order_total' => 100.00,
            'payment_status' => 'pending_gateway_payment',
            'payment_method' => 'Card',
            'payment_gateway_transaction_id' => 'mock_paystack_ref_webhook',
        ]);

        $this->mock(PaystackService::class, function ($mock) use ($order) {
            $mock->shouldReceive('verifyWebhookSignature')->andReturn(true)->once();
            $mock->shouldReceive('verifyPayment')->andReturn([
                'status' => true,
                'amount' => (int)($order->order_total * 100),
                'currency' => 'GHS',
                'reference' => $order->order_number,
                'gateway_response' => 'Approved',
                'id' => 'paystack_trans_id_123',
            ])->once();
            $mock->shouldNotReceive('initializePayment');
        });

        $webhookPayload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => $order->order_number,
            'status' => 'success',
            'amount' => (int)($order->order_total * 100),
            'currency' => 'GHS',
            'gateway_response' => 'Approved',
            'id' => 'paystack_trans_id_123',
            'customer' => ['email' => $user->email],
        ],
    ]);

        // Get the webhook secret from config (same as your service uses)
    $webhookSecret = config('services.paystack.webhook_secret');
    // Generate the actual expected signature for the payload
    $expectedSignature = hash_hmac('sha512', $webhookPayload, $webhookSecret);

    $response = $this->postJson('/api/paystack/webhook', json_decode($webhookPayload, true), [
        'x-paystack-signature' => $expectedSignature, // <-- USE THE CORRECTLY GENERATED SIGNATURE HERE
    ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Webhook processed successfully']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'payment_gateway_transaction_id' => 'paystack_trans_id_123',
        ]);
    }

    #[Test]
public function paystack_webhook_fails_with_invalid_signature()
{
    // 1. Create a user and order for the webhook payload, even if not directly used by the test's assertions.
    // The webhook payload needs a customer email, which comes from the user.
    $user = $this->createCustomerUser();
    $order = Order::factory()->create([
        'customer_id' => $user->customer->id, // Link order to the user's customer
        'order_number' => 'MC-ORD-INVALID',
        'order_total' => 100.00, // Ensure order_total exists for the webhook payload
    ]);

    // 2. Mock PaystackService to return false for signature verification.
    // This is correct as we are testing the controller's response to an invalid signature.
    $this->mock(PaystackService::class, function ($mock) {
        $mock->shouldReceive('verifyWebhookSignature')->andReturn(false)->once(); // Expect it to be called once and return false
        $mock->shouldNotReceive('verifyPayment'); // verifyPayment should NOT be called if signature fails
    });

    // 3. Prepare the webhook payload.
    $webhookPayload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => $order->order_number,
            'status' => 'success',
            'amount' => (int)($order->order_total * 100),
            'currency' => 'GHS',
            'gateway_response' => 'Approved',
            'id' => 'paystack_trans_id_123',
            'customer' => ['email' => $user->email], // Use the created user's email
        ],
    ]);

    // 4. Send the request with an *intentionally invalid* signature.
    // This is the key change to truly test the invalid signature scenario.
    $response = $this->postJson('/api/paystack/webhook', json_decode($webhookPayload, true), [
        'x-paystack-signature' => 'this_is_an_invalid_signature_string', // <-- Provide an explicitly INVALID string
    ]);
    
    // 5. Assert the expected behavior.
    $response->assertStatus(401)
             ->assertJson(['message' => 'Invalid signature']);

    // 6. Assert database state remains unchanged.
    // The order's status should not be modified if the webhook signature is invalid.
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'payment_status' => $order->payment_status, // Should remain as it was
        'order_status' => $order->order_status,     // Should remain as it was
    ]);
}

    #[Test]
    public function paystack_webhook_handles_amount_mismatch()
    {
        $user = $this->createCustomerUser();
        $order = Order::factory()->create([
            'customer_id' => $user->customer->id,
            'order_number' => 'MC-ORD-MISMATCH',
            'order_total' => 100.00,
            'payment_status' => 'pending_gateway_payment',
            'payment_method' => 'Card',
        ]);

        $this->mock(PaystackService::class, function ($mock) use ($order) {
            $mock->shouldReceive('verifyWebhookSignature')->andReturn(true)->once();
            $mock->shouldReceive('verifyPayment')->andReturn([
                'status' => 'success',
                'amount' => (int)($order->order_total * 100) + 500,
                'currency' => 'GHS',
                'reference' => $order->order_number,
                'gateway_response' => 'Approved',
                'id' => 'paystack_trans_id_mismatch',
            ])->once();
        });

        $webhookPayload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'reference' => $order->order_number,
                'status' => 'success',
                'amount' => (int)($order->order_total * 100) + 500,
                'currency' => 'GHS',
                'gateway_response' => 'Approved',
                'id' => 'paystack_trans_id_mismatch',
                'customer' => ['email' => $user->email],
            ],
        ]);

        $response = $this->postJson('/api/paystack/webhook', json_decode($webhookPayload, true), [
            'x-paystack-signature' => 'mock_valid_signature',
        ]);

        $response->assertStatus(400)
                 ->assertJson(['message' => 'Amount or currency mismatch']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'amount_mismatch',
            'order_status' => 'cancelled',
        ]);
    }
}
