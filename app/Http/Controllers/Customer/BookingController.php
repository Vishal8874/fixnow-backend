<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingCollection;
use App\Http\Resources\BookingDetailsResource;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request): JsonResponse
    {
        $bookings = $this->bookingService->list($request->user(), $request->all());

        return ApiResponse::paginated($bookings, BookingCollection::make($bookings->getCollection())->resolve(), 'Bookings fetched successfully.');
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->create($request->user(), $request->validated());

        return ApiResponse::success(BookingDetailsResource::make($booking), 'Booking created successfully.', 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->show($request->user(), $booking);

        return ApiResponse::success(BookingDetailsResource::make($booking), 'Booking fetched successfully.');
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->cancel($request->user(), $booking, $request->validated());

        return ApiResponse::success(BookingResource::make($booking->loadMissing('customerAddress')->loadCount('items')), 'Booking cancelled successfully.');
    }
}
