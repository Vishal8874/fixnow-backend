<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingDetailsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'booking' => [
                'id' => $this->id,
                'booking_number' => $this->booking_number,
                'booking_date' => $this->booking_date?->toDateString(),
                'booking_time' => $this->booking_time?->format('H:i:s'),
                'special_instructions' => $this->special_instructions,
            ],
            'address' => CustomerAddressResource::make($this->customerAddress),
            'services' => $this->items->map(fn ($item): array => [
                'name' => $item->service_name,
                'quantity' => $item->quantity,
                'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'subtotal' => number_format((float) $item->subtotal, 2, '.', ''),
            ])->values()->all(),
            'summary' => [
                'subtotal' => number_format((float) $this->subtotal, 2, '.', ''),
                'service_charge' => number_format((float) $this->service_charge, 2, '.', ''),
                'tax' => number_format((float) $this->tax, 2, '.', ''),
                'discount' => number_format((float) $this->discount, 2, '.', ''),
                'total' => number_format((float) $this->total, 2, '.', ''),
            ],
            'status' => [
                'current' => $this->status?->value,
                'history' => $this->statusHistories->map(fn ($history): array => [
                    'status' => $history->status?->value,
                    'remarks' => $history->remarks,
                ])->values()->all(),
            ],
            'payment' => $this->whenLoaded('payment', fn (): array => PaymentResource::make($this->payment)->resolve()['payment']),
        ];
    }
}
