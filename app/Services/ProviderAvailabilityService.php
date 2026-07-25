<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ProviderAvailability;
use App\Models\ProviderProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProviderAvailabilityService
{
    public function show(User $provider): ProviderAvailability
    {
        $profile = $this->getProviderProfile($provider);
        $availability = $profile->availability;

        if (! $availability) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $availability;
    }

    public function create(User $provider, array $data): ProviderAvailability
    {
        $profile = $this->getProviderProfile($provider);

        if ($profile->availability()->exists()) {
            throw new HttpException(409, 'Provider availability already exists.');
        }

        return $profile->availability()->create([
            'is_available' => $data['is_available'] ?? true,
            'available_from' => $this->normalizeTime($data['available_from'] ?? null),
            'available_until' => $this->normalizeTime($data['available_until'] ?? null),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function update(User $provider, array $data): ProviderAvailability
    {
        $profile = $this->getProviderProfile($provider);
        $availability = $profile->availability;

        if (! $availability) {
            throw new HttpException(404, 'Resource not found.');
        }

        $availability->fill([
            'is_available' => $data['is_available'] ?? $availability->is_available,
            'available_from' => array_key_exists('available_from', $data)
                ? $this->normalizeTime($data['available_from'])
                : $availability->available_from,
            'available_until' => array_key_exists('available_until', $data)
                ? $this->normalizeTime($data['available_until'])
                : $availability->available_until,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $availability->notes,
        ])->save();

        return $availability->fresh();
    }

    public function isCurrentlyEligible(ProviderProfile $providerProfile, ?Carbon $currentTime = null): bool
    {
        $availability = $providerProfile->availability;

        if (! $availability || ! $availability->is_available) {
            return false;
        }

        if (! $availability->available_from || ! $availability->available_until) {
            return true;
        }

        $now = ($currentTime ?? Date::now())->setTimezone(config('app.timezone'));
        $today = $now->toDateString();
        $availableFrom = Carbon::parse($today.' '.$availability->available_from->format('H:i:s'), config('app.timezone'));
        $availableUntil = Carbon::parse($today.' '.$availability->available_until->format('H:i:s'), config('app.timezone'));

        return $now->betweenIncluded($availableFrom, $availableUntil);
    }

    protected function getProviderProfile(User $provider): ProviderProfile
    {
        if ($provider->role !== UserRole::PROVIDER || ! $provider->providerProfile) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $provider->providerProfile;
    }

    protected function normalizeTime(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return Carbon::createFromFormat('H:i', $time, config('app.timezone'))->format('H:i:s');
    }
}
