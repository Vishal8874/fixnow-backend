<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ProviderProfile;
use App\Models\ProviderServiceArea;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProviderServiceAreaService
{
    public function list(User $provider, array $filters): LengthAwarePaginator
    {
        $profile = $this->getProviderProfile($provider);

        return $profile->serviceAreas()
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function create(User $provider, array $data): ProviderServiceArea
    {
        $profile = $this->getProviderProfile($provider);

        if ($profile->serviceAreas()->where('postal_code', $data['postal_code'])->exists()) {
            throw new HttpException(409, 'This postal code is already covered by the provider.');
        }

        return DB::transaction(function () use ($profile, $data): ProviderServiceArea {
            return $profile->serviceAreas()->create([
                'postal_code' => $data['postal_code'],
                'city' => $data['city'],
                'state' => $data['state'],
            ]);
        });
    }

    public function update(User $provider, ProviderServiceArea $serviceArea, array $data): ProviderServiceArea
    {
        $ownedServiceArea = $this->ownedServiceArea($provider, $serviceArea);

        $postalCode = $data['postal_code'] ?? $ownedServiceArea->postal_code;

        $duplicateExists = $ownedServiceArea->providerProfile
            ->serviceAreas()
            ->where('postal_code', $postalCode)
            ->whereKeyNot($ownedServiceArea->id)
            ->exists();

        if ($duplicateExists) {
            throw new HttpException(409, 'This postal code is already covered by the provider.');
        }

        $ownedServiceArea->fill([
            'postal_code' => $postalCode,
            'city' => $data['city'] ?? $ownedServiceArea->city,
            'state' => $data['state'] ?? $ownedServiceArea->state,
        ])->save();

        return $ownedServiceArea->fresh();
    }

    public function delete(User $provider, ProviderServiceArea $serviceArea): void
    {
        $ownedServiceArea = $this->ownedServiceArea($provider, $serviceArea);

        $ownedServiceArea->delete();
    }

    protected function getProviderProfile(User $provider): ProviderProfile
    {
        if ($provider->role !== UserRole::PROVIDER || ! $provider->providerProfile) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $provider->providerProfile;
    }

    protected function ownedServiceArea(User $provider, ProviderServiceArea $serviceArea): ProviderServiceArea
    {
        $profile = $this->getProviderProfile($provider);

        if ($serviceArea->provider_profile_id !== $profile->id) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $serviceArea->loadMissing('providerProfile');
    }
}
