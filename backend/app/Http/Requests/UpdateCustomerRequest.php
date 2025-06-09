<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customer = $this->route('customer');
        // A user should only be able to update their own customer profile.
        // Ensure the authenticated user exists, the customer model is found,
        // and the authenticated user's ID matches the customer's user_id.
        return $this->user() && $customer && $this->user()->id === $customer->user_id;

        // Optional: If admins can update any profile, you might add something like:
        // return $this->user()->hasRole('admin') || ($this->user() && $customer && $this->user()->id === $customer->user_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the customer model being updated from the route.
        // This is necessary for the unique rule and conditional requiredIf logic.
        $customer = $this->route('customer');

        return [
            'user_id' => [
                'sometimes', // Field is optional for update; only validate if present.
                'integer',
                'exists:users,id', // Must refer to an existing user in the 'users' table.
                // Ensures user_id is unique across customers, but ignores the current customer's ID.
                Rule::unique('customers', 'user_id')->ignore($customer->id),
                // Ensures that if user_id is provided in the request, it matches the existing user_id
                // of the customer profile being updated. Prevents changing the linked user.
                Rule::in([$customer->user_id]),
            ],
            'customer_type' => [
                'sometimes', // Field is optional for update.
                'string',
                // Ensures customer_type is one of the allowed values.
                Rule::in(['restaurant', 'family', 'individual_bulk']),
            ],
            'business_name' => [
                'nullable',  // Can be null.
                'string',    // Must be a string if present.
                'max:255',   // Maximum 255 characters.
                // Conditional requirement: required if the customer_type (new or existing) is 'restaurant'.
                Rule::requiredIf(function () use ($customer) {
                    $newCustomerType = $this->input('customer_type', $customer->customer_type); // Get new type or fallback to old
                    return $newCustomerType === 'restaurant';
                }),
            ],
            'ghanapost_gps_address' => [
                'sometimes', // Field is optional for update.
                'nullable',  // Can be null.
                'string',
                'max:255',
            ],
            'digital_address' => [
                'sometimes', // Field is optional for update.
                'nullable',  // Can be null.
                'string',
                'max:255',
            ],
            'tax_id' => [
                'nullable',  // Can be null.
                'string',
                'max:255',
                // Conditional requirement: required if the customer_type (new or existing) is 'restaurant'.
                Rule::requiredIf(function () use ($customer) {
                    $newCustomerType = $this->input('customer_type', $customer->customer_type); // Get new type or fallback to old
                    return $newCustomerType === 'restaurant';
                }),
                // Custom closure validation rule for GRA TIN / GhanaCard PIN format:
                function ($attribute, $value, $fail) {
                    // Only apply format validation if a value is actually provided and not null.
                    if (!is_null($value)) {
                        $graTinRegex = '/^(P|C|G|Q|V)00\d{8}$/'; // Regex for GRA TIN (e.g., C0012345678)
                        $ghanaCardPinRegex = '/^[A-Z]{3}-\d{8,9}-[A-Z0-9]$/'; // Regex for GhanaCard PIN (e.g., GHA-12345678-X)

                        // If the value matches neither format, then it's invalid.
                        if (!preg_match($graTinRegex, $value) && !preg_match($ghanaCardPinRegex, $value)) {
                            $fail("The :attribute format is invalid. It must be either a valid GRA TIN (e.g., C0012345678) or a GhanaCard PIN (e.g., GHA-12345678-X or GHA-123456789-Y).");
                        }
                    }
                },
            ],
            'contact_person_name' => [
                'sometimes', // Field is optional for update.
                'nullable',  // Can be null.
                'string',
                'max:255',
            ],
            'contact_person_phone' => [
                'sometimes', // Field is optional for update.
                'nullable',  // Can be null.
                'string',
                'max:255',
                // Regex for Ghanaian phone numbers (e.g., +233241234567 or 0241234567)
                'regex:/^(\+233|0)[2|5]\d{8}$/'
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
            'user_id.unique' => 'A customer profile already exists for the selected user.',
            'user_id.exists' => 'The selected user does not exist.',
            'user_id.in' => 'The user ID cannot be changed for an existing customer profile.', // Custom message for immutable user_id

            'customer_type.in' => 'The customer type must be one of: restaurant, family, or individual_bulk.',
            'business_name.required_if' => 'The business name is required when the customer type is restaurant.',
            'tax_id.required_if' => 'The tax ID is required when the customer type is restaurant.',
            // The message for the custom closure on tax_id is passed directly to $fail()
        ];
    }
}
