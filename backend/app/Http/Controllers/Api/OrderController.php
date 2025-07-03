<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Address;
use App\Models\DeliveryOption;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\StoreOrderRequest;


class OrderController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        Log::info("User {$user->id} is accessing their orders.", ['role' => $user->role]);

        if ($user->role === 'admin') {
            // Admin can view all orders with eager loaded relationships
            $orders = Order::with(['customer.user', 'items.product', 'deliveryOption', 'addressSnapshots'])
                            ->orderBy('created_at', 'desc')
                            ->paginate(15);

            return response()->json([
                'message' => 'All orders retrieved successfully.',
                'data' => $orders,
            ]);
        } else {
            // Customer can only view their own orders
            $customer = $user->customer;
            if (!$customer) {
                return response()->json([
                    'message' => 'No customer profile found for the authenticated user.',
                    'data' => [],
                ], 404);
            }

            $orders = Order::where('customer_id', $customer->id)
                            ->with(['items.product', 'deliveryOption', 'addressSnapshots'])
                            ->orderBy('created_at', 'desc')
                            ->paginate(15);

            return response()->json([
                'message' => 'Your orders retrieved successfully.',
                'data' => $orders,
            ]);
        }
    }

    /**
     * Store a newly created order in storage.
     * This method handles the checkout process:
     * 1. Validates input (addresses, delivery option, payment method).
     * 2. Fetches the authenticated user's active cart.
     * 3. Checks product stock and minimum order quantities.
     * 4. Creates a new Order record.
     * 5. Creates OrderItem records from cart items, snapshotting product details.
     * 6. Creates OrderAddressSnapshot records for shipping and billing addresses.
     * 7. Reduces product stock.
     * 8. Clears the user's cart.
     * All within a database transaction.
     *
     * @param StoreOrderRequest $request
     */
    public function store(StoreOrderRequest $request)
    {
        $user = Auth::user();
        $customer = $user->customer;

        if (!$customer) {
            return response()->json([
                'message' => 'Customer profile not found. Please complete your customer profile before placing an order.',
            ], 400);
        }

        // Fetch the user's active cart
        $cart = Cart::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->with('items.product')
                    ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty. Please add items before placing an order.',
            ], 400);
        }

        // Fetch selected addresses and delivery option
        $deliveryAddress = Address::find($request->delivery_address_id);
        $billingAddress = $request->billing_address_id ? Address::find($request->billing_address_id) : $deliveryAddress;
        $deliveryOption = DeliveryOption::find($request->delivery_option_id);

        // Basic sanity checks (though covered by request validation, good for explicit clarity)
        if (!$deliveryAddress || !$billingAddress || !$deliveryOption) {
             return response()->json([
                'message' => 'Invalid address or delivery option provided.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $orderItemsData = [];
            $subTotal = 0;

            // Validate each cart item against current product stock and snapshot details
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;

                if (!$product || !$product->is_active || $product->stock_quantity < $cartItem->quantity) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Product '{$cartItem->product_name}' is out of stock or unavailable for the requested quantity.",
                        'product_id' => $cartItem->product_id,
                    ], 422);
                }

                // Ensure minimum order quantity is met (if not already enforced by cart logic)
                if ($cartItem->quantity < $product->min_order_quantity) {
                     DB::rollBack();
                     return response()->json([
                         'message' => "Minimum order quantity for '{$product->name}' is {$product->min_order_quantity} {$product->unit_of_measure}.",
                         'product_id' => $product->id,
                     ], 422);
                }

                $priceAtOrder = $product->current_price; // Assuming current_price accessor on Product model
                $lineItemTotal = $cartItem->quantity * $priceAtOrder;
                $subTotal += $lineItemTotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name, // Snapshot product name
                    'price_per_unit_at_purchase' => $priceAtOrder, // Snapshot price
                    'unit_of_measure_at_purchase' => $product->unit_of_measure, // Snapshot unit
                    'quantity' => $cartItem->quantity,
                    'line_item_total' => $lineItemTotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Reduce product stock
                $product->decrement('stock_quantity', $cartItem->quantity);
            }

            $initialPaymentStatus = ($request->payment_method === 'Cash on Delivery') ? 'unpaid' : 'pending_gateway_payment';


            // Create the Order
            $order = Order::create([
                'customer_id' => $customer->id,
                'delivery_address_id' => $deliveryAddress->id,
                'delivery_option_id' => $deliveryOption->id,
                'order_total' => $subTotal + $deliveryOption->cost, // Calculate total
                'payment_method' => $request->payment_method,
                'payment_status' => $initialPaymentStatus, // Set initial payment status
                'order_status' => 'pending',   // Default order status
                'notes' => $request->notes,
                'ordered_at' => now(),
                // order_number is generated in the model's booted method
            ]);

            // Create Order Items
            $order->items()->createMany($orderItemsData);

            // Create Shipping Address Snapshot
            $order->addressSnapshots()->create([
                'address_type' => 'Shipping',
                'recipient_name' => $user->name, // Use user's name
                'phone_number' => $user->phone_number, // Use user's phone number
                'address_line1' => $deliveryAddress->address_line1,
                'address_line2' => $deliveryAddress->address_line2,
                'city' => $deliveryAddress->city,
                'region' => $deliveryAddress->region,
                'country' => $deliveryAddress->country,
                'ghanapost_gps_address' => $deliveryAddress->ghanapost_gps_address,
                'digital_address_description' => $deliveryAddress->digital_address_description,
                'delivery_instructions' => $deliveryAddress->delivery_instructions,
                'created_at' => now(), // Manually set created_at as timestamps is false for this model
            ]);

            // Create Billing Address Snapshot (if different, or a copy of shipping)
            $order->addressSnapshots()->create([
                'address_type' => 'Billing',
                'recipient_name' => $user->name,
                'phone_number' => $user->phone_number,
                'address_line1' => $deliveryAddress->address_line1,
                'address_line2' => $deliveryAddress->address_line2,
                'city' => $deliveryAddress->city,
                'region' => $deliveryAddress->region,
                'country' => $deliveryAddress->country,
                'ghanapost_gps_address' => $deliveryAddress->ghanapost_gps_address,
                'digital_address_description' => $deliveryAddress->digital_address_description,
                'delivery_instructions' => $deliveryAddress->delivery_instructions,
                'created_at' => now(), // Manually set created_at
            ]);

            // Clear the user's cart after successful order placement
            $cart->items()->delete();
            $cart->delete(); // Delete the cart itself

            DB::commit();

           // --- Paystack Integration ---
            if ($request->payment_method !== 'Cash on Delivery') {
                $orderTotal = $subTotal + $deliveryOption->cost;
                $callbackUrl = config('app.frontend_url') . '/payment/callback?order_ref=' . $order->order_number; // Frontend URL for callback
                $paystackResponse = $this->paystackService->initializePayment(
                    $orderTotal,
                    $user->email, // Use user's email for payment
                    $order->order_number, // Use order number as reference
                    $callbackUrl
                );

                if ($paystackResponse) {
                    $order->update([
                        'payment_gateway_transaction_id' => $paystackResponse['reference'],
                        'payment_details' => json_encode(['authorization_url' => $paystackResponse['authorization_url'], 'access_code' => $paystackResponse['access_code']]),
                    ]);
                    return response()->json([
                        'message' => 'Order placed. Redirecting to payment gateway.',
                        'data' => $order->load(['customer.user', 'items.product', 'deliveryOption', 'addressSnapshots']),
                        'authorization_url' => $paystackResponse['authorization_url'],
                    ], 201);
                } else {
                    // If Paystack initiation fails, revert order status or mark as failed
                    $order->update(['payment_status' => 'failed', 'order_status' => 'cancelled']);
                    Log::error("Paystack initiation failed for order {$order->id}. Order status set to failed/cancelled.");
                    return response()->json([
                        'message' => 'Order placed, but payment initiation failed. Please try again or choose Cash on Delivery.',
                        'data' => $order->load(['customer.user', 'items.product', 'deliveryOption', 'addressSnapshots']),
                    ], 500);
                }
            }

            // If Cash on Delivery, just return success
            return response()->json([
                'message' => 'Order placed successfully (Cash on Delivery)!',
                'data' => $order->load(['customer.user', 'items.product', 'deliveryOption', 'addressSnapshots']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Order placement failed: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to place order. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified order.
     * Authenticated Customer: Can only view their own order.
     * Admin User: Can view any order.
     *
     * @param Order $order
     */
    public function show(Order $order)
    {
        $user = Auth::user();

        // Authorization check
        if ($user->role !== 'admin' && $order->customer->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to view this order.',
            ], 403); // 403 Forbidden
        }

        // Eager load all necessary relationships for a detailed view
        $order->load(['customer.user', 'items.product', 'deliveryOption', 'addressSnapshots']);

        return response()->json([
            'message' => 'Order retrieved successfully.',
            'data' => $order,
        ]);
    }

    /**
     * Update the specified order in storage.
     * Only accessible by Admin users.
     *
     * @param UpdateOrderRequest $request
     * @param Order $order
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {
        // Authorization is handled by UpdateOrderRequest's authorize method (admin only)

        DB::beginTransaction();
        try {
            $order->update($request->validated());

            // If payment_status is updated to 'paid', you might want to record payment details
            if ($request->has('payment_status') && $request->payment_status === 'paid') {
                // This is a simplified example. In a real app, this would come from a payment gateway webhook.
                // For manual admin updates, you might allow them to input transaction ID/details.
                if ($request->has('payment_gateway_transaction_id')) {
                    $order->payment_gateway_transaction_id = $request->payment_gateway_transaction_id;
                }
                if ($request->has('payment_details')) {
                    $order->payment_details = json_decode($request->payment_details, true);
                }
                $order->save(); // Save again if payment-specific fields were updated
            }

            DB::commit();

            $order->load(['customer.user', 'items.product', 'deliveryOption', 'addressSnapshots']);

            return response()->json([
                'message' => 'Order updated successfully.',
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Order update failed: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to update order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified order from storage.
     * Only accessible by Admin users.
     *
     * @param Order $order
     */
    public function destroy(Order $order)
    {
        // Authorization check (Admin only)
        if (Auth::user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized to delete this order.',
            ], 403); // 403 Forbidden
        }

        DB::beginTransaction();
        try {
            // Deleting an order will cascade delete its order_items and order_address_snapshots
            // due to onDelete('cascade') in their migrations.
            $order->delete();

            DB::commit();

            return response()->json([
                'message' => 'Order deleted successfully.',
            ], 200); // Or 204 No Content
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Order deletion failed: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to delete order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Handle Paystack webhook notifications.
     * This route should be publicly accessible but secured by signature verification.
     *
     * @param Request $request
     */
    public function handlePaystackWebhook(Request $request)
    {
        // 1. Verify webhook signature
        $paystackSignature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        if (!$this->paystackService->verifyWebhookSignature($payload, $paystackSignature)) {
            Log::warning('Paystack Webhook: Invalid signature received.', ['payload' => $payload, 'signature' => $paystackSignature]);
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = json_decode($payload, true);
        Log::info('Paystack Webhook: Event received.', ['event' => $event]);

        // 2. Process the event
        if ($event['event'] === 'charge.success') {
            $reference = $event['data']['reference'];
           

            DB::beginTransaction();
            try {
                $order = Order::where('order_number', $reference)->first();

                if (!$order) {
                    Log::error("Paystack Webhook: Order not found for reference: {$reference}");
                    DB::rollBack();
                    return response()->json(['message' => 'Order not found'], 404);
                }

                // Check if the order is already paid or being processed
                if ($order->payment_status === 'paid') {
                    Log::info("Paystack Webhook: Order {$order->id} already marked as paid. Ignoring duplicate webhook.");
                    DB::commit(); // Still commit to acknowledge the webhook
                    return response()->json(['message' => 'Order already paid'], 200);
                }
                $paystackVerification = $this->paystackService->verifyPayment($reference);

                $isVerificationSuccessful = isset($paystackVerification['status']) &&
                                        ($paystackVerification['status'] === true || $paystackVerification['status'] === 'success');

            if (!$isVerificationSuccessful) {
                    Log::error("Paystack Webhook: Direct payment verification failed for reference {$reference}. Paystack response: ", ['paystack_response' => $paystackVerification]);
                    $order->update([
                        'payment_status' => 'failed',
                        'order_status' => 'cancelled',
                        'notes' => 'Payment verification failed via webhook.',
                    ]);
                    DB::commit(); // Commit the status update for security logs
                    return response()->json(['message' => 'Payment verification failed'], 400);
                }

                 $expectedAmountInPesewas = (int)($order->order_total * 100);
                $actualAmountInPesewas = (int)$paystackVerification['amount']; // This is already in kobo/pesewas from verifyPayment
                $verifiedCurrency = $paystackVerification['currency'];

                if ($expectedAmountInPesewas !== $actualAmountInPesewas || $verifiedCurrency !== 'GHS') {
                    Log::error("Paystack Webhook: Amount or currency mismatch for order {$order->id}. Expected {$order->order_total} GHS ({$expectedAmountInPesewas} pesewas), got {$actualAmountInPesewas} {$verifiedCurrency} (verified).");
                    $order->update([
                        'payment_status' => 'amount_mismatch',
                        'notes' => 'Payment amount/currency mismatch via webhook.',
                        'order_status' => 'cancelled',
                    ]);
                    DB::commit(); // Commit the transaction to save the amount_mismatch status
                    return response()->json(['message' => 'Amount or currency mismatch'], 400);
                }

                // Update order status based on Paystack response
                $order->update([
                    'payment_status' => 'paid',
                    'order_status' => 'processing', // Move to processing after payment
                    'payment_gateway_transaction_id' => $paystackVerification['id'],
                    'payment_details' => json_encode($paystackVerification),
                ]);
                Log::info("Paystack Webhook: Payment successful for order {$order->id}. Status updated to paid.");
                
                DB::commit();
                return response()->json(['message' => 'Webhook processed successfully'], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Paystack Webhook: Error processing charge.success event for reference {$reference}: " . $e->getMessage(), [
                    'exception' => $e->getTraceAsString(),
                    'event_data' => $event,
                ]);
                return response()->json(['message' => 'Internal server error processing webhook'], 500);
            }
        }

        // Handle other event types if necessary (e.g., 'transfer.success', 'refund.success')
        Log::info('Paystack Webhook: Event type not handled.', ['event_type' => $event['event']]);
        return response()->json(['message' => 'Event type not handled'], 200);
    }
}
    