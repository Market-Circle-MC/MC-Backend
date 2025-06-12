<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $countryCode = fake()->numberBetween(1, 999);
        $subscriberDigitsCount = fake()->numberBetween(7, 10); 
        $subscriberNumber = fake()->unique()->numerify(str_repeat('#', $subscriberDigitsCount));
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone_number' => '+' . $countryCode . $subscriberNumber, // Generates a phone number with a random country code
            'role' => 'customer', // Default role for new users
        ];
    }

    /**
     * Indicate that the user is an admin.
     * 
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function admin(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'role' => 'admin',
            ];
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a customer.
     * 
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    
}
