<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function store(CreatePaymentRequest $request, Booking $booking): JsonResponse
    {
        $payment = $this->paymentService->createForBooking($request->user(), $booking, $request->validated());

        return ApiResponse::success(PaymentResource::make($payment), 'Payment created successfully.', 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $payment = $this->paymentService->showForBooking($request->user(), $booking);

        return ApiResponse::success(PaymentResource::make($payment), 'Payment fetched successfully.');
    }
}
