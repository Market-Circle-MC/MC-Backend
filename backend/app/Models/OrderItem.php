<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Order;
use App\Models\Product;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'price_per_unit_at_purchase',
        'unit_of_measure_at_purchase',
        'line_item_total',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'float',
        'price_per_unit_at_purchase' => 'float',
        'line_item_total' => 'float',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::saving(function ($item) {
            // Calculate line_item_total before saving
            $item->line_item_total = $item->quantity * $item->price_per_unit_at_purchase;
        });
    }

    /**
     * Get the order that the item belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product associated with the order item.
     * This can be null if the original product was deleted.
     */
    public function product(): BelongsTo
    {
        // product_id is NOT NULL in migration, so product should always exist
        return $this->belongsTo(Product::class);
    }
}
