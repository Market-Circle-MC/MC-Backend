<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'order_number',
        'delivery_address_id',
        'delivery_option_id',
        'order_total',
        'payment_method',
        'payment_status',
        'payment_gateway_transaction_id',
        'payment_details',
        'order_status',
        'notes',
        'ordered_at',
        'dispatched_at',
        'delivery_tracking_number',
        'delivery_service',
        'delivered_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'order_total' => 'float',
        'payment_details' => 'array',
        'ordered_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($order) {
            // Generate a unique order number before saving
            if (empty($order->order_number)) {
                $order->order_number = 'MC-ORD-' . now()->format('YmdHis') . Str::random(6);
            }
        });

    }

    /**
     * Get the customer that placed the order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the order items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the delivery option for the order.
     */
    public function deliveryOption(): BelongsTo
    {
        return $this->belongsTo(DeliveryOption::class);
    }

    /**
     * Get the delivery address for the order.
     */
    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }
    /**
     * Get the address snapshots for the order.
     */
    public function addressSnapshots(): HasMany
    {
        return $this->hasMany(OrderAddressSnapshot::class);
    }

    /**
     * Get the shipping address snapshot for the order.
     * This will typically be one of the addressSnapshots related to this order.
     */
    public function shippingAddressSnapshot() 
    {
        return $this->addressSnapshots()->where('address_type', 'Shipping')->first();
    }

    /**
     * Get the billing address snapshot for the order.
     * This will typically be one of the addressSnapshots related to this order.
     */
    public function billingAddressSnapshot() 
    {
        return $this->addressSnapshots()->where('address_type', 'Billing')->first();
    }
}
