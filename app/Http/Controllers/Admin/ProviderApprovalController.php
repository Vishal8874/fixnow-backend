<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderProfile\ApproveProviderRequest;
use App\Http\Requests\ProviderProfile\RejectProviderRequest;
use App\Http\Resources\ProviderProfileResource;
use App\Models\User;
use App\Services\ProviderProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderApprovalController extends Controller
{
    public function __construct(private readonly ProviderProfileService $providerProfileService) {}

    public function pending(Request $request): JsonResponse
    {
        $providers = $this->providerProfileService->listPending($request->all());

        return ApiResponse::paginated($providers, ProviderProfileResource::collection($providers->getCollection())->resolve(), 'Pending providers fetched successfully.');
    }

    public function index(Request $request): JsonResponse
    {
        $providers = $this->providerProfileService->listAll($request->all());

        return ApiResponse::paginated($providers, ProviderProfileResource::collection($providers->getCollection())->resolve(), 'Providers fetched successfully.');
    }

    public function show(User $provider): JsonResponse
    {
        $profile = $this->providerProfileService->viewProviderForAdmin($provider);

        return ApiResponse::success(ProviderProfileResource::make($profile), 'Provider fetched successfully.');
    }

    public function approve(ApproveProviderRequest $request, User $provider): JsonResponse
    {
        $profile = $this->providerProfileService->approve($provider);

        return ApiResponse::success(ProviderProfileResource::make($profile), 'Provider approved successfully.');
    }

    public function reject(RejectProviderRequest $request, User $provider): JsonResponse
    {
        $profile = $this->providerProfileService->reject($provider);

        return ApiResponse::success(ProviderProfileResource::make($profile), 'Provider rejected successfully.');
    }
}
