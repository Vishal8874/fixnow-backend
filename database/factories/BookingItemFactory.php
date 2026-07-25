<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingItem>
 */
class BookingItemFactory extends Factory
{
    protected $model = BookingItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $service = Service::factory()->create();

        return [
            'booking_id' => Booking::factory(),
            'service_id' => $service->id,
            'service_name' => $service->name,
            'unit_price' => 500,
            'quantity' => 2,
            'subtotal' => 1000,
        ];
    }
}
