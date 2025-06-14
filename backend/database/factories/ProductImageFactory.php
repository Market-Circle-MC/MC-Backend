<?php

namespace Database\Factories;

use App\Models\ProductImage;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProductImage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $imageName = 'products/' . $this->faker->uuid() . '.jpg';
        
        $imageUrl = Storage::url($imageName);

        return [
            'product_id' => Product::factory(), // Create a product if one doesn't exist
            'image_url' => $imageUrl,
            'is_main_image' => $this->faker->boolean(10), // 10% chance of being main image
        ];
    }
}
