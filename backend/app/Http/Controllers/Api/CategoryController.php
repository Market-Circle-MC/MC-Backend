<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;


class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::query(); // Start with a query builder instance
        $user = Auth::user(); // Get the authenticated user
        // Check if the authenticated user is an admin
        // This assumes your User model has a 'role' column and you're using 'admin' for admin users.
        if ($user && $user->role === 'admin') {
            // Admin users get all categories
            $categories->with(['parent', 'children']);
        } else {
            // Non-admin (guest, customer) users only get active categories
            $categories->where('is_active', true)->with(['parent', 'children']);
        }

        return response()->json([
            'message' => 'Categories retrieved successfully.',
            'data' => $categories->get() // Execute the query
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $validatedData = $request->validated();

        // Generate slug from name
        $validatedData['slug'] = Str::slug($validatedData['name']);

        // Set default for is_active if not provided
        if (!isset($validatedData['is_active'])) {
            $validatedData['is_active'] = true;
        }

        try {
            $category = Category::create($validatedData);

            return response()->json([
                'message' => 'Category created successfully.',
                'data' => $category
            ], 201); // 201 Created
        } catch (\Exception $e) {
            // Handle potential unique slug/name constraint violation if not caught by validation
            // or other database errors
            return response()->json([
                'message' => 'Failed to create category.',
                'error' => $e->getMessage()
            ], 500); // 500 Internal Server Error
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        // Eager load parent and children for a single category view
        $category->load(['parent', 'children']);

        return response()->json([
            'message' => 'Category retrieved successfully.',
            'data' => $category
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validatedData = $request->validated();

        // Regenerate slug only if name has changed
        if (isset($validatedData['name']) && $validatedData['name'] !== $category->name) {
            $validatedData['slug'] = Str::slug($validatedData['name']);
        }

        try {
            $category->update($validatedData);

            return response()->json([
                'message' => 'Category updated successfully.',
                'data' => $category
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update category.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        // Prevent deletion if the category has associated products (due to 'onDelete('restrict')' implied from FK in products table)
        // Or if it has children categories (due to 'onDelete('set null')' behavior, we still might want to restrict this via business logic)
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category: It has associated products.',
            ], 409); // 409 Conflict
        }

        if ($category->children()->count() > 0) {
             return response()->json([
                'message' => 'Cannot delete category: It has active child categories. Re-assign or delete children first.',
            ], 409); // 409 Conflict
        }

        try {
            $category->delete();

            return response()->json([
                'message' => 'Category deleted successfully.',
            ], 200); // Or 204 No Content for successful deletion with no body
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete category.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
