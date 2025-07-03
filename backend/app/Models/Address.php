<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Customer;

class Address extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'address_line1',
        'address_line2',
        'city',
        'region',
        'country',
        'ghanapost_gps_address',
        'digital_address_description',
        'is_default',
        'delivery_instructions',
    ];

    /**
     * Get the customer that owns the address.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the orders associated with the address.
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'delivery_address_id');    
    }
}
