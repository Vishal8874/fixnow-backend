<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewCollection;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviewService) {}

    public function index(Request $request): JsonResponse
    {
        $reviews = $this->reviewService->listForAdmin($request->all());

        return ApiResponse::paginated($reviews, ReviewCollection::make($reviews->getCollection())->resolve(), 'Reviews fetched successfully.');
    }

    public function show(Review $review): JsonResponse
    {
        $review = $this->reviewService->showForAdmin($review);

        return ApiResponse::success(ReviewResource::make($review), 'Review fetched successfully.');
    }
}
