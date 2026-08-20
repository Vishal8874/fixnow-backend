<?php

namespace App\Services;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use RuntimeException;

class RazorpayService
{
    private Api $razorpay;

    public function __construct()
    {
        $this->razorpay = new Api(
            config('razorpay.key_id'),
            config('razorpay.key_secret')
        );
    }

    public function createOrder(float $amount, string $receipt): array
    {
        $order = $this->razorpay->order->create([
            'amount' => (int) round($amount * 100),
            'currency' => 'INR',
            'receipt' => $receipt,
        ]);

        return [
            'id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'status' => $order['status'],
        ];
    }

    public function verifyPayment(
        string $orderId,
        string $paymentId,
        string $signature
    ): bool {
        try {
            $this->razorpay->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }
}