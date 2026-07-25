<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'booking_date' => $this->booking_date?->toDateString(),
            'booking_time' => $this->booking_time?->format('H:i:s'),
            'status' => $this->status?->value,
            'address' => [
                'id' => $this->customerAddress?->id,
                'label' => $this->customerAddress?->label,
                'city' => $this->customerAddress?->city,
                'state' => $this->customerAddress?->state,
            ],
            'services_count' => $this->whenCounted('items', fn (): int => (int) $this->items_count),
            'summary' => [
                'subtotal' => number_format((float) $this->subtotal, 2, '.', ''),
                'service_charge' => number_format((float) $this->service_charge, 2, '.', ''),
                'tax' => number_format((float) $this->tax, 2, '.', ''),
                'discount' => number_format((float) $this->discount, 2, '.', ''),
                'total' => number_format((float) $this->total, 2, '.', ''),
            ],
        ];
    }
}
