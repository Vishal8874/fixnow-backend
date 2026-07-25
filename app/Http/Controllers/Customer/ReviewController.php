<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewCollection;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviewService) {}

    public function index(Request $request): JsonResponse
    {
        $reviews = $this->reviewService->listForCustomer($request->user(), $request->all());

        return ApiResponse::paginated($reviews, ReviewCollection::make($reviews->getCollection())->resolve(), 'Reviews fetched successfully.');
    }

    public function store(StoreReviewRequest $request, Booking $booking): JsonResponse
    {
        $review = $this->reviewService->create($request->user(), $booking, $request->validated());

        return ApiResponse::success(ReviewResource::make($review), 'Review created successfully.', 201);
    }

    public function show(Request $request, Review $review): JsonResponse
    {
        $review = $this->reviewService->showForCustomer($request->user(), $review);

        return ApiResponse::success(ReviewResource::make($review), 'Review fetched successfully.');
    }

    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        $review = $this->reviewService->update($request->user(), $review, $request->validated());

        return ApiResponse::success(ReviewResource::make($review), 'Review updated successfully.');
    }
}
