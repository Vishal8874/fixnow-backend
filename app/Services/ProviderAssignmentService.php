<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\ProviderAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProviderAssignmentService
{
    public function __construct(private readonly ProviderAvailabilityService $providerAvailabilityService) {}

    public function assignProvider(Booking $booking, array $data = []): ?ProviderAssignment
    {
        if ($booking->status !== BookingStatus::PENDING_ASSIGNMENT) {
            throw new HttpException(409, 'Only bookings pending assignment can be assigned to a provider.');
        }

        $this->ensureNoActiveAssignment($booking);

        $eligibleProvider = $this->findEligibleProvider($booking);

        if (! $eligibleProvider) {
            return null;
        }

        return DB::transaction(function () use ($booking, $eligibleProvider, $data): ProviderAssignment {
            return ProviderAssignment::query()->create([
                'booking_id' => $booking->id,
                'provider_profile_id' => $eligibleProvider->id,
                'status' => AssignmentStatus::ASSIGNED,
                'assigned_at' => now(),
                'notes' => $data['notes'] ?? null,
            ])->load(['providerProfile.user', 'booking']);
        });
    }

    public function assignAutomatically(Booking $booking): ?ProviderAssignment
    {
        return $this->assignProvider($booking, [
            'notes' => 'Automatically assigned by the system.',
        ]);
    }

    public function listProviderAssignments(User $provider, array $filters): LengthAwarePaginator
    {
        $profile = $this->getProviderProfileFromUser($provider);

        return $profile->assignments()
            ->with(['providerProfile.user', 'booking'])
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function showProviderAssignment(User $provider, ProviderAssignment $assignment): ProviderAssignment
    {
        $ownedAssignment = $this->ownedAssignment($provider, $assignment);

        return $ownedAssignment->load(['providerProfile.user', 'booking']);
    }

    public function accept(User $provider, ProviderAssignment $assignment, array $data): ProviderAssignment
    {
        $ownedAssignment = $this->ownedAssignment($provider, $assignment);

        if ($ownedAssignment->status !== AssignmentStatus::ASSIGNED) {
            throw new HttpException(409, 'Only assigned provider assignments can be accepted.');
        }

        return DB::transaction(function () use ($ownedAssignment, $data): ProviderAssignment {
            $ownedAssignment->forceFill([
                'status' => AssignmentStatus::ACCEPTED,
                'accepted_at' => now(),
                'notes' => $data['notes'] ?? $ownedAssignment->notes,
            ])->save();

            $this->updateBookingStatus($ownedAssignment->booking, BookingStatus::PROVIDER_ASSIGNED, 'Provider accepted assignment.');

            return $ownedAssignment->fresh(['providerProfile.user', 'booking']);
        });
    }

    public function reject(User $provider, ProviderAssignment $assignment, array $data): ProviderAssignment
    {
        $ownedAssignment = $this->ownedAssignment($provider, $assignment);

        if ($ownedAssignment->status !== AssignmentStatus::ASSIGNED) {
            throw new HttpException(409, 'Only assigned provider assignments can be rejected.');
        }

        return DB::transaction(function () use ($ownedAssignment, $data): ProviderAssignment {
            $ownedAssignment->forceFill([
                'status' => AssignmentStatus::REJECTED,
                'rejected_at' => now(),
                'rejection_reason' => $data['rejection_reason'] ?? $ownedAssignment->rejection_reason,
                'notes' => $data['notes'] ?? $ownedAssignment->notes,
            ])->save();

            $this->updateBookingStatus($ownedAssignment->booking, BookingStatus::PENDING_ASSIGNMENT, 'Provider rejected assignment.');
            $this->assignAutomatically($ownedAssignment->booking->fresh());

            return $ownedAssignment->fresh(['providerProfile.user', 'booking']);
        });
    }

    public function markOnTheWay(User $provider, ProviderAssignment $assignment, array $data): ProviderAssignment
    {
        $ownedAssignment = $this->ownedAssignment($provider, $assignment);

        if ($ownedAssignment->booking->status !== BookingStatus::PROVIDER_ASSIGNED) {
            throw new HttpException(409, 'Booking must be in provider_assigned status to mark on the way.');
        }

        return DB::transaction(function () use ($ownedAssignment, $data): ProviderAssignment {
            $this->updateBookingStatus($ownedAssignment->booking, BookingStatus::ON_THE_WAY, 'Provider is on the way.');

            $ownedAssignment->forceFill([
                'notes' => $data['notes'] ?? $ownedAssignment->notes,
            ])->save();

            return $ownedAssignment->fresh(['providerProfile.user', 'booking']);
        });
    }

    public function markArrived(User $provider, ProviderAssignment $assignment, array $data): ProviderAssignment
    {
        $ownedAssignment = $this->ownedAssignment($provider, $assignment);

        if ($ownedAssignment->booking->status !== BookingStatus::ON_THE_WAY) {
            throw new HttpException(409, 'Booking must be in on_the_way status to mark arrived.');
        }

        return DB::transaction(function () use ($ownedAssignment, $data): ProviderAssignment {
            $this->updateBookingStatus($ownedAssignment->booking, BookingStatus::ARRIVED, 'Provider has arrived.');

            $ownedAssignment->forceFill([
                'notes' => $data['notes'] ?? $ownedAssignment->notes,
            ])->save();

            return $ownedAssignment->fresh(['providerProfile.user', 'booking']);
        });
    }

    public function markInProgress(User $provider, ProviderAssignment $assignment, array $data): ProviderAssignment
    {
        $ownedAssignment = $this->ownedAssignment($provider, $assignment);

        if ($ownedAssignment->booking->status !== BookingStatus::ARRIVED) {
            throw new HttpException(409, 'Booking must be in arrived status to mark in progress.');
        }

        return DB::transaction(function () use ($ownedAssignment, $data): ProviderAssignment {
            $this->updateBookingStatus($ownedAssignment->booking, BookingStatus::IN_PROGRESS, 'Service is in progress.');

            $ownedAssignment->forceFill([
                'notes' => $data['notes'] ?? $ownedAssignment->notes,
            ])->save();

            return $ownedAssignment->fresh(['providerProfile.user', 'booking']);
        });
    }

    public function markCompleted(User $provider, ProviderAssignment $assignment, array $data): ProviderAssignment
    {
        $ownedAssignment = $this->ownedAssignment($provider, $assignment);

        if ($ownedAssignment->booking->status !== BookingStatus::IN_PROGRESS) {
            throw new HttpException(409, 'Booking must be in in_progress status to mark completed.');
        }

        return DB::transaction(function () use ($ownedAssignment, $data): ProviderAssignment {
            $ownedAssignment->forceFill([
                'status' => AssignmentStatus::COMPLETED,
                'completed_at' => now(),
                'notes' => $data['notes'] ?? $ownedAssignment->notes,
            ])->save();

            $this->updateBookingStatus($ownedAssignment->booking, BookingStatus::COMPLETED, 'Service completed.');

            $this->closeBookingIfEligible($ownedAssignment->booking->fresh(['payment']));

            return $ownedAssignment->fresh(['providerProfile.user', 'booking']);
        });
    }

    public function confirmCodPayment(User $provider, ProviderAssignment $assignment, array $data): ProviderAssignment
    {
        $ownedAssignment = $this->ownedAssignment($provider, $assignment);
        $booking = $ownedAssignment->booking->load('payment');

        if ($booking->status !== BookingStatus::COMPLETED) {
            throw new HttpException(409, 'Cash can only be confirmed after service completion.');
        }

        if (! $booking->payment || $booking->payment->payment_method !== PaymentMethod::CASH_ON_DELIVERY) {
            throw new HttpException(409, 'This booking does not use cash on delivery payment.');
        }

        if ($booking->payment->payment_status === PaymentStatus::PAID) {
            throw new HttpException(409, 'Payment is already marked as paid.');
        }

        return DB::transaction(function () use ($ownedAssignment, $booking, $data): ProviderAssignment {
            $booking->payment->forceFill([
                'payment_status' => PaymentStatus::PAID,
                'paid_at' => now(),
                'notes' => $data['notes'] ?? $booking->payment->notes,
            ])->save();

            $this->closeBookingIfEligible($booking->fresh(['payment']));

            return $ownedAssignment->fresh(['providerProfile.user', 'booking']);
        });
    }

    public function closeBookingIfEligible(Booking $booking): void
    {
        if ($booking->status !== BookingStatus::COMPLETED) {
            return;
        }

        if (! $booking->payment || $booking->payment->payment_status !== PaymentStatus::PAID) {
            return;
        }

        $this->updateBookingStatus($booking, BookingStatus::CLOSED, 'Booking closed. Service completed and payment confirmed.');
    }

    protected function findEligibleProvider(Booking $booking): ?ProviderProfile
    {
        $booking->loadMissing(['customerAddress', 'items']);

        $postalCode = $booking->customerAddress?->postal_code;
        $serviceIds = $booking->items->pluck('service_id')->unique()->values();

        if (! $postalCode || $serviceIds->isEmpty()) {
            return null;
        }

        $currentTime = now()->format('H:i:s');

        return ProviderProfile::query()
            ->with(['user', 'availability'])
            ->where('verification_status', ProviderVerificationStatus::APPROVED)
            ->whereHas('user', function (Builder $query): void {
                $query
                    ->where('role', UserRole::PROVIDER)
                    ->where('status', UserStatus::ACTIVE);
            })
            ->whereHas('availability', function (Builder $query) use ($currentTime): void {
                $query
                    ->where('is_available', true)
                    ->where(function (Builder $availabilityQuery) use ($currentTime): void {
                        $availabilityQuery
                            ->whereNull('available_from')
                            ->orWhereNull('available_until')
                            ->orWhere(function (Builder $windowQuery) use ($currentTime): void {
                                $windowQuery
                                    ->whereTime('available_from', '<=', $currentTime)
                                    ->whereTime('available_until', '>=', $currentTime);
                            });
                    });
            })
            ->whereHas('serviceAreas', function (Builder $query) use ($postalCode): void {
                $query->where('postal_code', $postalCode);
            })
            ->where(function (Builder $query) use ($serviceIds): void {
                foreach ($serviceIds as $serviceId) {
                    $query->whereHas('providerServices', function (Builder $serviceQuery) use ($serviceId): void {
                        $serviceQuery->where('service_id', $serviceId);
                    });
                }
            })
            ->whereDoesntHave('assignments', function (Builder $query) use ($booking): void {
                $query
                    ->whereIn('status', [AssignmentStatus::ASSIGNED, AssignmentStatus::ACCEPTED])
                    ->whereHas('booking', function (Builder $bookingQuery) use ($booking): void {
                        $bookingQuery
                            ->whereDate('booking_date', $booking->booking_date)
                            ->whereTime('booking_time', $booking->booking_time);
                    });
            })
            ->whereDoesntHave('assignments', function (Builder $query) use ($booking): void {
                $query->where('booking_id', $booking->id);
            })
            ->orderBy('id')
            ->first();
    }

    protected function ensureNoActiveAssignment(Booking $booking): void
    {
        $hasActiveAssignment = $booking->assignmentHistory()
            ->whereIn('status', [AssignmentStatus::ASSIGNED, AssignmentStatus::ACCEPTED])
            ->exists();

        if ($hasActiveAssignment) {
            throw new HttpException(409, 'This booking already has an active provider assignment.');
        }
    }

    protected function ownedAssignment(User $provider, ProviderAssignment $assignment): ProviderAssignment
    {
        $profile = $this->getProviderProfileFromUser($provider);

        if ($assignment->provider_profile_id !== $profile->id) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $assignment;
    }

    protected function getProviderProfileFromUser(User $provider): ProviderProfile
    {
        if ($provider->role !== UserRole::PROVIDER) {
            throw new HttpException(404, 'Resource not found.');
        }

        $profile = $provider->providerProfile;

        if (! $profile) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $profile;
    }

    protected function updateBookingStatus(Booking $booking, BookingStatus $status, string $remarks): void
    {
        $booking->forceFill([
            'status' => $status,
        ])->save();

        $booking->statusHistories()->create([
            'status' => $status,
            'remarks' => $remarks,
        ]);
    }
}
