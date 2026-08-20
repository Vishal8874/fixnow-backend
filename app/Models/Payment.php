<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'payment_method',
        'payment_status',
        'amount',
        'currency',
        'paid_at',
        'gateway',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
        ];
    }

    /**
     * Get the booking associated with the payment.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}