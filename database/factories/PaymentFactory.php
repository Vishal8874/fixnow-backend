<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $booking = Booking::factory();

        return [
            'booking_id' => $booking,
            'payment_method' => PaymentMethod::ONLINE,
            'payment_status' => PaymentStatus::PENDING,
            'amount' => 1075.00,
            'paid_at' => null,
            'gateway' => 'simulated',
            'gateway_transaction_id' => null,
            'notes' => null,
        ];
    }
}
