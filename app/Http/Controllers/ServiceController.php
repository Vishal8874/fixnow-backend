<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\ServiceCollection;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceService $serviceService) {}

    public function index(Request $request): JsonResponse
    {
        $services = $this->serviceService->getPublicPaginatedList($request->all(), $request->user());

        return ApiResponse::paginated($services, ServiceCollection::make($services->getCollection())->resolve(), 'Services fetched successfully.');
    }
}
