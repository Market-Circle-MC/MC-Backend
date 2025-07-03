<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Order;

class DeliveryOption extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'cost',
        'min_delivery_days', // Added as per migration
        'max_delivery_days', // Added as per migration
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'cost' => 'float',
        'min_delivery_days' => 'integer', // Cast as integer
        'max_delivery_days' => 'integer', // Cast as integer
        'is_active' => 'boolean',
    ];

    /**
     * Get the orders that use this delivery option.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
