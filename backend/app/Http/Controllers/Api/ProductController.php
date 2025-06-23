<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;


class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15); // Default to 15 items per page
        $products = Product::active() // Only active products
                            // ->featured() // Optional: Only featured products (remove this line if not needed for this endpoint)
                            // ->inStock()  // Optional: Only in-stock products
                            ->with('category', 'images') // Eager load relationships
                            ->paginate($perPage);

        return response()->json([
            'message' => 'Products retrieved successfully.',
            'data' => $products
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $validatedData = $request->validated();

        DB::beginTransaction(); // Start transaction
        try {
            $product = Product::create($validatedData);

            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) { // Added $index for potential main image logic
                    $imagePath = $image->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $imagePath,
                        'is_main_image' => ($index === 0), // Set the first uploaded image as main
                    ]);
                }
            }

            DB::commit(); // Commit transaction
            $product->load('category', 'images'); // Load category and images to return full object

            return response()->json([
                'message' => 'Product created successfully.',
                'data' => $product
            ], 201); // 201 Created
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            Log::error('Failed to create product. Error: ' . $e->getMessage(), ['exception' => $e, 'data' => $validatedData]); // Log more context
            return response()->json([
                'message' => 'Failed to create product.',
                'error' => $e->getMessage()
            ], 500); // 500 Internal Server Error
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        // Eager load category for a single product view
        $product->load('category', 'images');

        return response()->json([
            'message' => 'Product retrieved successfully.',
            'data' => $product
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $validatedData = $request->validated();


        DB::beginTransaction(); // Start transaction
        try {
            $product->update($validatedData);

            // Handle new image uploads (if you want to add/replace images during update)
            if ($request->hasFile('images')) {
                // You might have more complex logic here for updating images,
                // e.g., deleting old ones, marking new main image.
                // This example just adds new images.
                foreach ($request->file('images') as $image) {
                    $imagePath = $image->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $imagePath,
                        'is_main_image' => false, // Adjust as per your update image strategy
                    ]);
                }
            }

            DB::commit(); // Commit transaction
            $product->load('category', 'images');

            return response()->json([
                'message' => 'Product updated successfully.',
                'data' => $product
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            Log::error('Failed to update product. Error: ' . $e->getMessage(), ['exception' => $e, 'product_id' => $product->id, 'data' => $validatedData]); // Log more context
            return response()->json([
                'message' => 'Failed to update product.',
                'error' => $e->getMessage()
            ], 500);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        DB::beginTransaction(); // Start transaction
        try {
            // Delete associated image files from storage AND their database records
            foreach ($product->images as $image) {
                
                $image->delete(); // Delete the image record from the database
            }

            $product->delete(); // Delete the product itself
            DB::commit(); // Commit transaction

            return response()->json([
                'message' => 'Product deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            Log::error('Failed to delete product. Error: ' . $e->getMessage(), ['exception' => $e, 'product_id' => $product->id]);
            return response()->json([
                'message' => 'Failed to delete product.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}