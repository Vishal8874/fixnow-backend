<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'availability' => [
                'is_available' => $this->is_available,
                'available_from' => $this->available_from?->format('H:i'),
                'available_until' => $this->available_until?->format('H:i'),
                'notes' => $this->notes,
            ],
        ];
    }
}
