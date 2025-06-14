<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                // Ensure category exists and is active
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('is_active', true);
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
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validate each image file
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sku' => 'nullable|string|max:100|unique:products,sku', // will be generated automatically if not set
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'A category is required for the product.',
            'category_id.exists' => 'The selected category does not exist or is not active.',
            'name.required' => 'A product name is required.',
            'name.unique' => 'A product with this name already exists.',
            'price_per_unit.required' => 'Product price is required.',
            'price_per_unit.min' => 'Product price must be at least 0.01.',
            'unit_of_measure.required' => 'Unit of measure (e.g., kg, piece) is required.',
            'min_order_quantity.required' => 'Minimum order quantity is required.',
            'min_order_quantity.min' => 'Minimum order quantity must be at least 0.01.',
            'stock_quantity.required' => 'Stock quantity is required.',
            'stock_quantity.min' => 'Stock quantity cannot be negative.',
            'images.*.image' => 'Each image must be a valid image file.',
            'images.*.mimes' => 'Images must be a file of type: jpeg, png, jpg, gif, svg.',
            'images.*.max' => 'Each image may not be greater than 2MB.',
            'sku.unique' => 'This SKU is already in use by another product.',
        ];
    }
}
