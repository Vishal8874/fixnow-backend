<?php

namespace App\Http\Controllers\Provider;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderProfile\StoreProviderProfileRequest;
use App\Http\Requests\ProviderProfile\UpdateProviderProfileRequest;
use App\Http\Resources\ProviderProfileResource;
use App\Services\ProviderProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderProfileController extends Controller
{
    public function __construct(private readonly ProviderProfileService $providerProfileService) {}

    public function show(Request $request): JsonResponse
    {
        $profile = $this->providerProfileService->view($request->user());

        return ApiResponse::success(ProviderProfileResource::make($profile), 'Provider profile fetched successfully.');
    }

    public function store(StoreProviderProfileRequest $request): JsonResponse
    {
        $profile = $this->providerProfileService->create($request->user(), $request->validated());

        return ApiResponse::success(ProviderProfileResource::make($profile), 'Provider profile created successfully.', 201);
    }

    public function update(UpdateProviderProfileRequest $request): JsonResponse
    {
        $profile = $this->providerProfileService->update($request->user(), $request->validated());

        return ApiResponse::success(ProviderProfileResource::make($profile), 'Provider profile updated successfully.');
    }
}
