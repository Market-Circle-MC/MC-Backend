<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;


class UpdateOrderRequest extends FormRequest
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
        return [
            'order_status' => ['sometimes', 'required', 'string', Rule::in(['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])],
            'payment_status' => ['sometimes', 'required', 'string', Rule::in(['unpaid', 'paid', 'partially_paid', 'refunded'])],
            'payment_method' => ['sometimes', 'required', 'string', Rule::in(['Mobile Money', 'Bank Transfer', 'Cash on Delivery', 'Card'])],
            'payment_gateway_transaction_id' => ['nullable', 'string', 'max:255'],
            'payment_details' => ['nullable', 'json'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'delivery_option_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('delivery_options', 'id')->where('is_active', true),
            ],
            'delivery_tracking_number' => ['nullable', 'string', 'max:255'],
            'delivery_service' => ['nullable', 'string', 'max:255'],
            'dispatched_at' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date', 'after_or_equal:dispatched_at'],
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
            'order_status.in' => 'Invalid order status provided.',
            'payment_status.in' => 'Invalid payment status provided.',
            'delivery_option_id.exists' => 'The selected delivery option is invalid or not active.',
            'delivered_at.after_or_equal' => 'The delivered date cannot be before the dispatched date.',
        ];
    }
}
