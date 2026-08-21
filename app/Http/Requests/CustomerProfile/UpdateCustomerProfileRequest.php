<?php

namespace App\Http\Requests\CustomerProfile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerProfileRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],

            'gender' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
