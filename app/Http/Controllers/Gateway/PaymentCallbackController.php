<?php

namespace App\Http\Controllers\Gateway;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\GatewayCallbackRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentCallbackController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function handleCallback(GatewayCallbackRequest $request): JsonResponse
    {
        $payment = Payment::query()->findOrFail($request->validated('payment_id'));
        $payment = $this->paymentService->handleGatewayCallback($payment, $request->validated());

        return ApiResponse::success(PaymentResource::make($payment), 'Payment callback processed successfully.');
    }
}
