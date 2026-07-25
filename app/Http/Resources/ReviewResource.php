<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'review' => [
                'id' => $this->id,
                'rating' => $this->rating,
                'comment' => $this->comment,
                'customer' => [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                ],
                'provider' => [
                    'id' => $this->providerProfile->user->id,
                    'name' => $this->providerProfile->user->name,
                    'average_rating' => (float) $this->providerProfile->average_rating,
                    'total_reviews' => $this->providerProfile->total_reviews,
                ],
                'booking' => [
                    'booking_number' => $this->booking->booking_number,
                ],
                'created_at' => $this->created_at?->toIso8601String(),
            ],
        ];
    }
}
