<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
        return [
            'user_id' => [
                'required',
               'integer',
                'exists:users,id',
                'unique:customers,user_id',
            ],
            'customer_type' => [
                'required',
                'string',
                // Rule::in ensures the value is one of the specified options
                Rule::in(['restaurant', 'family', 'individual_bulk']),
            ],
            'business_name' => [
                'nullable',
                'string',          
                'max:255',
                // Conditional Rule: required if 'customer_type' in the request is 'restaurant'
                Rule::requiredIf($this->customer_type === 'restaurant'),
            ],
            'ghanapost_gps_address' => [
                'nullable',
                'string',           
                'max:255',           
            ],
            'digital_address' => [
                'nullable',
                'string',
                'max:255'
            ],
            'tax_id' => [
                'nullable',
                'string',
                'max:255',
                // Conditional Rule: required if 'customer_type' in the request is 'restaurant'
                Rule::requiredIf($this->customer_type === 'restaurant'),
                //Only apply format validation if tax_id value is provided
                function ($attribute, $value, $fail) {
                    if (!is_null($value)) {
                        //Regex for GRA TIN(e.g., P0012345678, C0012345678) - 11 characters
                        $graTinRegex = '/^(P|C|G|Q|V)00\d{8}$/';

                        // Regex for GhanaCard PIN (e.g., GHA-12345678-X, GHA-123456789-Y) - 15 or 16 characters
                        // Country Code (3 letters) - 8 or 9 digits - Checksum (1 char/digit)
                        $ghanaCardPinRegex = '/^[A-Z]{3}-\d{8,9}-[A-Z0-9]$/';

                        // Check if the value matches EITHER of the valid formats
                        if (!preg_match($graTinRegex, $value) && !preg_match($ghanaCardPinRegex, $value)) {
                            // If it matches neither, then it's invalid.
                            $fail("The :attribute format is invalid. It must be either a valid GRA TIN (e.g., C0012345678) or a GhanaCard PIN (e.g., GHA-12345678-X or GHA-123456789-Y).");
                        }
                    }
                },
            ],
            'contact_person_name' => [
                'nullable',
                'string',
                'max:255'
            ],
            'contact_person_phone' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^\+\d{7,15}$/'
            ],
        ];
    } /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => 'A customer profile already exists for the selected user.',
            'user_id.exists' => 'The selected user does not exist.',
            'customer_type.in' => 'The customer type must be one of: restaurant, family, or individual_bulk.',
            'business_name.required_if' => 'The business name is required when the customer type is restaurant.',
            'tax_id.required_if' => 'The tax ID is required when the customer type is restaurant.',
        ];
    }
}

