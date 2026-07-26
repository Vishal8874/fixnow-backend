<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\Status;
use App\Models\Booking;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingService
{
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        return $user->bookings()
            ->with(['customerAddress'])
            ->withCount('items')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function show(User $user, Booking $booking): Booking
    {
        $ownedBooking = $this->ownedBooking($user, $booking);

        return $ownedBooking->load(['customerAddress', 'items', 'statusHistories', 'payment']);
    }

    public function create(User $user, array $data): Booking
    {
        $address = $this->ownedAddress($user, (int) $data['customer_address_id']);
        $services = $this->resolveBookableServices($data['services']);
        $totals = $this->calculateTotals($services, $data);

        return $this->createBookingWithRetry($user, $address, $data, $services, $totals);
    }

    public function cancel(User $user, Booking $booking, array $data): Booking
    {
        $ownedBooking = $this->ownedBooking($user, $booking);

        if ($ownedBooking->status === BookingStatus::CANCELLED) {
            throw new HttpException(409, 'Booking is already cancelled.');
        }

        $cancellableStatuses = [BookingStatus::CREATED, BookingStatus::PENDING_PAYMENT];

        if (! in_array($ownedBooking->status, $cancellableStatuses, true)) {
            throw new HttpException(409, 'Booking cannot be cancelled after payment has been made.');
        }

        return DB::transaction(function () use ($ownedBooking, $user, $data): Booking {
            $ownedBooking->forceFill([
                'status' => BookingStatus::CANCELLED,
            ])->save();

            $ownedBooking->statusHistories()->create([
                'status' => BookingStatus::CANCELLED,
                'remarks' => $data['remarks'] ?? 'Booking cancelled by customer.',
                'created_by' => $user->id,
            ]);

            return $ownedBooking->load(['customerAddress', 'items', 'statusHistories', 'payment']);
        });
    }

    protected function ownedBooking(User $user, Booking $booking): Booking
    {
        if ($booking->user_id !== $user->id) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $booking;
    }

    protected function ownedAddress(User $user, int $addressId): CustomerAddress
    {
        $address = CustomerAddress::query()->findOrFail($addressId);

        if ($address->user_id !== $user->id) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $address;
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @return Collection<int, array<string, mixed>>
     */
    protected function resolveBookableServices(array $services): Collection
    {
        $items = collect($services)->values();
        $requestedServiceIds = $items->pluck('service_id')->map(fn (mixed $serviceId): int => (int) $serviceId)->unique()->values();

        $serviceModels = Service::query()
            ->with('category')
            ->whereIn('id', $requestedServiceIds)
            ->get()
            ->keyBy('id');

        if ($serviceModels->count() !== $requestedServiceIds->count()) {
            throw new HttpException(422, 'One or more selected services are not available for booking.');
        }

        return $items->map(function (array $item) use ($serviceModels): array {
            /** @var Service|null $service */
            $service = $serviceModels->get((int) $item['service_id']);

            if (! $service || $service->status !== Status::ACTIVE || ! $service->category || $service->category->status !== Status::ACTIVE) {
                throw new HttpException(422, 'One or more selected services are not available for booking.');
            }

            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $service->base_price;

            return [
                'service' => $service,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice * $quantity,
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $services
     * @return array<string, float>
     */
    protected function calculateTotals(Collection $services, array $data): array
    {
        $subtotal = (float) $services->sum('subtotal');
        $serviceCharge = (float) ($data['service_charge'] ?? 0);
        $tax = (float) ($data['tax'] ?? 0);
        $discount = (float) ($data['discount'] ?? 0);
        $total = $subtotal + $serviceCharge + $tax - $discount;

        if ($total < 0) {
            throw new HttpException(422, 'Booking total cannot be negative.');
        }

        return [
            'subtotal' => round($subtotal, 2),
            'service_charge' => round($serviceCharge, 2),
            'tax' => round($tax, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
        ];
    }

    protected function generateBookingNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'BK'.$year;
        $latestBooking = Booking::query()
            ->where('booking_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->latest('id')
            ->first();

        $nextSequence = 1;

        if ($latestBooking) {
            $nextSequence = ((int) substr($latestBooking->booking_number, -5)) + 1;
        }

        return sprintf('%s%05d', $prefix, $nextSequence);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $services
     * @param  array<string, float|int|string|null>  $totals
     */
    protected function createBookingWithRetry(User $user, CustomerAddress $address, array $data, Collection $services, array $totals): Booking
    {
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $address, $data, $services, $totals): Booking {
                    $booking = Booking::query()->create([
                        'booking_number' => $this->generateBookingNumber(),
                        'user_id' => $user->id,
                        'customer_address_id' => $address->id,
                        'booking_date' => $data['booking_date'],
                        'booking_time' => $data['booking_time'],
                        'special_instructions' => $data['special_instructions'] ?? null,
                        'status' => BookingStatus::CREATED,
                        'subtotal' => $totals['subtotal'],
                        'service_charge' => $totals['service_charge'],
                        'tax' => $totals['tax'],
                        'discount' => $totals['discount'],
                        'total' => $totals['total'],
                    ]);

                    $booking->items()->createMany($services->map(fn (array $service): array => [
                        'service_id' => $service['service']->id,
                        'service_name' => $service['service']->name,
                        'unit_price' => $service['unit_price'],
                        'quantity' => $service['quantity'],
                        'subtotal' => $service['subtotal'],
                    ])->all());

                    $booking->statusHistories()->create([
                        'status' => BookingStatus::CREATED,
                        'remarks' => 'Booking created.',
                        'created_by' => $user->id,
                    ]);

                    return $booking->load(['customerAddress', 'items', 'statusHistories', 'payment']);
                }, 5);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === $maxAttempts || ! str_contains($exception->getMessage(), 'bookings.booking_number')) {
                    throw $exception;
                }
            }
        }

        throw new HttpException(500, 'Unable to generate a unique booking number.');
    }
}
