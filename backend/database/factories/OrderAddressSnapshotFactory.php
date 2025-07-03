<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderAddressSnapshot>
 */
class OrderAddressSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Ensure an order exists to link the snapshot to.
        $order = Order::factory()->create();

        return [
            'order_id' => $order->id,
            'address_type' => $this->faker->randomElement(['Shipping', 'Billing']),
            'recipient_name' => $this->faker->name(),
            'phone_number' => $this->faker->phoneNumber(),
            'address_line1' => $this->faker->streetAddress(),
            'address_line2' => $this->faker->secondaryAddress(),
            'city' => $this->faker->city(),
            'region' => $this->faker->state(),
            'country' => 'Ghana',
            'ghanapost_gps_address' => 'GA-' . $this->faker->randomNumber(3, true) . '-' . $this->faker->randomNumber(4, true),
            'digital_address_description' => $this->faker->sentence(3),
            'delivery_instructions' => $this->faker->paragraph(1),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'), // Manually set as timestamps are off
        ];
    }
}
