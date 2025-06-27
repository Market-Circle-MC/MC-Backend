<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCartItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cartItem = $this->route('item');

        // If cartItem is resolved via route model binding, eager load its product relationship.
        // This ensures the product is available for prepareForValidation method.
        if ($cartItem) {
            $cartItem->loadMissing('product');
        }

        return [
            'quantity' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     * This method can be used to set default values or manipulate input before validation.
     */
    protected function prepareForValidation(): void
    {
        $cartItem = $this->route('item');
        if ($cartItem && $cartItem->product) {
            $product = $cartItem->product;
            // Adjust quantity to min_order_quantity if the submitted quantity is lower than allowed.
            if ($this->has('quantity') && $this->quantity < $product->min_order_quantity) {
                $this->merge(['quantity' => $product->min_order_quantity]);
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
            'quantity.required' => 'Quantity is required.',
            'quantity.numeric' => 'Quantity must be a number.',
            'quantity.min' => 'Quantity must be at least :min.',
        ];
    }
}