<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'contact_person' => $this->contact_person,
            'contact_phone' => $this->contact_phone,
            'address' => [
                'line_1' => $this->address_line_1,
                'line_2' => $this->address_line_2,
                'landmark' => $this->landmark,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'latitude' => $this->latitude !== null ? (string) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (string) $this->longitude : null,
            ],
            'is_default' => $this->is_default,
        ];
    }
}
