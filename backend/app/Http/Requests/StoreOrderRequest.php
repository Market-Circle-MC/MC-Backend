<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // delivery_address_id: Must exist in the addresses table and belong to the authenticated customer.
            'delivery_address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where(function ($query) {
                    $query->where('customer_id', Auth::user()->customer->id);
                }),
            ],
            // delivery_option_id: Must exist in the delivery_options table and be active.
            'delivery_option_id' => [
                'required',
                'integer',
                Rule::exists('delivery_options', 'id')->where('is_active', true),
            ],
            // payment_method: Required and must be one of the allowed methods.
            'payment_method' => ['required', 'string', Rule::in(['Mobile Money', 'Bank Transfer', 'Cash on Delivery', 'Card'])],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'delivery_address_id.required' => 'A delivery address is required to place an order.',
            'delivery_address_id.exists' => 'The selected delivery address is invalid or does not belong to your account.',
            'delivery_option_id.required' => 'A delivery option is required.',
            'delivery_option_id.exists' => 'The selected delivery option is invalid or not active.',
            'payment_method.required' => 'A payment method is required.',
            'payment_method.in' => 'The selected payment method is not supported.',
        ];
    }
}
