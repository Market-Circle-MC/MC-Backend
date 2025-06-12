<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
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
            // Require password for both login types
            'password' => ['required', 'string'],

            // Use the 'required_without' rule to ensure either email or phone_number is present
            // 'email' is required if 'phone_number' is not present
            'email' => [
                Rule::requiredIf(fn () => !$this->has('phone_number')),
                'string',
                'email',
                'max:255',
            ],
            // 'phone_number' is required if 'email' is not present
            'phone_number' => [
                Rule::requiredIf(fn () => !$this->has('email')),
                'string',
                'max:255', // Adjust max length as appropriate for phone numbers
                'regex:/^\+?\d{8,15}$/', // Example regex for international phone numbers (adjust as needed)
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
            'email.required_without' => 'Either email or phone number is required.',
            'phone_number.required_without' => 'Either phone number or email is required.',
            'phone_number.regex' => 'The phone number format is invalid.',
        ];
    }
}
