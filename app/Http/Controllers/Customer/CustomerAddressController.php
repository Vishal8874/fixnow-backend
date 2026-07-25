<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerAddress\StoreCustomerAddressRequest;
use App\Http\Requests\CustomerAddress\UpdateCustomerAddressRequest;
use App\Http\Resources\CustomerAddressCollection;
use App\Http\Resources\CustomerAddressResource;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    public function __construct(private readonly CustomerAddressService $customerAddressService) {}

    public function index(Request $request): JsonResponse
    {
        $addresses = $this->customerAddressService->list($request->user(), $request->all());

        return ApiResponse::paginated($addresses, CustomerAddressCollection::make($addresses->getCollection())->resolve(), 'Customer addresses fetched successfully.');
    }

    public function store(StoreCustomerAddressRequest $request): JsonResponse
    {
        $address = $this->customerAddressService->create($request->user(), $request->validated());

        return ApiResponse::success(CustomerAddressResource::make($address), 'Customer address created successfully.', 201);
    }

    public function show(Request $request, CustomerAddress $address): JsonResponse
    {
        $address = $this->customerAddressService->show($request->user(), $address);

        return ApiResponse::success(CustomerAddressResource::make($address), 'Customer address fetched successfully.');
    }

    public function update(UpdateCustomerAddressRequest $request, CustomerAddress $address): JsonResponse
    {
        $address = $this->customerAddressService->update($request->user(), $address, $request->validated());

        return ApiResponse::success(CustomerAddressResource::make($address), 'Customer address updated successfully.');
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->customerAddressService->delete($request->user(), $address);

        return ApiResponse::success((object) [], 'Customer address deleted successfully.');
    }

    public function setDefault(Request $request, CustomerAddress $address): JsonResponse
    {
        $address = $this->customerAddressService->setDefault($request->user(), $address);

        return ApiResponse::success(CustomerAddressResource::make($address), 'Default customer address updated successfully.');
    }
}
