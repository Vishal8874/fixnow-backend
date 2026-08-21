<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerProfile\UpdateCustomerProfileRequest;
use App\Http\Resources\CustomerProfileResource;
use App\Services\CustomerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProfileController extends Controller
{
    public function __construct(
        private readonly CustomerProfileService $customerProfileService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $profile = $this->customerProfileService->getProfile(
            $request->user()
        );

        return ApiResponse::success(
            CustomerProfileResource::make($profile),
            'Customer profile fetched successfully.'
        );
    }

    public function update(UpdateCustomerProfileRequest $request): JsonResponse
    {
        $profile = $this->customerProfileService->update(
            $request->user(),
            $request->validated()
        );

        return ApiResponse::success(
            CustomerProfileResource::make($profile),
            'Customer profile updated successfully.'
        );
    }
}