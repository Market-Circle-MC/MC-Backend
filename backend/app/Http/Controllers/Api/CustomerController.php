<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;


class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        if (Auth::user() && Auth::user()->id !== $customer->user_id) {
            abort(403, 'Unauthorized action. You can only view your own profile' ); // Returns 403 Forbidden
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
     */
    public function destroy(string $id)
    {
        //
    }
}
