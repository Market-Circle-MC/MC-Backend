<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UpdateCategoryRequest extends FormRequest
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
        // Get the category ID from the route parameters
        // This is important for the unique rule to ignore the current category's name.
        $categoryId = $this->route('category')->id;

        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
                // Ensure unique name, but ignore the current category's name
                Rule::unique('categories')->ignore($categoryId),
            ],
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'integer',
                // Ensure parent category exists and is active, and is not the category itself
                Rule::exists('categories', 'id')->where(function ($query) use ($categoryId) {
                    $query->where('is_active', true)
                          ->where('id', '!=', $categoryId); // Cannot be its own parent
                }),
            ],
            'image_url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
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
            'name.unique' => 'A category with this name already exists.',
            'parent_id.exists' => 'The selected parent category does not exist, is not active, or is the category itself.',
            'image_url.url' => 'The image URL must be a valid URL.',
        ];
    }
}