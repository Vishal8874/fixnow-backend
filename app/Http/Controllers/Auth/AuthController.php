<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Http\Requests\Auth\RegisterProviderRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function registerCustomer(RegisterCustomerRequest $request): JsonResponse
    {
        $result = $this->authService->registerCustomer($request->validated());

        return ApiResponse::success([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ], 'Customer registered successfully.', 201);
    }

    public function registerProvider(RegisterProviderRequest $request): JsonResponse
    {
        $result = $this->authService->registerProvider($request->validated());

        return ApiResponse::success([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ], 'Provider account created successfully. Complete onboarding later to start accepting bookings.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return ApiResponse::success([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ], 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(UserResource::make($request->user()), 'Authenticated user fetched successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success((object) [], 'Logout successful.');
    }

    public function googleRedirect(): JsonResponse
    {
        return ApiResponse::success([
            'redirect_url' => $this->authService->googleRedirectUrl(),
        ], 'Google redirect URL generated successfully.');
    }

    public function googleCallback(): JsonResponse
    {
        $result = $this->authService->handleGoogleCallback();

        return ApiResponse::success([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ], 'Google login successful.');
    }
}
