<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment' => [
                'id' => $this->id,
                'method' => $this->payment_method?->value,
                'status' => $this->payment_status?->value,
                'amount' => (float) $this->amount,
                'currency' => $this->currency,
                'razorpay_order_id' => $this->razorpay_order_id,
                'paid_at' => $this->paid_at?->toIso8601String(),
            ],
        ];
    }
}
