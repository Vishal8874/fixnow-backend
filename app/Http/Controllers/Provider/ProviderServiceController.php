<?php

namespace App\Http\Controllers\Provider;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderService\StoreProviderServiceRequest;
use App\Http\Resources\ProviderServiceCollection;
use App\Http\Resources\ProviderServiceResource;
use App\Models\ProviderService;
use App\Services\ProviderServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderServiceController extends Controller
{
    public function __construct(private readonly ProviderServiceService $providerServiceService) {}

    public function index(Request $request): JsonResponse
    {
        $providerServices = $this->providerServiceService->list($request->user(), $request->all());

        return ApiResponse::paginated($providerServices, ProviderServiceCollection::make($providerServices->getCollection())->resolve(), 'Provider services fetched successfully.');
    }

    public function store(StoreProviderServiceRequest $request): JsonResponse
    {
        $providerService = $this->providerServiceService->create($request->user(), $request->validated());

        return ApiResponse::success(ProviderServiceResource::make($providerService), 'Provider service added successfully.', 201);
    }

    public function destroy(Request $request, ProviderService $providerService): JsonResponse
    {
        $this->providerServiceService->delete($request->user(), $providerService);

        return ApiResponse::success((object) [], 'Provider service removed successfully.');
    }
}
