<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'profile_image' => $this->profile_image,
                'about' => $this->about,
                'experience_years' => $this->experience_years,
                'verification_status' => $this->verification_status?->value,
                'average_rating' => (float) $this->average_rating,
                'total_reviews' => $this->total_reviews,
            ],
        ];
    }
}
