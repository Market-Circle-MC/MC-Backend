<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- Parent Categories ---
        $freshProducts = Category::firstOrCreate(['slug' => Str::slug('Fresh Products')], [
            'name' => 'Fresh Products',
            'description' => 'Direct from farm to table.',
            'image_url' => 'https://images.pexels.com/photos/2255935/pexels-photo-2255935.jpeg?auto=compress&cs=tinysrgb&w=600',
            'is_active' => true,
        ]);

        $dairyBreakfast = Category::firstOrCreate(['slug' => Str::slug('Dairy & Breakfast')], [
            'name' => 'Dairy & Breakfast',
            'description' => 'Start your day right with our selection.',
            'image_url' => 'https://www.vecteezy.com/photo/24529899-dairy-products-on-rustic-wooden-table-illustration-ai-generative',
            'is_active' => true,
        ]);

        $animalProteins = Category::firstOrCreate(['slug' => Str::slug('Animal Proteins')], [
            'name' => 'Animal Proteins',
            'description' => 'High-quality meats and seafood.',
            'image_url' => 'https://picsum.photos/id/40/400/300?grayscale',
            'is_active' => true,
        ]);

        $pantryStaples = Category::firstOrCreate(['slug' => Str::slug('Pantry Staples')], [
            'name' => 'Pantry Staples',
            'description' => 'Everyday essentials for your kitchen.',
            'image_url' => 'https://picsum.photos/id/50/400/300?grayscale',
            'is_active' => true,
        ]);

        // --- Child Categories (linking to parents) ---
        Category::firstOrCreate(['slug' => Str::slug('Organic Vegetables')], [
            'name' => 'Organic Vegetables',
            'description' => 'Naturally grown, fresh vegetables.',
            'parent_id' => $freshProducts->id,
            'image_url' => 'https://picsum.photos/id/21/400/300?grayscale',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Fresh Fruits')], [
            'name' => 'Fresh Fruits',
            'description' => 'Juicy and seasonal fruits.',
            'parent_id' => $freshProducts->id,
            'image_url' => 'https://picsum.photos/id/22/400/300?grayscale',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Milk & Cheese')], [
            'name' => 'Milk & Cheese',
            'description' => 'A wide range of dairy products.',
            'parent_id' => $dairyBreakfast->id,
            'image_url' => 'https://picsum.photos/id/31/400/300?grayscale',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Cereal & Grains')], [
            'name' => 'Cereal & Grains',
            'description' => 'Healthy options for a great start.',
            'parent_id' => $dairyBreakfast->id,
            'image_url' => 'https://picsum.photos/id/32/400/300?grayscale',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Red Meat')], [
            'name' => 'Red Meat',
            'description' => 'Premium cuts of beef, lamb, and pork.',
            'parent_id' => $animalProteins->id,
            'image_url' => 'https://picsum.photos/id/41/400/300?grayscale',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Poultry')], [
            'name' => 'Poultry',
            'description' => 'Fresh chicken, turkey, and duck.',
            'parent_id' => $animalProteins->id,
            'image_url' => 'https://picsum.photos/id/42/400/300?grayscale',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Seafood')], [
            'name' => 'Seafood',
            'description' => 'Freshly caught fish and shellfish.',
            'parent_id' => $animalProteins->id,
            'image_url' => 'https://picsum.photos/id/43/400/300?grayscale',
            'is_active' => true,
        ]);
    }
}
