<?php

namespace Database\Factories;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
       $name = $this->faker->unique()->words(3, true) . ' Product';
        return [
            'category_id' => Category::factory(), // Create a category if one doesn't exist
            'name' => $name,
            'slug' => Str::slug($name),
            'short_description' => $this->faker->sentence(10),
            'description' => $this->faker->paragraph(),
            'price_per_unit' => $this->faker->randomFloat(2, 0.50, 500.00),
            'unit_of_measure' => $this->faker->randomElement(['kg', 'piece', 'liter', 'pack', 'dozen']),
            'min_order_quantity' => $this->faker->randomFloat(2, 0.1, 5.0),
            'stock_quantity' => $this->faker->numberBetween(0, 1000),
            'is_featured' => $this->faker->boolean(20), // 20% chance of being featured
            'is_active' => $this->faker->boolean(90),   // 90% chance of being active
            'sku' => Str::upper(Str::random(3)) . '-' . Str::random(6), // Unique SKU
        ];
    }
}
