<?php

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\ProviderServiceArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderServiceArea>
 */
class ProviderServiceAreaFactory extends Factory
{
    protected $model = ProviderServiceArea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_profile_id' => ProviderProfile::factory(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'state' => fake()->state(),
        ];
    }
}
