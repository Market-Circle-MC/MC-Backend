<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create(['is_active' => true, 'price_per_unit' => $this->faker->randomFloat(2, 10, 200)]);
        $quantity = $this->faker->numberBetween(1, 10);
        $priceAtPurchase = $product->current_price; // Use the current price from the product

        return [
            'order_id' => Order::factory(), // Automatically creates an order
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'price_per_unit_at_purchase' => $priceAtPurchase,
            'unit_of_measure_at_purchase' => $product->unit_of_measure,
            'line_item_total' => $quantity * $priceAtPurchase,
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (OrderItem $orderItem) {
            // Ensure line_item_total is calculated if not explicitly set
            if (is_null($orderItem->line_item_total)) {
                $orderItem->line_item_total = $orderItem->quantity * $orderItem->price_per_unit_at_purchase;
            }
        });
    }
}
