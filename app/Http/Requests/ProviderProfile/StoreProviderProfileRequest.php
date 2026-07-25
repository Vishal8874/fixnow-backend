<?php

namespace App\Http\Requests\ProviderProfile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProviderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'about' => ['required', 'string'],
            'experience_years' => ['required', 'integer', 'min:0'],
            'profile_image' => ['nullable', 'image'],
        ];
    }
}
