<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\Category;
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
        Log::info('ProductController@index method started.'); // This will always be logged

        $perPage = $request->input('per_page', 15);

        $productsQuery = Product::active();

        // --- Category Filtering Logic ---
        Log::info('Checking for "category" query parameter.');
        Log::info('Request has "category" parameter: ' . ($request->has('category') ? 'true' : 'false'));
        Log::info('Value of "category" parameter: ' . $request->query('category', 'Not provided'));

        if ($request->has('category')) {
            $categorySlug = $request->query('category');

            Log::info('Attempting to find category with slug: ' . $categorySlug);
            $category = Category::where('slug', $categorySlug)->first();

            // Log what $category contains after the lookup
            if ($category) {
                Log::info('$category found: ID ' . $category->id . ', Name: ' . $category->name . ', Slug: ' . $category->slug);
            } else {
                Log::info('$category NOT found for slug: ' . $categorySlug);
            }

            if ($category) {
                Log::info('Calling descendantsAndSelf() on Category instance. Category ID: ' . $category->id);
                // THIS IS THE LINE WHERE THE ERROR OCCURS, SO WE LOG BEFORE AND AFTER
                $categoryIds = Category::descendantsAndSelf($category->id)->pluck('id')->toArray();
                Log::info('descendantsAndSelf() returned IDs: ' . json_encode($categoryIds));

                $productsQuery->whereIn('category_id', $categoryIds);
            } else {
                Log::info('Category not found for filtering.');
                // If the category slug does not exist, return an empty paginated result.
                return response()->json([
                    'message' => 'No products found for the specified category.',
                    'data' => (new LengthAwarePaginator([], 0, $perPage))->toArray()
                ], 200);
            }
        }
        // --- End Category Filtering Logic ---

        // Optional filters
        $productsQuery->featured();
        $productsQuery->inStock();

        $products = $productsQuery->with('category', 'images')->paginate($perPage);

        Log::info('Products query completed. Returning response.');

        return response()->json([
            'message' => 'Products retrieved successfully.',
            'data' => $products->toArray()
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