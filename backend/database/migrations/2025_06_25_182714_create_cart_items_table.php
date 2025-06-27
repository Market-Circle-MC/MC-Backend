<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
             // Foreign key to the carts table, cascading delete if cart is removed
            $table->foreignId('cart_id')->constrained('carts')->onDelete('cascade');
            // Foreign key to the products table, restricting delete if product is in an active cart
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('quantity', 10, 2)->default(1.00); // Quantity of the product in the cart
            // Price and unit at the time of addition, important for historical accuracy as product prices can change
            $table->decimal('price_per_unit_at_addition', 10, 2);
            $table->string('unit_of_measure_at_addition', 50);
            $table->decimal('line_item_total', 12, 2); // Calculated total for this specific item (quantity * price_at_addition)
            $table->timestamps();

            // Ensure a unique product per cart item (composite unique index for cart_id and product_id)
            $table->unique(['cart_id', 'product_id']);
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
