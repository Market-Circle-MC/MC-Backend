<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // 'confirmed' checks for password_confirmation
            'role' => ['nullable', Rule::in(['customer', 'admin'])], // 'customer' or 'admin', can be null/omitted

            // Require either email or phone_number, but not both explicitly
            'email' => [
                'nullable',
                Rule::unique('users', 'email'),
                'string',
                'email',
                'max:255',
                'required_without:phone_number',
            ],
            'phone_number' => [
                Rule::unique('users', 'phone_number'),
                'nullable',
                'required_without:email',
                'string',
                'max:255',
                'unique:users', 
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
            'email.required_without' => 'Either email or phone number is required for registration.',
            'phone_number.required_without' => 'Either phone number or email is required for registration.',
            'phone_number.regex' => 'The phone number format is invalid.',
            'email.unique' => 'This email is already registered.',
            'phone_number.unique' => 'This phone number is already registered.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}

