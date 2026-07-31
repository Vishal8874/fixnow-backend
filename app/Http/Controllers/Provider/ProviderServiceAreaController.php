<?php

namespace App\Http\Controllers\Provider;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderServiceArea\StoreProviderServiceAreaRequest;
use App\Http\Requests\ProviderServiceArea\UpdateProviderServiceAreaRequest;
use App\Http\Resources\ProviderServiceAreaCollection;
use App\Http\Resources\ProviderServiceAreaResource;
use App\Models\ProviderServiceArea;
use App\Services\ProviderServiceAreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderServiceAreaController extends Controller
{
    public function __construct(private readonly ProviderServiceAreaService $providerServiceAreaService) {}

    public function index(Request $request): JsonResponse
    {
        $serviceAreas = $this->providerServiceAreaService->list($request->user(), $request->all());

        return ApiResponse::paginated($serviceAreas, ProviderServiceAreaCollection::make($serviceAreas->getCollection())->resolve(), 'Provider service areas fetched successfully.');
    }

    public function store(StoreProviderServiceAreaRequest $request): JsonResponse
    {
        $serviceArea = $this->providerServiceAreaService->create($request->user(), $request->validated());

        return ApiResponse::success(ProviderServiceAreaResource::make($serviceArea), 'Provider service area created successfully.', 201);
    }

    public function update(UpdateProviderServiceAreaRequest $request, ProviderServiceArea $serviceArea): JsonResponse
    {
        $serviceArea = $this->providerServiceAreaService->update($request->user(), $serviceArea, $request->validated());

        return ApiResponse::success(ProviderServiceAreaResource::make($serviceArea), 'Provider service area updated successfully.');
    }

    public function destroy(Request $request, ProviderServiceArea $serviceArea): JsonResponse
    {
        $this->providerServiceAreaService->delete($request->user(), $serviceArea);

        return ApiResponse::success((object) [], 'Provider service area deleted successfully.');
    }
}
