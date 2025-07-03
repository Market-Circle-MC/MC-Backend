<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryOptionRequest;
use App\Http\Requests\UpdateDeliveryOptionRequest;
use App\Models\DeliveryOption;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Illuminate\Contracts\Cache\Store;
use Illuminate\Http\Request;

class DeliveryOptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
         $user = Auth::user();

        if ($user && $user->role === 'admin') {
            $deliveryOptions = DeliveryOption::orderBy('name')->get();
            $message = 'All delivery options retrieved successfully.';
        } else {
            $deliveryOptions = DeliveryOption::where('is_active', true)->orderBy('name')->get();
            $message = 'Active delivery options retrieved successfully.';
        }

        return response()->json([
            'message' => $message,
            'data' => $deliveryOptions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDeliveryOptionRequest $request)
    {
       if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized to create delivery options.',
            ], 403);
        }
        DB::beginTransaction();
        try {
            $deliveryOption = DeliveryOption::create($request->validated()); // Get validated data
            DB::commit();

            return response()->json([
                'message' => 'Delivery option created successfully.',
                'data' => $deliveryOption,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create delivery option: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to create delivery option. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(DeliveryOption $deliveryOption)
    {
        $user = Auth::user();

        if ($user && $user->role === 'admin') {
            // Admin can view any delivery option
            return response()->json([
                'message' => 'Delivery option retrieved successfully.',
                'data' => $deliveryOption,
            ]);
        } elseif (!$deliveryOption->is_active) {
            // Non-admin users cannot view inactive delivery options
            return response()->json([
                'message' => 'Delivery option not found or is inactive.',
            ], 404);
        }

        return response()->json([
            'message' => 'Delivery option retrieved successfully.',
            'data' => $deliveryOption,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDeliveryOptionRequest $request, DeliveryOption $deliveryOption)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            // Return 403 for both unauthenticated and non-admin authenticated users
            return response()->json([
                'message' => 'Unauthorized to update delivery options.',
            ], 403);
        }
         DB::beginTransaction();
        try {
            $deliveryOption->update($request->validated()); // Get validated data
            DB::commit();

            return response()->json([
                'message' => 'Delivery option updated successfully.',
                'data' => $deliveryOption,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update delivery option: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to update delivery option. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeliveryOption $deliveryOption)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            // Return 403 for both unauthenticated and non-admin authenticated users
            return response()->json([
                'message' => 'Unauthorized to update delivery options.',
            ], 403);
        }
        if (method_exists($deliveryOption, 'orders') && $deliveryOption->orders()->exists()) {
            return response()->json([
                'message' => 'Cannot delete delivery option as it is linked to existing orders.',
            ], 409); // Conflict
        }

        DB::beginTransaction();
        try {
            $deliveryOption->delete();
            DB::commit();

            return response()->json([
                'message' => 'Delivery option deleted successfully.',
            ]);

        } catch (\Exception | \Throwable $e) { // Catch Throwable for more general exceptions
            DB::rollBack();
            Log::error("Failed to delete delivery option: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return response()->json([
                'message' => 'Failed to delete delivery option. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    
    }
}
