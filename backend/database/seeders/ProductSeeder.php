<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       // Fetch categories for linking (as before)
        $organicVegetables = Category::where('slug', Str::slug('Organic Vegetables'))->firstOrFail();
        $milkAndCheese = Category::where('slug', Str::slug('Milk & Cheese'))->firstOrFail();
        $redMeat = Category::where('slug', Str::slug('Red Meat'))->firstOrFail();
        $freshFruits = Category::where('slug', Str::slug('Fresh Fruits'))->firstOrFail();
        $poultry = Category::where('slug', Str::slug('Poultry'))->firstOrFail();
        $cerealAndGrains = Category::where('slug', Str::slug('Cereal & Grains'))->firstOrFail();


        // --- Products ---

        // Organic Vegetables
        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Organic Spinach',
            'slug' => Str::slug('Organic Spinach'),
            'short_description' => 'Freshly harvested organic spinach.',
            'description' => 'Locally grown, pesticide-free organic spinach. Great for salads, smoothies, or sautéing.',
            'price_per_unit' => 3.99,
            'unit_of_measure' => 'bunch',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 150.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'OVSPIN001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://picsum.photos/id/26/600/400', 'is_main_image' => true], // <<< CHANGED: image_url, is_main_image
            ['image_url' => 'https://picsum.photos/id/27/600/400', 'is_main_image' => false], // <<< CHANGED: image_url, is_main_image
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Organic Carrots (1kg)',
            'slug' => Str::slug('Organic Carrots 1kg'),
            'short_description' => 'Sweet and crunchy organic carrots.',
            'description' => 'A kilogram of organic carrots, perfect for juicing, cooking, or snacking.',
            'price_per_unit' => 2.50,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 200.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'OVCARR002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://picsum.photos/id/28/600/400', 'is_main_image' => true], // <<< CHANGED
        ]);

        // Milk & Cheese
        $product = Product::create([
            'category_id' => $milkAndCheese->id,
            'name' => 'Fresh Whole Milk (1L)',
            'slug' => Str::slug('Fresh Whole Milk 1L'),
            'short_description' => 'Farm-fresh whole milk.',
            'description' => 'Pasteurized and homogenized whole milk, delivered fresh daily. Rich in calcium.',
            'price_per_unit' => 1.80,
            'unit_of_measure' => 'liter',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 300.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'DWMILK001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://picsum.photos/id/36/600/400', 'is_main_image' => true], // <<< CHANGED
        ]);

        $product = Product::create([
            'category_id' => $milkAndCheese->id,
            'name' => 'Cheddar Cheese Block (250g)',
            'slug' => Str::slug('Cheddar Cheese Block 250g'),
            'short_description' => 'Sharp and creamy cheddar cheese.',
            'description' => 'A versatile block of aged cheddar cheese, perfect for sandwiches, grating, or snacking.',
            'price_per_unit' => 5.99,
            'unit_of_measure' => 'piece',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 80.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'DWCCHD002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://picsum.photos/id/37/600/400', 'is_main_image' => true], // <<< CHANGED
        ]);


        // Fresh Animal Proteins (Red Meat)
        $product = Product::create([
            'category_id' => $redMeat->id,
            'name' => 'Grass-Fed Beef Mince (500g)',
            'slug' => Str::slug('Grass-Fed Beef Mince 500g'),
            'short_description' => 'Lean grass-fed beef mince.',
            'description' => 'Premium quality grass-fed beef mince, ideal for burgers, bolognese, or meatballs.',
            'price_per_unit' => 8.75,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 70.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'APRFM001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://picsum.photos/id/46/600/400', 'is_main_image' => true], // <<< CHANGED
        ]);

        // Fresh Fruits
        $product = Product::create([
            'category_id' => $freshFruits->id,
            'name' => 'Organic Apples (1kg)',
            'slug' => Str::slug('Organic Apples 1kg'),
            'short_description' => 'Crisp and sweet organic apples.',
            'description' => 'A kilogram of delicious, organic apples, perfect for snacking or baking.',
            'price_per_unit' => 3.20,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 180.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'FFAPP001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://picsum.photos/id/23/600/400', 'is_main_image' => true], // <<< CHANGED
        ]);

        // Poultry
        $product = Product::create([
            'category_id' => $poultry->id,
            'name' => 'Chicken Breast Fillets (500g)',
            'slug' => Str::slug('Chicken Breast Fillets 500g'),
            'short_description' => 'Lean and tender chicken breast.',
            'description' => '500g pack of boneless, skinless chicken breast fillets, ideal for grilling or stir-fries.',
            'price_per_unit' => 6.50,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 120.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'APCHICK001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://picsum.photos/id/47/600/400', 'is_main_image' => true], // <<< CHANGED
        ]);

        // Cereal & Grains
        $product = Product::create([
            'category_id' => $cerealAndGrains->id,
            'name' => 'Rolled Oats (1kg)',
            'slug' => Str::slug('Rolled Oats 1kg'),
            'short_description' => 'Hearty rolled oats for breakfast.',
            'description' => 'A kilogram of whole grain rolled oats, perfect for oatmeal, granola, or baking.',
            'price_per_unit' => 2.99,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 90.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'CGROATS001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://picsum.photos/id/38/600/400', 'is_main_image' => true], // <<< CHANGED
        ]);
    }
}
