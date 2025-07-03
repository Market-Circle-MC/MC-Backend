<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Address;
use App\Models\DeliveryOption;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer = Customer::factory()->create();
        $user = $customer->user()->create([
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => 'password',
            'phone_number' => $this->faker->phoneNumber, // Crucial for snapshot
            'role' => 'customer',
        ]);

        $deliveryAddress = Address::factory()->for($customer)->create([
            'address_line1' => $this->faker->streetAddress,
            'address_line2' => $this->faker->secondaryAddress,
            'city' => $this->faker->city,
            'region' => $this->faker->state,
            'country' => $this->faker->country,
            'ghanapost_gps_address' => 'GA-123-4567', // Example or use faker for similar format
            'digital_address_description' => $this->faker->sentence,
            'delivery_instructions' => $this->faker->sentence,
        ]);
        $deliveryOption = DeliveryOption::factory()->active()->create();

        return [
            'customer_id' => $customer->id,
            'delivery_address_id' => $deliveryAddress->id, // Assign the ID of the created address
            'delivery_option_id' => $deliveryOption->id,
            'order_total' => $this->faker->randomFloat(2, 50, 500),
            'payment_method' => $this->faker->randomElement(['Card', 'Cash on Delivery']),
            'payment_status' => $this->faker->randomElement(['unpaid', 'pending_gateway_payment', 'paid']),
            'order_status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'cancelled']),
            'notes' => $this->faker->sentence,
            'ordered_at' => now(),
            'payment_gateway_transaction_id' => null,
        ];
    }
    

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            // Eager load necessary relationships for the snapshots
            $order->loadMissing('customer.user', 'deliveryAddress');

            // Ensure deliveryAddress and customer are not null
            if (!$order->deliveryAddress || !$order->customer || !$order->customer->user) {
                // This indicates a deeper factory setup issue if it happens
                throw new \Exception("Order Factory: Missing deliveryAddress, customer, or user during afterCreating callback.");
            }

            $user = $order->customer->user;
            $deliveryAddress = $order->deliveryAddress;

            // Create Shipping Address Snapshot
            $order->addressSnapshots()->create([
                'address_type' => 'Shipping',
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
            ]);

            // Create Billing Address Snapshot (using delivery address data as per your explanation)
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
            ]);

            // Optionally create order items too, if you want orders to always have items
            // This part is fine as is, assuming your OrderItem factory/logic is correct.
        });
    }
}
