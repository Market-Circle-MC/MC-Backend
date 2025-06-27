<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        //Authorization logic will be handled in controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return 
            [
            'product_id' => [
                'required',
                'integer',
                'exists:products,id', // Ensure product exists
                // ensure product is active and in stock
                Rule::exists('products', 'id')->where(function ($query) {
                    $query->where('is_active', true)->where('stock_quantity', '>', 0);
                }),
            ],
            'quantity' => [
                'required',
                'numeric',
                'min:0.01', // Minimum quantity
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     * This method can be used to set default values or manipulate input before validation.
     */
    protected function prepareForValidation(): void
    {
        // If product_id is provided, fetch product details to use in validation or controller logic
        if ($this->has('product_id')) {
            $product = \App\Models\Product::find($this->product_id);
            if ($product) {
                // Ensure quantity respects min_order_quantity and available stock
                if ($this->has('quantity') && $this->quantity < $product->min_order_quantity) {
                    $this->merge(['quantity' => $product->min_order_quantity]);
                }
                
                // Store product data for easy access later in the controller
                $this->merge([
                    'price_per_unit_at_addition' => $product->current_price, // Use current_price from accessor
                    'unit_of_measure_at_addition' => $product->unit_of_measure,
                ]);
            }
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'The selected product does not exist or is not available for purchase.',
            'product_id.required' => 'A product is required to add to the cart.',
            'quantity.required' => 'Quantity is required.',
            'quantity.numeric' => 'Quantity must be a number.',
            'quantity.min' => 'Quantity must be at least :min.',
        ];
    }
    
}

