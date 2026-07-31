<?php

namespace App\Services;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\ProviderProfile;
use App\Models\ProviderService;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProviderServiceService
{
    public function list(User $provider, array $filters): LengthAwarePaginator
    {
        $profile = $this->getProviderProfile($provider);

        return $profile->providerServices()
            ->with(['service.category'])
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function create(User $provider, array $data): ProviderService
    {
        $profile = $this->getProviderProfile($provider);
        $service = $this->validateAssignableService((int) $data['service_id']);

        if ($profile->providerServices()->where('service_id', $service->id)->exists()) {
            throw new HttpException(409, 'This service is already selected by the provider.');
        }

        return DB::transaction(function () use ($profile, $service): ProviderService {
            return $profile->providerServices()->create([
                'service_id' => $service->id,
            ])->load(['service.category']);
        });
    }

    public function delete(User $provider, ProviderService $providerService): void
    {
        $ownedProviderService = $this->ownedProviderService($provider, $providerService);

        $ownedProviderService->delete();
    }

    protected function getProviderProfile(User $provider): ProviderProfile
    {
        if ($provider->role !== UserRole::PROVIDER || ! $provider->providerProfile) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $provider->providerProfile;
    }

    protected function ownedProviderService(User $provider, ProviderService $providerService): ProviderService
    {
        $profile = $this->getProviderProfile($provider);

        if ($providerService->provider_profile_id !== $profile->id) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $providerService;
    }

    protected function validateAssignableService(int $serviceId): \App\Models\Service
    {
        $service = \App\Models\Service::query()
            ->with('category')
            ->whereKey($serviceId)
            ->first();

        if (! $service || $service->status !== Status::ACTIVE || ! $service->category || $service->category->status !== Status::ACTIVE) {
            throw new HttpException(422, 'Only active services from active categories can be selected.');
        }

        return $service;
    }
}
