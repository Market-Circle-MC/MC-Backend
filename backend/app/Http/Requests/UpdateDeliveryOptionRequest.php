<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateDeliveryOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Only administrators can update delivery options.
        return Auth::check() && Auth::user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Get the ID of the delivery option being updated from the route parameters
        $deliveryOptionId = $this->route('delivery_option')->id ?? null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('delivery_options', 'name')->ignore($deliveryOptionId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'min_delivery_days' => ['nullable', 'integer', 'min:0'],
            'max_delivery_days' => ['nullable', 'integer', 'min:0', 'gte:min_delivery_days'],
            'is_active' => ['sometimes', 'boolean'],
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
            'name.unique' => 'A delivery option with this name already exists.',
            'cost.min' => 'Delivery cost must be a non-negative number.',
            'max_delivery_days.gte' => 'Max delivery days must be greater than or equal to min delivery days.',
        ];
    }
}
