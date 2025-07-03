<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Customer;
use App\Models\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthorized. Please log in to view addresses.',
            ], 401);
        }

        $user = Auth::user();
        $addresses = collect();
        
       if ($user->role === 'admin') {
            // Admin can view all addresses
            $addresses = Address::with('customer.user')->get();
        } elseif ($user->role === 'customer') {
            // Customer can only view their own addresses
            // Ensure the user has a customer profile before trying to access it
            if ($user->customer) {
                $addresses = $user->customer->addresses()->orderBy('is_default', 'desc')->get();
            } else {
                // If a user with 'customer' role somehow doesn't have a customer profile,
                // you might want to log this or return an appropriate error.
                return response()->json(['message' => 'Customer profile not found for this user.'], 403);
            }
        } else {
            // For any other unexpected roles, deny access
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'message' => 'Addresses retrieved successfully.',
            'data' => $addresses
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAddressRequest $request)
    {
        $user = Auth::user();
        $customer = $user->customer;

        // Authorization is handled by StoreAddressRequest, but we still need customer check here
        if (!$customer) {
            return response()->json([
                'message' => 'Customer profile not found. Cannot add address.',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $data = $request->validated(); // Get validated data
            $data['customer_id'] = $customer->id;

            // If new address is set as default, unset existing default addresses for this customer
            if (isset($data['is_default']) && $data['is_default']) {
                $customer->addresses()->where('is_default', true)->update(['is_default' => false]);
            } elseif ($customer->addresses()->count() === 0) {
                // If this is the very first address, make it default automatically
                $data['is_default'] = true;
            }

            $address = Address::create($data);

            DB::commit();

            return response()->json([
                'message' => 'Address created successfully.',
                'data' => $address,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create address: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to create address. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Address $address)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();

        // Ensure the user owns the address or is an admin
        if ($user->role !== 'admin' && $address->customer->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to view this address.',
            ], 403);
        }

        return response()->json([
            'message' => 'Address retrieved successfully.',
            'data' => $address,
        ]);
    
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAddressRequest $request, Address $address)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();

        // Ensure the user owns the address or is an admin
        if ($user->role !== 'admin' && $address->customer->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to update this address.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $data = $request->validated(); // Get validated data

            // If this address is being set as default, unset existing default addresses for this customer
            if (isset($data['is_default']) && $data['is_default']) {
                $address->customer->addresses()->where('is_default', true)->update(['is_default' => false]);
            }

            $address->update($data);

            DB::commit();

            return response()->json([
                'message' => 'Address updated successfully.',
                'data' => $address,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update address: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to update address. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Address $address)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

         $user = Auth::user();

        // Ensure the user owns the address or is an admin
        if ($user->role !== 'admin' && $address->customer->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to delete this address.',
            ], 403);
        }

        // Prevent deleting an address if it's currently used in an order (delivery_address_id)
        // The foreign key constraint on orders table (onDelete('restrict')) should handle this,
        // but an explicit check provides a friendlier error message.
        if ($address->orders()->exists()) {
            return response()->json([
                'message' => 'Cannot delete address as it is linked to existing orders.',
            ], 409); // Conflict
        }

        DB::beginTransaction();
        try {
            $address->delete();

            // If the deleted address was the default, and other addresses exist, set a new default
            $customer = $address->customer;
            if ($customer->addresses()->where('is_default', true)->doesntExist() && $customer->addresses()->count() > 0) {
                $customer->addresses()->first()->update(['is_default' => true]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Address deleted successfully.',
            ]);

        } catch (\Exception | \Throwable $e) { // Catch Throwable for more general exceptions
            DB::rollBack();
            Log::error("Failed to delete address: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to delete address. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
}
