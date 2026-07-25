<?php

namespace App\Http\Controllers\Provider;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderAssignment\ProviderAssignmentActionRequest;
use App\Http\Resources\ProviderAssignmentResource;
use App\Models\ProviderAssignment;
use App\Services\ProviderAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(private readonly ProviderAssignmentService $providerAssignmentService) {}

    public function index(Request $request): JsonResponse
    {
        $assignments = $this->providerAssignmentService->listProviderAssignments($request->user(), $request->all());

        return ApiResponse::paginated($assignments, ProviderAssignmentResource::collection($assignments->getCollection())->resolve(), 'Provider assignments fetched successfully.');
    }

    public function show(Request $request, ProviderAssignment $assignment): JsonResponse
    {
        $assignment = $this->providerAssignmentService->showProviderAssignment($request->user(), $assignment);

        return ApiResponse::success(ProviderAssignmentResource::make($assignment), 'Provider assignment fetched successfully.');
    }

    public function accept(ProviderAssignmentActionRequest $request, ProviderAssignment $assignment): JsonResponse
    {
        $assignment = $this->providerAssignmentService->accept($request->user(), $assignment, $request->validated());

        return ApiResponse::success(ProviderAssignmentResource::make($assignment), 'Provider assignment accepted successfully.');
    }

    public function reject(ProviderAssignmentActionRequest $request, ProviderAssignment $assignment): JsonResponse
    {
        $assignment = $this->providerAssignmentService->reject($request->user(), $assignment, $request->validated());

        return ApiResponse::success(ProviderAssignmentResource::make($assignment), 'Provider assignment rejected successfully.');
    }

    public function complete(ProviderAssignmentActionRequest $request, ProviderAssignment $assignment): JsonResponse
    {
        $assignment = $this->providerAssignmentService->complete($request->user(), $assignment, $request->validated());

        return ApiResponse::success(ProviderAssignmentResource::make($assignment), 'Provider assignment completed successfully.');
    }
}
