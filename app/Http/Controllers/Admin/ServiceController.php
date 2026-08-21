<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceCollection;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceService $serviceService) {}

    public function index(Request $request): JsonResponse
    {
        $services = $this->serviceService->getAdminPaginatedList($request->all());

        return ApiResponse::paginated($services, ServiceCollection::make($services->getCollection())->resolve(), 'Services fetched successfully.');
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->serviceService->create($request->validated());

        return ApiResponse::success(ServiceResource::make($service), 'Service created successfully.', 201);
    }

    public function show(Service $service): JsonResponse
    {
        $service->load('category');

        return ApiResponse::success(ServiceResource::make($service), 'Service fetched successfully.');
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        \Log::info('SERVICE UPDATE DATA', [
            'validated' => $request->validated(),
            'image' => $request->file('image'),
            'image_type' => $request->hasFile('image') ? get_class($request->file('image')) : null,
        ]);

        $service = $this->serviceService->update($service, $request->validated());

        return ApiResponse::success(ServiceResource::make($service), 'Service updated successfully.');
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->serviceService->delete($service);

        return ApiResponse::success((object) [], 'Service deleted successfully.');
    }
}
