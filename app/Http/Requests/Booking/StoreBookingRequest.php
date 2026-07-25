<?php

namespace App\Http\Requests\Booking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'customer_address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
            'booking_date' => ['required', 'date'],
            'booking_time' => ['required', 'date_format:H:i'],
            'special_instructions' => ['nullable', 'string'],
            'service_charge' => ['nullable', 'numeric', 'gte:0'],
            'tax' => ['nullable', 'numeric', 'gte:0'],
            'discount' => ['nullable', 'numeric', 'gte:0'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
