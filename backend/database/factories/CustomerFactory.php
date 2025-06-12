<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Customer;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customerType = $this->faker->randomElement(['restaurant', 'family', 'individual_bulk']);
        $isRestaurant = ($customerType === 'restaurant');
        return [
            'user_id' => User::factory(),
            'customer_type' => $customerType,
            'business_name' => $isRestaurant ? $this->faker->company() : null,
            'ghanapost_gps_address' => $this->faker->regexify('([A-Z]{2}-\d{3}-\d{4})'),
            'digital_address' => $this->faker->address(),
            'tax_id' => $isRestaurant ? $this->faker->regexify('[PCGQVA-Z]{3,4}\d{8,9}[A-Z0-9]?') : null, // Loosened regex for faker
            'contact_person_name' => $this->faker->name(),
            'contact_person_phone' => $this->faker->phoneNumber(),
        ];
    }
    /**
     * Indicate that the customer is a restaurant.
     * Useful for creating specific types of customers in tests.
     */
    public function restaurant(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'customer_type' => 'restaurant',
                'business_name' => $this->faker->company(),
                'tax_id' => $this->faker->regexify('C00\d{8}'), // Example GRA TIN
            ];
        });
    }

    /**
     * Indicate that the customer is a family.
     */
    public function family(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'customer_type' => 'family',
                'business_name' => null,
                'tax_id' => null,
            ];
        });
    }
    /**
     * Indicate that the customer is an individual bulk purchaser.
     * This customer type also does not require business_name or tax_id.
     */
    public function individualBulk(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'customer_type' => 'individual_bulk',
                'business_name' => null,
                'tax_id' => null,
            ];
        });
    }
    
}
