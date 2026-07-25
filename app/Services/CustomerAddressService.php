<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerAddressService
{
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        return $user->customerAddresses()
            ->latest('is_default')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function create(User $user, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data): CustomerAddress {
            $hasExistingAddresses = $user->customerAddresses()->exists();
            $shouldBeDefault = ! $hasExistingAddresses || ($data['is_default'] ?? false);

            if ($shouldBeDefault) {
                $this->clearDefaultForUser($user);
            }

            return $user->customerAddresses()->create([
                'label' => $data['label'],
                'contact_person' => $data['contact_person'],
                'contact_phone' => $data['contact_phone'],
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $data['address_line_2'] ?? null,
                'landmark' => $data['landmark'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_default' => $shouldBeDefault,
            ]);
        });
    }

    public function show(User $user, CustomerAddress $address): CustomerAddress
    {
        return $this->ownedAddress($user, $address);
    }

    public function update(User $user, CustomerAddress $address, array $data): CustomerAddress
    {
        $ownedAddress = $this->ownedAddress($user, $address);

        return DB::transaction(function () use ($user, $ownedAddress, $data): CustomerAddress {
            $shouldBeDefault = (bool) ($data['is_default'] ?? false);

            if ($shouldBeDefault) {
                $this->clearDefaultForUser($user, $ownedAddress->id);
            }

            $ownedAddress->fill([
                'label' => $data['label'] ?? $ownedAddress->label,
                'contact_person' => $data['contact_person'] ?? $ownedAddress->contact_person,
                'contact_phone' => $data['contact_phone'] ?? $ownedAddress->contact_phone,
                'address_line_1' => $data['address_line_1'] ?? $ownedAddress->address_line_1,
                'address_line_2' => array_key_exists('address_line_2', $data) ? $data['address_line_2'] : $ownedAddress->address_line_2,
                'landmark' => array_key_exists('landmark', $data) ? $data['landmark'] : $ownedAddress->landmark,
                'city' => $data['city'] ?? $ownedAddress->city,
                'state' => $data['state'] ?? $ownedAddress->state,
                'postal_code' => $data['postal_code'] ?? $ownedAddress->postal_code,
                'latitude' => array_key_exists('latitude', $data) ? $data['latitude'] : $ownedAddress->latitude,
                'longitude' => array_key_exists('longitude', $data) ? $data['longitude'] : $ownedAddress->longitude,
                'is_default' => $shouldBeDefault ? true : $ownedAddress->is_default,
            ])->save();

            if (! $user->customerAddresses()->where('is_default', true)->exists()) {
                $ownedAddress->forceFill(['is_default' => true])->save();
            }

            return $ownedAddress->fresh();
        });
    }

    public function delete(User $user, CustomerAddress $address): void
    {
        $ownedAddress = $this->ownedAddress($user, $address);

        DB::transaction(function () use ($user, $ownedAddress): void {
            $wasDefault = $ownedAddress->is_default;
            $ownedAddress->delete();

            if ($wasDefault) {
                $replacement = $user->customerAddresses()->latest('id')->first();

                if ($replacement) {
                    $this->clearDefaultForUser($user, $replacement->id);
                    $replacement->forceFill(['is_default' => true])->save();
                }
            }
        });
    }

    public function setDefault(User $user, CustomerAddress $address): CustomerAddress
    {
        $ownedAddress = $this->ownedAddress($user, $address);

        return DB::transaction(function () use ($user, $ownedAddress): CustomerAddress {
            $this->clearDefaultForUser($user, $ownedAddress->id);
            $ownedAddress->forceFill(['is_default' => true])->save();

            return $ownedAddress->fresh();
        });
    }

    protected function ownedAddress(User $user, CustomerAddress $address): CustomerAddress
    {
        if ($address->user_id !== $user->id) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $address;
    }

    protected function clearDefaultForUser(User $user, ?int $exceptAddressId = null): void
    {
        $user->customerAddresses()
            ->when($exceptAddressId, fn ($query) => $query->whereKeyNot($exceptAddressId))
            ->update(['is_default' => false]);
    }
}
