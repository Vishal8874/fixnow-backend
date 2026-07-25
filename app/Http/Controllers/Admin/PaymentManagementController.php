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

    public function success(UpdatePaymentStatusRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->paymentService->markOnlineSuccess($payment, $request->validated());

        return ApiResponse::success(PaymentResource::make($payment), 'Payment marked successful.');
    }

    public function failed(UpdatePaymentStatusRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->paymentService->markOnlineFailed($payment, $request->validated());

        return ApiResponse::success(PaymentResource::make($payment), 'Payment marked failed.');
    }

    public function codPaid(UpdatePaymentStatusRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->paymentService->markCodPaid($payment, $request->validated());

        return ApiResponse::success(PaymentResource::make($payment), 'COD payment marked paid.');
    }
}
