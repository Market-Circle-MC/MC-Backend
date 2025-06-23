<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_url',
        'is_main_image',
    ];

    protected $casts = [
        'is_main_image' => 'boolean',
    ];

    /**
     * The product that this image belongs to.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    /**
     * The "booted" method of the model.
     *
     * This is where you register model event listeners.
     */
    protected static function boot()
    {
        parent::boot();

        // When a ProductImage model is being deleted, delete the associated file from storage.
        static::deleting(function ($image) {
            // Check if image_url exists and is not empty
            if ($image->image_url) {
                // The image_url stored in the database should be the relative path
                // e.g., 'products/some-image.jpg'
                if (Storage::disk('public')->exists($image->image_url)) {
                    Storage::disk('public')->delete($image->image_url);
                }
                Log::info("Deleted product image file: {$image->image_url}");
            }
        });
    }
}
