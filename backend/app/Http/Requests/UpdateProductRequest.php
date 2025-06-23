<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the product ID from the route parameters
        // This is important for the unique rule to ignore the current product's name/SKU.
        $productId = $this->route('product');

        return [
            'category_id' => [
                'nullable', // Not required for update unless changing
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],
            'name' => [
                'nullable',
                'string',
                'max:255',
                // Ensure unique name, but ignore the current product's name
                Rule::unique('products')->ignore($productId),
            ],
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'price_per_unit' => 'nullable|numeric|min:0.01',
            'discount_price' => 'nullable|numeric|min:0.01|lt:price_per_unit|different:price_per_unit|prohibits:discount_percentage',
            'discount_percentage' => 'nullable|numeric|min:0|max:100|prohibits:discount_price',
            'discount_start_date' => 'nullable|date_format:Y-m-d H:i:s',
            'discount_end_date' => 'nullable|date_format:Y-m-d H:i:s|after_or_equal:discount_start_date',
            'unit_of_measure' => 'nullable|string|max:50',
            'min_order_quantity' => 'nullable|numeric|min:0.01',
            'stock_quantity' => 'nullable|numeric|min:0',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validate each image file
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sku' => [
                'nullable',
                'string',
                'max:100',
                // Ensure unique SKU, but ignore the current product's SKU
                Rule::unique('products')->ignore($productId),
            ],
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
            'category_id.exists' => 'The selected category does not exist or is not active.',
            'name.unique' => 'A product with this name already exists.',
            'price_per_unit.min' => 'Product price must be at least 0.01.',
            'min_order_quantity.min' => 'Minimum order quantity must be at least 0.01.',
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
