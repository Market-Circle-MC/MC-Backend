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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;



class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function index(Request $request)
    {
        $user = Auth::user();
        $productsQuery = Product::active(); // Default for regular users

        if ($user && $user->role === 'admin') {
            // Admins can see all products, including inactive ones
            $productsQuery = Product::query();
        }

        // Eager load relationships ONCE
        $productsQuery->with(['category', 'images']);

        Log::info('Product query SQL before pagination:', [
            'sql' => $productsQuery->toSql(),
            'bindings' => $productsQuery->getBindings()
        ]);

        $rawProductsBeforePagination = $productsQuery->get();
        Log::info('Count of raw products fetched before pagination:', ['count' => $rawProductsBeforePagination->count()]);

        $perPage = $request->input('per_page', 15);
        Log::info('Request has category parameter:', ['has_category' => $request->has('category')]);
        // --- Category Filtering Logic ---
        if ($request->has('category')) {
            $categorySlug = $request->query('category');
            Log::info('Category slug from request:', ['slug' => $categorySlug]);
            $categoryBaseQuery = Category::query();
            if (!($user && $user->role === 'admin')) {
                $categoryBaseQuery->active();
            }

            $category = $categoryBaseQuery->where('slug', $categorySlug)->first();
            Log::info('Found Category object:', ['category' => $category ? $category->toArray() : 'null']);

            if ($category) {
                $relatedCategories = Category::descendantsAndSelf($category->id);
                Log::info('Related Categories (descendantsAndSelf):', [
                    'count' => $relatedCategories->count(),
                    'ids' => $relatedCategories->pluck('id')->toArray(),
                    'raw_data' => $relatedCategories->toArray()
                ]);

                $categoryIds = [];
                if ($user && $user->role === 'admin') {
                    $categoryIds = $relatedCategories->pluck('id')->toArray();
                } else {
                    $categoryIds = $relatedCategories->filter(function ($cat) {
                        return $cat->is_active;
                    })->pluck('id')->toArray();
                }

                Log::info('Final Category IDs for Product Filtering:', ['category_ids' => $categoryIds]);

                if (!empty($categoryIds)) {
                    $productsQuery->whereIn('category_id', $categoryIds);
                    Log::info('Applied whereIn to productsQuery.', ['category_ids_used' => $categoryIds]);
                } else {
                    // This block means category was found, but no active descendants/self were.
                    Log::warning('No active category IDs found for product filtering, returning empty response.');
                    return response()->json([
                        'message' => 'No products found for the specified category or its active subcategories.',
                        'data' => (new LengthAwarePaginator([], 0, $perPage))->toArray()
                    ], 200);
                }
            } else {
                // This block means the category with the given slug was NOT found.
                Log::warning('Category not found for slug: ' . $categorySlug);
                return response()->json([
                    'message' => 'No products found for the specified category.',
                    'data' => (new LengthAwarePaginator([], 0, $perPage))->toArray()
                ], 200);
            }
        }
        // --- End Category Filtering Logic ---

        // Apply optional filters - ensure test data matches these if they are always applied
        $productsQuery->featured();
        $productsQuery->inStock();

        Log::info('Final Product query SQL before pagination:', [
            'sql' => $productsQuery->toSql(),
            'bindings' => $productsQuery->getBindings()
        ]);
        $productsQuery->orderBy('id', 'desc');

        $products = $productsQuery->paginate($perPage);

        Log::info('Products retrieved for response:', [
            'count_on_page' => $products->count(),
            'total_items' => $products->total(),
            'per_page_setting' => $products->perPage(),
            'current_page' => $products->currentPage()
        ]);

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