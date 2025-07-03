<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Cart;
use App\Models\Product;
use App\Models\CartItem;
use Ramsey\Uuid\Type\Decimal;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 100, 'price_per_unit' => $this->faker->randomFloat(2, 10, 500)]);
        $quantity = $this->faker->randomFloat(2, 1, 5);
        // Calculate line_item_total and round it here too for consistency
        $lineItemTotal = round($quantity * $product->current_price, 2);

        return [
            'cart_id' => Cart::factory(), // Automatically creates a cart
            'product_id' => $product->id,
            'product_name' => $product->name, // Snapshot from product
            'price_per_unit_at_addition' => $product->current_price, // Use accessor for current price
            'unit_of_measure_at_addition' => $product->unit_of_measure, // Snapshot from product
            'quantity' => $quantity,
            'line_item_total' => $lineItemTotal, // Calculated
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (CartItem $cartItem) {
            // Ensure line_item_total is calculated if not explicitly set
            if (is_null($cartItem->line_item_total)) {
                // Explicitly cast both operands to float before multiplication
                $quantity = $cartItem->quantity;
                $price = $cartItem->price_per_unit_at_addition;
                $cartItem->line_item_total = round($quantity * $price, 2);
            }
        })->afterCreating(function (CartItem $cartItem) {
            // Optionally update cart total after creating item, if cart has a total field
            // This is handled by Cart model observers or when retrieving cart
        });
    
    }
}
