<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\PaymentManagementController;
use App\Http\Controllers\Admin\ProviderApprovalController;
use App\Http\Controllers\Admin\ProviderAssignmentController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Customer\BookingController;
use App\Http\Controllers\Customer\CustomerAddressController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Gateway\PaymentCallbackController;
use App\Http\Controllers\Provider\AssignmentController;
use App\Http\Controllers\Provider\ProviderAvailabilityController;
use App\Http\Controllers\Provider\ProviderProfileController;
use App\Http\Controllers\Provider\ProviderServiceAreaController;
use App\Http\Controllers\Provider\ProviderServiceController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now(),
    ]);
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register/customer', [AuthController::class, 'registerCustomer'])->middleware('throttle:auth-register');
    Route::post('/register/provider', [AuthController::class, 'registerProvider'])->middleware('throttle:auth-register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::get('/google/redirect', [AuthController::class, 'googleRedirect']);
    Route::get('/google/callback', [AuthController::class, 'googleCallback'])->middleware('throttle:auth-google-callback');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}/services', [CategoryController::class, 'services']);
Route::get('/services', [ServiceController::class, 'index']);

// Simulated payment gateway callback (no auth — verified by gateway signature in production)
Route::post('/gateway/payment/callback', [PaymentCallbackController::class, 'handleCallback']);

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function (): void {
        Route::apiResource('categories', AdminCategoryController::class)->except(['create', 'edit']);
        Route::apiResource('services', AdminServiceController::class)->except(['create', 'edit']);
        Route::get('/providers/pending', [ProviderApprovalController::class, 'pending']);
        Route::get('/providers', [ProviderApprovalController::class, 'index']);
        Route::get('/providers/{provider}', [ProviderApprovalController::class, 'show']);
        Route::patch('/providers/{provider}/approve', [ProviderApprovalController::class, 'approve']);
        Route::patch('/providers/{provider}/reject', [ProviderApprovalController::class, 'reject']);
        Route::patch('/payments/{payment}/failed', [PaymentManagementController::class, 'failed']);
        Route::post('/bookings/{booking}/assign', [ProviderAssignmentController::class, 'store']);
        Route::get('/reviews', [AdminReviewController::class, 'index']);
        Route::get('/reviews/{review}', [AdminReviewController::class, 'show']);
    });

Route::prefix('customer')
    ->middleware(['auth:sanctum', 'customer'])
    ->group(function (): void {
        Route::get('/addresses', [CustomerAddressController::class, 'index']);
        Route::post('/addresses', [CustomerAddressController::class, 'store']);
        Route::get('/addresses/{address}', [CustomerAddressController::class, 'show']);
        Route::patch('/addresses/{address}', [CustomerAddressController::class, 'update']);
        Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy']);
        Route::patch('/addresses/{address}/default', [CustomerAddressController::class, 'setDefault']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('/bookings/{booking}/payment', [PaymentController::class, 'store']);
        Route::get('/bookings/{booking}/payment', [PaymentController::class, 'show']);
        Route::post('/bookings/{booking}/review', [CustomerReviewController::class, 'store']);
        Route::get('/reviews', [CustomerReviewController::class, 'index']);
        Route::get('/reviews/{review}', [CustomerReviewController::class, 'show']);
        Route::patch('/reviews/{review}', [CustomerReviewController::class, 'update']);
    });

Route::prefix('provider')
    ->middleware(['auth:sanctum', 'provider'])
    ->group(function (): void {
        Route::get('/profile', [ProviderProfileController::class, 'show']);
        Route::post('/profile', [ProviderProfileController::class, 'store']);
        Route::patch('/profile', [ProviderProfileController::class, 'update']);
        Route::get('/services', [ProviderServiceController::class, 'index']);
        Route::post('/services', [ProviderServiceController::class, 'store']);
        Route::delete('/services/{providerService}', [ProviderServiceController::class, 'destroy']);
        Route::get('/service-areas', [ProviderServiceAreaController::class, 'index']);
        Route::post('/service-areas', [ProviderServiceAreaController::class, 'store']);
        Route::patch('/service-areas/{serviceArea}', [ProviderServiceAreaController::class, 'update']);
        Route::delete('/service-areas/{serviceArea}', [ProviderServiceAreaController::class, 'destroy']);
        Route::get('/availability', [ProviderAvailabilityController::class, 'show']);
        Route::post('/availability', [ProviderAvailabilityController::class, 'store']);
        Route::patch('/availability', [ProviderAvailabilityController::class, 'update']);
        Route::get('/assignments', [AssignmentController::class, 'index']);
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
        Route::patch('/assignments/{assignment}/accept', [AssignmentController::class, 'accept']);
        Route::patch('/assignments/{assignment}/reject', [AssignmentController::class, 'reject']);
        Route::patch('/assignments/{assignment}/on-the-way', [AssignmentController::class, 'onTheWay']);
        Route::patch('/assignments/{assignment}/arrived', [AssignmentController::class, 'arrived']);
        Route::patch('/assignments/{assignment}/in-progress', [AssignmentController::class, 'inProgress']);
        Route::patch('/assignments/{assignment}/complete', [AssignmentController::class, 'complete']);
        Route::patch('/assignments/{assignment}/confirm-cod-payment', [AssignmentController::class, 'confirmCodPayment']);
    });
