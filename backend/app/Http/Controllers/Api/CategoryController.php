<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Retrieve all active categories, optionally with their parents/children
        // You might want to paginate this for larger datasets: Category::where('is_active', true)->paginate(10);
        $categories = Category::where('is_active', true)
                                ->with(['parent', 'children']) // Eager load parent and children relationships
                                ->get();

        return response()->json([
            'message' => 'Categories retrieved successfully.',
            'data' => $categories
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('is_active', true); // Ensure parent category is active
                }),
            ],
            'image_url' => 'nullable|url|max:255',
            'is_active' => 'boolean', // Will default to true if not provided and not explicitly set to false
        ]);

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
    public function update(Request $request, Category $category)
    {
        $validatedData = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // Ensure unique name, but ignore the current category's name
                Rule::unique('categories')->ignore($category->id),
            ],
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'integer',
                // Ensure parent category exists, is active, and is not the category itself or one of its descendants
                Rule::exists('categories', 'id')->where(function ($query) use ($category) {
                    $query->where('is_active', true)
                          ->where('id', '!=', $category->id); // Cannot be its own parent
                    // Additional logic needed here if you want to prevent circular relationships
                    // (e.g., A -> B, B -> C, then trying C -> A would cause infinite recursion)
                    // This often requires custom validation rules or deeper checks.
                }),
            ],
            'image_url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

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
