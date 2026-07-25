<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer = User::factory();
        $providerProfile = ProviderProfile::factory();

        return [
            'booking_id' => Booking::factory(),
            'customer_id' => $customer,
            'provider_profile_id' => $providerProfile,
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
        ];
    }
}
