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
    public function __construct(private readonly ProviderAssignmentService $providerAssignmentService, private readonly RazorpayService $razorpayService) {}

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
                $this->updateBookingStatus($ownedBooking, BookingStatus::PENDING_ASSIGNMENT, 'COD payment selected. Booking moved to pending assignment.', $user->id);
                $this->attemptAutomaticAssignment($ownedBooking->fresh());
            }

            if ($payment->payment_method === PaymentMethod::ONLINE) {
                $order = $this->razorpayService->createOrder((float) $ownedBooking->total, 'booking_' . $ownedBooking->id);

                $payment
                    ->forceFill([
                        'razorpay_order_id' => $order['id'],
                        'gateway' => 'razorpay',
                    ])
                    ->save();

                $this->updateBookingStatus($ownedBooking, BookingStatus::PENDING_PAYMENT, 'Online payment initiated. Awaiting gateway confirmation.', $user->id);
            }

            return $payment->fresh();
        });
    }

    public function showForBooking(User $user, Booking $booking): Payment
    {
        $ownedBooking = $this->ownedBooking($user, $booking);
        $payment = $ownedBooking->payment;

        if (!$payment) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $payment;
    }

    public function handleGatewayCallback(Payment $payment, array $data): Payment
    {
        if ($payment->payment_method !== PaymentMethod::ONLINE) {
            throw new HttpException(409, 'Only online payments can be processed by the gateway.');
        }

        if ($payment->payment_status !== PaymentStatus::PENDING) {
            throw new HttpException(409, 'This payment has already been processed.');
        }

        $status = $data['status'] ?? 'failed';

        if ($status === 'success') {
            return $this->processGatewaySuccess($payment, $data);
        }

        return $this->processGatewayFailure($payment, $data);
    }

    /**
     * Admin operational override: manually mark an online payment as failed.
     * This is for exceptional cases only, not the normal booking lifecycle.
     */
    public function markOnlineFailed(Payment $payment, array $data = []): Payment
    {
        if ($payment->payment_method !== PaymentMethod::ONLINE) {
            throw new HttpException(409, 'Only online payments can be marked as failed.');
        }

        if ($payment->payment_status === PaymentStatus::FAILED) {
            throw new HttpException(409, 'Payment is already marked as failed.');
        }

        return DB::transaction(function () use ($payment, $data): Payment {
            $payment
                ->forceFill([
                    'payment_status' => PaymentStatus::FAILED,
                    'paid_at' => null,
                    'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $payment->gateway_transaction_id,
                    'notes' => $data['notes'] ?? $payment->notes,
                ])
                ->save();

            return $payment->fresh();
        });
    }

    protected function processGatewaySuccess(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data): Payment {
            $payment
                ->forceFill([
                    'payment_status' => PaymentStatus::PAID,
                    'paid_at' => now(),
                    'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $payment->gateway_transaction_id,
                    'notes' => $data['notes'] ?? $payment->notes,
                ])
                ->save();

            $this->updateBookingStatus($payment->booking, BookingStatus::PENDING_ASSIGNMENT, 'Online payment confirmed. Booking moved to pending assignment.');
            $this->attemptAutomaticAssignment($payment->booking->fresh());

            return $payment->fresh();
        });
    }

    public function verifyPayment(User $user, Booking $booking, array $data): Payment
    {
        $ownedBooking = $this->ownedBooking($user, $booking);

        $payment = $ownedBooking->payment;

        if (!$payment) {
            throw new HttpException(404, 'Payment not found.');
        }

        if ($payment->payment_method !== PaymentMethod::ONLINE) {
            throw new HttpException(409, 'Only online payments can be verified.');
        }

        if ($payment->payment_status !== PaymentStatus::PENDING) {
            throw new HttpException(409, 'This payment has already been processed.');
        }

        if (!$payment->razorpay_order_id) {
            throw new HttpException(409, 'Razorpay order ID is missing.');
        }

        $paymentId = $data['razorpay_payment_id'];
        $signature = $data['razorpay_signature'];

        /*
         * Verify Razorpay signature.
         */
        $isValid = $this->razorpayService->verifyPayment($payment->razorpay_order_id, $paymentId, $signature);

        if (!$isValid) {
            throw new HttpException(400, 'Payment verification failed.');
        }

        return DB::transaction(function () use ($payment, $paymentId): Payment {
            $payment
                ->forceFill([
                    'payment_status' => PaymentStatus::PAID,
                    'razorpay_payment_id' => $paymentId,
                    'paid_at' => now(),
                    'gateway' => 'razorpay',
                ])
                ->save();

            $this->updateBookingStatus($payment->booking, BookingStatus::PENDING_ASSIGNMENT, 'Online payment verified successfully. Booking moved to pending assignment.');

            $this->attemptAutomaticAssignment($payment->booking->fresh());

            return $payment->fresh();
        });
    }

    protected function processGatewayFailure(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data): Payment {
            $payment
                ->forceFill([
                    'payment_status' => PaymentStatus::FAILED,
                    'paid_at' => null,
                    'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $payment->gateway_transaction_id,
                    'notes' => $data['notes'] ?? $payment->notes,
                ])
                ->save();

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
        $booking
            ->forceFill([
                'status' => $status,
            ])
            ->save();

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
