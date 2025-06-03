<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;



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
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('is_active', true); // Ensure category is active
                }),
            ],
            'name' => 'required|string|max:255|unique:products,name',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'price_per_unit' => 'required|numeric|min:0.01',
            'unit_of_measure' => 'required|string|max:50', // e.g., 'kg', 'piece'
            'min_order_quantity' => 'required|numeric|min:0.01',
            'stock_quantity' => 'required|numeric|min:0',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validate each image URL
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sku' => 'nullable|string|max:100|unique:products,sku', // will be generated automatically if not set
        ]);

        //Generate SKU if not provided
        if (empty($validatedData['sku'])) {
            $productName = $validatedData['name'];
            $initials = '';
            // Generate SKU from product name initials
            foreach (explode(' ', $productName) as $word) {
                $initials .= strtoupper(substr($word, 0, 1)); // Get first letter of each word
            }
            if (empty($initials)) {
                $initials = Str::upper(substr(Str::slug($productName), 0, 3)); // Use first 3 letters of slug if no initials
            }
            $baseSku = $initials;
            $uniqueSku = '';
            $counter = 0;

            do {
                $randomPart = Str::upper(Str::random(6)); // 6 random alphanumeric characters
                $generatedSku = $baseSku . '-' . $randomPart;
                $counter++;
                if ($counter > 10) { // Safety break
                    throw new \Exception("Could not generate a unique SKU for product: {$productName} after 10 attempts.");
                }
            } while (Product::where('sku', $generatedSku)->exists()); // Check if SKU already exists

            $validatedData['sku'] = $generatedSku;
        }

        // Generate slug from name
        $validatedData['slug'] = Str::slug($validatedData['name']);

        // Set default values if not provided in the request
        if (!isset($validatedData['is_active'])) {
            $validatedData['is_active'] = true;
        }
        if (!isset($validatedData['is_featured'])) {
            $validatedData['is_featured'] = false;
        }

        try {
            $product = Product::create($validatedData);

            // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePath = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => $imagePath,
                    'is_main_image' => false, // You might need to adjust this logic
                ]);
            }
        }

        $product->load('category', 'images'); // Load category and images to return full object

            return response()->json([
                'message' => 'Product created successfully.',
                'data' => $product
            ], 201); // 201 Created
        } catch (\Exception $e) {
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
    public function update(Request $request, Product $product)
    {
        $validatedData = $request->validate([
            'category_id' => [
                'nullable', // Category can be updated, but not required if not changing
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('is_active', true); // Ensure category is active
                }),
            ],
            'name' => [
                'nullable', // Name is not required for update unless it's changed
                'string',
                'max:255',
                Rule::unique('products')->ignore($product->id), // Ignore current product's name
            ],
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'price_per_unit' => 'nullable|numeric|min:0.01',
            'unit_of_measure' => 'nullable|string|max:50',
            'min_order_quantity' => 'nullable|numeric|min:0.01',
            'stock_quantity' => 'nullable|numeric|min:0',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validate each image URL
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->ignore($product->id), // Ignore current product's SKU
            ],
        ]);

        // Regenerate slug only if name has changed and is provided in the request
        if (isset($validatedData['name']) && $validatedData['name'] !== $product->name) {
            $validatedData['slug'] = Str::slug($validatedData['name']);
        }

        
        Log::info('Product Update Validated Data: ' . json_encode($validatedData));
        Log::info('Product Data BEFORE UPDATE: ' . json_encode($product->toArray()));

        
        try {
            $product->update($validatedData);

            // Handle new image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePath = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => $imagePath,
                    'is_main_image' => false, // You might need to adjust this logic
                ]);
            }
        }

        $product->load('category', 'images');

            return response()->json([
                'message' => 'Product updated successfully.',
                'data' => $product
            ], 200);
        } catch (\Exception $e) {
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
        // Add any business logic here before deleting, e.g.,
        // Prevent deletion if product is part of existing orders.
        // For now, assuming direct deletion is fine.

        try {
            // Delete associated image files from storage
            foreach ($product->images as $image) {
                if (Storage::disk('public')->exists($image->image_url)) {
                    Storage::disk('public')->delete($image->image_url);
            }
            $image->delete(); // Delete the image record from the database
            }
            $product->delete();

            return response()->json([
                'message' => 'Product deleted successfully.',
            ], 200); // Or 204 No Content
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete product.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
