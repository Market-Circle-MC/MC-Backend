<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;


class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *  Authenticated User: Returns their own customer profile (if exists).
     * Admin User: Returns a list of ALL customer profiles.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->role === 'admin') {
            // Admin user: return all customer profiles
            $customers = Customer::paginate(15);
            return response()->json([
                'message' => 'All customer profiles retrieved successfully',
                'customers' => $customers
            ], 200);
        } else {
            // Regular user can only see their own profile.
            $customer = $user->customer; // Access the one-to-one relationship

            if ($customer) {
                return response()->json([
                    'message' => 'Your customer profile retrieved successfully',
                    'customers' => [$customer]
                ], 200);
            }

            // No customer profile found for the regular authenticated user.
            return response()->json([
                'message' => 'No customer profile found for the authenticated user',
                'customers' => []
            ], 200);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        return response()->json([
            'message' => 'Customer profile created successfully',
            'customer' => $customer
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $user = Auth::user();
        if ($user && ($user->role !== 'admin' && $user->id !== $customer->user_id)) {
            abort(403, 'Unauthorized action. You can only view your own customer profile.' ); // Returns 403 Forbidden
        }
        return response()->json([
            'message' => 'Customer profile retrieved successfully',
            'customer' => $customer
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return response()->json([
            'message' => 'Customer profile updated successfully',
            'customer' => $customer
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     * Authenticated User: Can only delete their own customer profile.
     * Admin User: Can delete any customer profile
     * 
     * @param \App\Models\Customer $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Customer $customer)
    {
        $user = Auth::user();

        if ($user && ($user->role !== 'admin' && $user->id !== $customer->user_id)) {
            abort (403, 'Unauthorized action. You cannot delete customer profile.');
        }
        $customer->delete();
        return response()->json([
            'message' => 'Customer profile deleted successfully.'
        ], 200);

    }
}
