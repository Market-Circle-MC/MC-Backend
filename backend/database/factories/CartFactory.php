<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cart>
 */
class CartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), // Automatically creates a user if not provided
            'status' => $this->faker->randomElement(['active', 'abandoned']),
            // total_amount is calculated based on cart items, so not set here
        ];
    }

    /**
     * Indicate that the cart is active.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function active(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
            ];
        });
    }

    /**
     * Indicate that the cart is abandoned.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function abandoned(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'abandoned',
            ];
        });
    
    }
}
