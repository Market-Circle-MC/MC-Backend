<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
