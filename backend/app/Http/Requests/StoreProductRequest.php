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
            'discount_price' => 'nullable|numeric|min:0.01|lte:price_per_unit|different:price_per_unit|prohibits:discount_percentage', // Ensure discount price is less than or equal to price_per_unit
            'discount_percentage' => 'nullable|numeric|min:0|max:100|prohibits:discount_price', // Ensure discount percentage is between 0 and 100
            'discount_start_date' => 'nullable|date_format:Y-m-d H:i:s',// Must be now or in the future
            'discount_end_date' => 'nullable|date_format:Y-m-d H:i:s|after_or_equal:discount_start_date', // Must be after or equal to start date
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
            'discount_percentage.prohibits' => 'Cannot provide both a discount percentage and a fixed discount price.',
            'discount_price.prohibits' => 'Cannot provide both a fixed discount price and a discount percentage.',
            'discount_price.lt' => 'The discount price must be less than the price per unit.',
            'discount_price.different' => 'The discount price must be different from the price per unit.',
            'discount_end_date.after_or_equal' => 'The discount end date must be after or equal to the discount start date.',
        ];
    }
}
