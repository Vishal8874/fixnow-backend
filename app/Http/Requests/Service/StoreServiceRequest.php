<?php

namespace App\Http\Requests\Service;

use App\Enums\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreServiceRequest extends FormRequest
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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:services,slug'],

            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'description' => ['nullable', 'string'],
            'estimated_duration' => ['required', 'integer', 'gt:0'],
            'base_price' => ['required', 'numeric', 'gte:0'],
            'status' => ['nullable', new Enum(Status::class)],
        ];
    }
}
