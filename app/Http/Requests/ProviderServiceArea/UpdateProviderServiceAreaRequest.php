<?php

namespace App\Http\Requests\ProviderServiceArea;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderServiceAreaRequest extends FormRequest
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
            'postal_code' => ['sometimes', 'required', 'string', 'max:20'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'state' => ['sometimes', 'required', 'string', 'max:100'],
        ];
    }
}
