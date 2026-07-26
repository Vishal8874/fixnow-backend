<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Booking;
use App\Models\ProviderAssignment;
use App\Models\ProviderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderAssignment>
 */
class ProviderAssignmentFactory extends Factory
{
    protected $model = ProviderAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'provider_profile_id' => ProviderProfile::factory(),
            'status' => AssignmentStatus::ASSIGNED,
            'assigned_at' => now(),
            'accepted_at' => null,
            'rejected_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
            'rejection_reason' => null,
            'notes' => null,
        ];
    }
}
