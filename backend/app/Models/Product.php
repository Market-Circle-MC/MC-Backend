<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ProductImage;
use App\Models\Category;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
        'discount_price',
        'discount_percentage',
        'discount_start_date',
        'discount_end_date',
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
        'discount_price' => 'decimal:2', // Cast to decimal with 2 places
        'min_order_quantity' => 'decimal:2', // Cast to decimal with 2 places
        'stock_quantity' => 'decimal:2', // Cast to decimal with 2 places
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'discount_start_date' => 'datetime',
        'discount_end_date' => 'datetime',
    ];

    protected $appends = [
        'main_image_url', // Add the accessor for main image URL
        'current_price', // Add the accessor for current price
        'is_discounted', // Add the accessor to check if the product is discounted
        'discount_status', // Add the accessor for discount status
    ];

    // Boot method to register model events
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($product) {
            $pricePerUnit = (float) $product->price_per_unit;

            // 1. Ensure discount fields are cleared if base price is invalid
            if ($pricePerUnit <= 0) {
                $product->discount_price = null;
                $product->discount_percentage = null;
                $product->discount_start_date = null;
                $product->discount_end_date = null;
                return;
            }

            // 2. Handle mutual exclusivity and calculation based on which field was dirtied in the request
            // If discount_percentage was specifically sent in this request (dirty)
            if ($product->isDirty('discount_percentage')) {
                $percentage = (float) $product->discount_percentage;
                if ($percentage > 0 && $percentage <= 100) {
                    $calculatedPrice = $pricePerUnit * (1 - ($percentage / 100));
                    $product->attributes['discount_price'] = round($calculatedPrice, 2); // Set attribute directly
                } else {
                    $product->attributes['discount_percentage'] = null; // Clear invalid percentage
                    $product->attributes['discount_price'] = null; // Clear corresponding fixed price
                }
            }
            // Else if discount_price was specifically sent in this request (dirty)
            elseif ($product->isDirty('discount_price')) {
                $fixedPrice = (float) $product->discount_price;
                if ($fixedPrice > 0 && $fixedPrice < $pricePerUnit) {
                    $calculatedPercentage = (($pricePerUnit - $fixedPrice) / $pricePerUnit) * 100;
                    $product->attributes['discount_percentage'] = round($calculatedPercentage, 2); // Set attribute directly
                } else {
                    $product->attributes['discount_price'] = null; // Clear invalid fixed price
                    $product->attributes['discount_percentage'] = null; // Clear corresponding percentage
                }
            }
            // If neither discount value was dirty, but one might have been set to null explicitly
            // (e.g., user removes discount by sending discount_price: null)
            elseif ($product->isDirty('discount_price') && $product->discount_price === null) {
                $product->attributes['discount_percentage'] = null;
            }
            elseif ($product->isDirty('discount_percentage') && $product->discount_percentage === null) {
                $product->attributes['discount_price'] = null;
            }

            // 3. Enforce date consistency and nullify discounts if dates are invalid
            $startDate = $product->discount_start_date;
            $endDate = $product->discount_end_date;

            // If discount_price OR discount_percentage are set (meaning a discount is intended)
            // but dates are missing or invalid, then clear the discounts and dates.
            if (($product->discount_price !== null || $product->discount_percentage !== null)) {
                // Scenario: Discount exists but dates are not fully defined or are invalid
                if (
                    ($startDate === null && $endDate !== null) || // End date without start date
                    ($startDate !== null && $endDate !== null && $startDate->greaterThan($endDate)) // End date before start date
                ) {
                    $product->attributes['discount_price'] = null;
                    $product->attributes['discount_percentage'] = null;
                    $product->attributes['discount_start_date'] = null;
                    $product->attributes['discount_end_date'] = null;
                }
            }
            // Scenario: Dates are explicitly cleared or not provided, clear discounts too if dates were meant for them
            elseif (($startDate === null && $product->isDirty('discount_start_date')) ||
                     ($endDate === null && $product->isDirty('discount_end_date'))) {
                // If either date is explicitly set to null, ensure discounts are also nullified
                // unless another discount field was explicitly set.
                if (!$product->isDirty('discount_price') && !$product->isDirty('discount_percentage')) {
                     $product->attributes['discount_price'] = null;
                     $product->attributes['discount_percentage'] = null;
                }
            }
        });
    }

     public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = Str::slug($value);
        if (empty($this->attributes['sku'])) {
            $this->attributes['sku'] = Str::upper(Str::substr(Str::slug($value), 0, 4)) . '-' . Str::upper(Str::random(6));
        }
    }

    /**
     * Accessor to get the current price of the product.
     *
     * @return float
     */
    public function getCurrentPriceAttribute()
    {
        $pricePerUnit = (float) $this->price_per_unit;

        // Only apply discount if it's currently active AND valid
        if ($this->isDiscountActive()) {
            if ($this->discount_percentage !== null && $this->discount_percentage > 0 && $this->discount_percentage <= 100) {
                return round($pricePerUnit * (1 - ((float)$this->discount_percentage / 100)), 2);
            } elseif ($this->discount_price !== null && $this->discount_price > 0 && $this->discount_price < $pricePerUnit) {
                return round($this->discount_price, 2);
            }
        }
        return round($pricePerUnit, 2);
    }

    // Accessor to check if product is discounted
    public function getIsDiscountedAttribute()
    {
        return $this->isDiscountActive();
    }

    // New: Check if the discount is currently active based on dates and values
    public function isDiscountActive(): bool
    {
        $now = Carbon::now();
        $hasValidDiscountValue = ($this->discount_percentage !== null && $this->discount_percentage > 0 && $this->discount_percentage <= 100) ||
                                 ($this->discount_price !== null && $this->discount_price > 0 && $this->discount_price < $this->price_per_unit);

        if (!$hasValidDiscountValue) {
            return false;
        }

        // Discount is active if start date is null or in the past/present
        // AND end date is null or in the future/present
        return ($this->discount_start_date === null || $this->discount_start_date->lessThanOrEqualTo($now)) &&
               ($this->discount_end_date === null || $this->discount_end_date->greaterThanOrEqualTo($now));
    }

    // New: Accessor for discount status (for user display)
    public function getDiscountStatusAttribute(): string
    {
        $now = Carbon::now();

        $hasValidDiscountValue = ($this->discount_percentage !== null && $this->discount_percentage > 0 && $this->discount_percentage <= 100) ||
                                 ($this->discount_price !== null && $this->discount_price > 0 && $this->discount_price < $this->price_per_unit);

        if (!$hasValidDiscountValue) {
            return 'none';
        }

        if ($this->discount_start_date === null && $this->discount_end_date === null) {
            // If no dates, and we have a discount value, it's considered active indefinitely
            return 'active';
        }

        if ($this->discount_start_date && $this->discount_start_date->greaterThan($now)) {
            return 'upcoming';
        }

        if ($this->discount_end_date && $this->discount_end_date->lessThan($now)) {
            return 'expired';
        }

        // If not upcoming or expired, and has valid value, it's active
        return 'active';
    }
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

