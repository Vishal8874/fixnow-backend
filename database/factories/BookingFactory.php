<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id, 'is_default' => true]);

        return [
            'booking_number' => sprintf('BK%s%05d', now()->format('Y'), fake()->unique()->numberBetween(1, 99999)),
            'user_id' => $user->id,
            'customer_address_id' => $address->id,
            'booking_date' => now()->toDateString(),
            'booking_time' => '10:00:00',
            'special_instructions' => null,
            'status' => BookingStatus::CREATED,
            'subtotal' => 1000,
            'service_charge' => 50,
            'tax' => 25,
            'discount' => 0,
            'total' => 1075,
        ];
    }
}
