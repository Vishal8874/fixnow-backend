<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    public function __construct(private readonly ProviderAssignmentService $providerAssignmentService) {}

    public function createForBooking(User $user, Booking $booking, array $data): Payment
    {
        $ownedBooking = $this->ownedBooking($user, $booking);

        if ($ownedBooking->payment()->exists()) {
            throw new HttpException(409, 'Payment already exists for this booking.');
        }

        return DB::transaction(function () use ($ownedBooking, $data, $user): Payment {
            $payment = $ownedBooking->payment()->create([
                'payment_method' => $data['payment_method'],
                'payment_status' => PaymentStatus::PENDING,
                'amount' => $ownedBooking->total,
                'gateway' => $data['gateway'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($payment->payment_method === PaymentMethod::CASH_ON_DELIVERY) {
                $this->updateBookingStatus($ownedBooking, BookingStatus::PENDING_ASSIGNMENT, 'COD payment created. Booking moved to pending assignment.', $user->id);
                $this->attemptAutomaticAssignment($ownedBooking->fresh());
            }

            return $payment->fresh();
        });
    }

    public function showForBooking(User $user, Booking $booking): Payment
    {
        $ownedBooking = $this->ownedBooking($user, $booking);
        $payment = $ownedBooking->payment;

        if (! $payment) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $payment;
    }

    public function markOnlineSuccess(Payment $payment, array $data = []): Payment
    {
        if ($payment->payment_method !== PaymentMethod::ONLINE) {
            throw new HttpException(409, 'Only online payments can be marked as successful.');
        }

        if ($payment->payment_status === PaymentStatus::PAID) {
            throw new HttpException(409, 'Payment is already marked as paid.');
        }

        return DB::transaction(function () use ($payment, $data): Payment {
            $payment->forceFill([
                'payment_status' => PaymentStatus::PAID,
                'paid_at' => now(),
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $payment->gateway_transaction_id,
                'notes' => $data['notes'] ?? $payment->notes,
            ])->save();

            $this->updateBookingStatus($payment->booking, BookingStatus::PENDING_ASSIGNMENT, 'Online payment marked successful.');
            $this->attemptAutomaticAssignment($payment->booking->fresh());

            return $payment->fresh();
        });
    }

    public function markOnlineFailed(Payment $payment, array $data = []): Payment
    {
        if ($payment->payment_method !== PaymentMethod::ONLINE) {
            throw new HttpException(409, 'Only online payments can be marked as failed.');
        }

        if ($payment->payment_status === PaymentStatus::FAILED) {
            throw new HttpException(409, 'Payment is already marked as failed.');
        }

        return DB::transaction(function () use ($payment, $data): Payment {
            $payment->forceFill([
                'payment_status' => PaymentStatus::FAILED,
                'paid_at' => null,
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $payment->gateway_transaction_id,
                'notes' => $data['notes'] ?? $payment->notes,
            ])->save();

            return $payment->fresh();
        });
    }

    public function markCodPaid(Payment $payment, array $data = []): Payment
    {
        if ($payment->payment_method !== PaymentMethod::CASH_ON_DELIVERY) {
            throw new HttpException(409, 'Only cash on delivery payments can be marked as paid.');
        }

        if ($payment->payment_status === PaymentStatus::PAID) {
            throw new HttpException(409, 'Payment is already marked as paid.');
        }

        if ($payment->booking->status !== BookingStatus::COMPLETED) {
            throw new HttpException(409, 'Cash on delivery payments can only be marked as paid after the booking is completed.');
        }

        return DB::transaction(function () use ($payment, $data): Payment {
            $payment->forceFill([
                'payment_status' => PaymentStatus::PAID,
                'paid_at' => now(),
                'notes' => $data['notes'] ?? $payment->notes,
            ])->save();

            return $payment->fresh();
        });
    }

    protected function ownedBooking(User $user, Booking $booking): Booking
    {
        if ($booking->user_id !== $user->id) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $booking;
    }

    protected function updateBookingStatus(Booking $booking, BookingStatus $status, string $remarks, ?int $createdBy = null): void
    {
        $booking->forceFill([
            'status' => $status,
        ])->save();

        $booking->statusHistories()->create([
            'status' => $status,
            'remarks' => $remarks,
            'created_by' => $createdBy,
        ]);
    }

    protected function attemptAutomaticAssignment(Booking $booking): void
    {
        try {
            $this->providerAssignmentService->assignAutomatically($booking);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }
        }
    }
}
