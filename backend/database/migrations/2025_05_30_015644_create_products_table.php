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
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // Auto-incrementing Primary Key
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict'); // Foreign Key to categories table
            $table->string('name', 255)->unique(); // Product name, unique
            $table->string('slug', 255)->unique(); // URL-friendly unique identifier
            $table->string('short_description', 500)->nullable(); // Brief summary
            $table->text('description')->nullable(); // Detailed description
            $table->decimal('price_per_unit', 10, 2); // Price based on unit, e.g., 5.50
            $table->string('unit_of_measure', 50); // e.g., 'kg', 'piece'
            $table->decimal('min_order_quantity', 10, 2)->default(1.00); // Minimum order quantity
            $table->decimal('stock_quantity', 10, 2); // Current inventory level
            $table->string('image_url', 255)->nullable(); // URL to product image
            $table->boolean('is_featured')->default(false); // Flag for featured products
            $table->boolean('is_active')->default(true); // Flag for product availability
            $table->string('sku', 100)->nullable()->unique(); // Stock Keeping Unit, unique
            $table->timestamps();


            // Indexes (already handled by unique() and foreignId()
            // $table->index('name'); // For efficient searching/sorting by name
            // $table->index('is_active'); // For quickly filtering active products
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
