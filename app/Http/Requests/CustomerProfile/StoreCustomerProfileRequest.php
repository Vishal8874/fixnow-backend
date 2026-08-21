<?php

namespace App\Http\Requests\CustomerProfile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'date_of_birth' => ['nullable', 'date', 'before:today'],

            'gender' => ['nullable', 'string', 'max:50'],
        ];
    }
}
