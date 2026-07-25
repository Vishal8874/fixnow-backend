<?php

namespace Database\Factories;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderProfile>
 */
class ProviderProfileFactory extends Factory
{
    protected $model = ProviderProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => UserRole::PROVIDER])->id,
            'profile_image' => null,
            'about' => fake()->paragraph(),
            'experience_years' => fake()->numberBetween(0, 20),
            'verification_status' => ProviderVerificationStatus::PENDING,
            'average_rating' => 0.00,
            'total_reviews' => 0,
        ];
    }
}
