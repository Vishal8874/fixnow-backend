<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'assignment' => [
                'id' => $this->id,
                'status' => $this->status?->value,
                'assigned_at' => $this->assigned_at?->toIso8601String(),
                'accepted_at' => $this->accepted_at?->toIso8601String(),
                'rejected_at' => $this->rejected_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
                'rejection_reason' => $this->rejection_reason,
                'notes' => $this->notes,
                'provider' => [
                    'id' => $this->providerProfile->user->id,
                    'name' => $this->providerProfile->user->name,
                    'experience_years' => $this->providerProfile->experience_years,
                    'average_rating' => (float) $this->providerProfile->average_rating,
                ],
            ],
        ];
    }
}
