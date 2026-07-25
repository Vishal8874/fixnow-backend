<?php

namespace Database\Factories;

use App\Models\ProviderAvailability;
use App\Models\ProviderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderAvailability>
 */
class ProviderAvailabilityFactory extends Factory
{
    protected $model = ProviderAvailability::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_profile_id' => ProviderProfile::factory(),
            'is_available' => true,
            'available_from' => '09:00:00',
            'available_until' => '18:00:00',
            'notes' => null,
        ];
    }
}
