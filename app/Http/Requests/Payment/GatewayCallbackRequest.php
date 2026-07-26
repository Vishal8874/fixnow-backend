<?php

namespace App\Http\Requests\Payment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GatewayCallbackRequest extends FormRequest
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
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'gateway_transaction_id' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:success,failed'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
