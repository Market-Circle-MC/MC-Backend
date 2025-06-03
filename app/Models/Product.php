<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ProductImage;
use App\Models\Category;

class Product extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'price_per_unit',
        'unit_of_measure',
        'min_order_quantity',
        'stock_quantity',
        'is_featured',
        'is_active',
        'sku',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price_per_unit' => 'decimal:2', // Cast to decimal with 2 places
        'min_order_quantity' => 'decimal:2', // Cast to decimal with 2 places
        'stock_quantity' => 'decimal:2', // Cast to decimal with 2 places
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'main_image_url', // Add the accessor for main image URL
    ];

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    /**
     * Get the main image for the product.
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include featured products.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include products with stock greater than 0.
     */
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /**
     * Accessor to get the URL of the main product image.
     *
     * @return string|null/
     */
    public function getMainImageUrlAttribute()
    {
        // Try to find an image explicitly marked as main
        $mainImage = $this->images->firstWhere('is_main_image', true);

        // If no main image is explicitly set, take the first available image
        if (!$mainImage) {
            $mainImage = $this->images->first();
        }

        // If an image is found, return its full public URL
        if ($mainImage) {
            return asset('storage/' . $mainImage->image_url);
        }

        // Return null if no images are associated with the product
        return null; // Or a placeholder URL like asset('images/placeholder.jpg')
    }

}
