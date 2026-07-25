<?php

namespace App\Http\Controllers\Provider;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderAvailability\StoreProviderAvailabilityRequest;
use App\Http\Requests\ProviderAvailability\UpdateProviderAvailabilityRequest;
use App\Http\Resources\ProviderAvailabilityResource;
use App\Services\ProviderAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderAvailabilityController extends Controller
{
    public function __construct(private readonly ProviderAvailabilityService $providerAvailabilityService) {}

    public function show(Request $request): JsonResponse
    {
        $availability = $this->providerAvailabilityService->show($request->user());

        return ApiResponse::success(ProviderAvailabilityResource::make($availability), 'Provider availability fetched successfully.');
    }

    public function store(StoreProviderAvailabilityRequest $request): JsonResponse
    {
        $availability = $this->providerAvailabilityService->create($request->user(), $request->validated());

        return ApiResponse::success(ProviderAvailabilityResource::make($availability), 'Provider availability created successfully.', 201);
    }

    public function update(UpdateProviderAvailabilityRequest $request): JsonResponse
    {
        $availability = $this->providerAvailabilityService->update($request->user(), $request->validated());

        return ApiResponse::success(ProviderAvailabilityResource::make($availability), 'Provider availability updated successfully.');
    }
}
