<?php

namespace App\Http\Requests\ProviderServiceArea;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProviderServiceAreaRequest extends FormRequest
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
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
        ];
    }
}
