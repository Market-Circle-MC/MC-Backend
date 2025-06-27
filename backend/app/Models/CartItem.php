<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'price_per_unit_at_addition',
        'unit_of_measure_at_addition',
        'line_item_total',
    ];

    

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:2',
        'price_per_unit_at_addition' => 'decimal:2',
        'line_item_total' => 'decimal:2',
    ];

    /**
     * Get the cart that this item belongs to.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the product associated with this cart item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Boot method to set up model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically calculate line_item_total before saving
        static::saving(function ($cartItem) {
            $cartItem->line_item_total = $cartItem->quantity * $cartItem->price_per_unit_at_addition;
        });
    }
}
