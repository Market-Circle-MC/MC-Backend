<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryOption>
 */
class DeliveryOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Standard Delivery',
                'Express Delivery',
                'Pickup at Store',
                'Next Day Delivery',
                'Same Day Delivery',
                'Local Pickup',
                'International Shipping',
            ]),
            'description' => $this->faker->sentence(),
            'cost' => $this->faker->randomFloat(2, 0, 50), // Cost between 0 and 50 GHS
            'min_delivery_days' => $this->faker->numberBetween(1, 3),
            'max_delivery_days' => $this->faker->numberBetween(4, 7),
            'is_active' => $this->faker->boolean,
        ];
    }

    /**
     * Indicate that the delivery option is active.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => true,
            ];
        });
    }

    /**
     * Indicate that the delivery option is inactive.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }
}
