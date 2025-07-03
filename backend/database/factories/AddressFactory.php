<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Customer;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer = Customer::factory()->create();

        return [
            'customer_id' => $customer->id,
            'address_line1' => $this->faker->streetAddress(),
            'address_line2' => $this->faker->secondaryAddress(),
            'city' => $this->faker->city(),
            'region' => $this->faker->state(), // Using faker's state for region
            'country' => 'Ghana', // Default as per migration
            'ghanapost_gps_address' => 'GA-' . $this->faker->randomNumber(3, true) . '-' . $this->faker->randomNumber(4, true),
            'digital_address_description' => $this->faker->sentence(3),
            'is_default' => $this->faker->boolean(20), // 20% chance of being default
            'delivery_instructions' => $this->faker->paragraph(1),
        ];
    }

    /**
     * Indicate that the address is the default one.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function default(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_default' => true,
            ];
        });
    }
}

