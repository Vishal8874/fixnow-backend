<?php

namespace App\Http\Requests\Service;

use App\Enums\Status;
use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateServiceRequest extends FormRequest
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
        /** @var Service $service */
        $service = $this->route('service');

        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('services', 'slug')->ignore($service->id)],

            'image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'description' => ['nullable', 'string'],
            'estimated_duration' => ['sometimes', 'required', 'integer', 'gt:0'],
            'base_price' => ['sometimes', 'required', 'numeric', 'gte:0'],
            'status' => ['sometimes', new Enum(Status::class)],
        ];
    }
}
