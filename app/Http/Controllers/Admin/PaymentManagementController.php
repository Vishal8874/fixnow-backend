<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\UpdatePaymentStatusRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentManagementController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * Admin operational override: manually mark an online payment as failed.
     * This is for exceptional cases only, not the normal booking lifecycle.
     */
    public function failed(UpdatePaymentStatusRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->paymentService->markOnlineFailed($payment, $request->validated());

        return ApiResponse::success(PaymentResource::make($payment), 'Payment marked failed.');
    }
}
