<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderAssignment\AssignProviderRequest;
use App\Http\Resources\ProviderAssignmentResource;
use App\Models\Booking;
use App\Services\ProviderAssignmentService;
use Illuminate\Http\JsonResponse;

class ProviderAssignmentController extends Controller
{
    public function __construct(private readonly ProviderAssignmentService $providerAssignmentService) {}

    public function store(AssignProviderRequest $request, Booking $booking): JsonResponse
    {
        $assignment = $this->providerAssignmentService->assignProvider($booking, $request->validated());

        if (! $assignment) {
            return ApiResponse::success((object) [], 'No provider is currently available for this booking.');
        }

        return ApiResponse::success(ProviderAssignmentResource::make($assignment), 'Provider assigned successfully.', 201);
    }
}
