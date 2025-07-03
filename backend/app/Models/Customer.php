<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'customers';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'user_id',
        'customer_type',
        'business_name',
        'ghanapost_gps_address',
        'digital_address',
        'tax_id',
        'contact_person_name',
        'contact_person_phone',
    ];

    /**
     * Get the user that owns the customer profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the addresses for the customer.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the orders for the customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
    
}
