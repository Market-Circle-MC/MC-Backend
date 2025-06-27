<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\CartItem;


class Cart extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'status',
    ];

    /**
     * The accessors that should be appended to the model's array form.
     */
    protected $appends = [
        'total'
        ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'user_id' => 'integer'
    ];
    
        
    /**
     * Get the user that owns the Cart.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the cart items for the Cart.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the total price of the cart.
     * This is an accessor that calculates the total dynamically.
     *
     * @return float
     */
    public function getTotalAttribute(): float
    {
        // Sum the line_item_total of all active cart items
        return $this->items->sum('line_item_total');
    }

}
