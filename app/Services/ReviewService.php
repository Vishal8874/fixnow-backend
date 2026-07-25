<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReviewService
{
    public function listForCustomer(User $customer, array $filters): LengthAwarePaginator
    {
        return $customer->reviews()
            ->with(['booking', 'customer', 'providerProfile.user'])
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function showForCustomer(User $customer, Review $review): Review
    {
        $ownedReview = $this->ownedReview($customer, $review);

        return $ownedReview->load(['booking', 'customer', 'providerProfile.user']);
    }

    public function listForAdmin(array $filters): LengthAwarePaginator
    {
        return Review::query()
            ->with(['booking', 'customer', 'providerProfile.user'])
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function showForAdmin(Review $review): Review
    {
        return $review->load(['booking', 'customer', 'providerProfile.user']);
    }

    public function create(User $customer, Booking $booking, array $data): Review
    {
        $eligibleBooking = $this->validateCreateEligibility($customer, $booking);
        $providerProfile = $this->resolveReviewedProvider($eligibleBooking);

        return DB::transaction(function () use ($eligibleBooking, $customer, $providerProfile, $data): Review {
            $review = Review::query()->create([
                'booking_id' => $eligibleBooking->id,
                'customer_id' => $customer->id,
                'provider_profile_id' => $providerProfile->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);

            $this->recalculateProviderRating($providerProfile);

            return $review->load(['booking', 'customer', 'providerProfile.user']);
        });
    }

    public function update(User $customer, Review $review, array $data): Review
    {
        $ownedReview = $this->ownedReview($customer, $review);
        $this->ensureEditableWindow($ownedReview);

        return DB::transaction(function () use ($ownedReview, $data): Review {
            $ownedReview->fill([
                'rating' => $data['rating'] ?? $ownedReview->rating,
                'comment' => array_key_exists('comment', $data) ? $data['comment'] : $ownedReview->comment,
            ])->save();

            $this->recalculateProviderRating($ownedReview->providerProfile);

            return $ownedReview->fresh(['booking', 'customer', 'providerProfile.user']);
        });
    }

    protected function validateCreateEligibility(User $customer, Booking $booking): Booking
    {
        if ($booking->user_id !== $customer->id) {
            throw new HttpException(409, 'You can only review your own completed bookings.');
        }

        $booking->loadMissing(['payment', 'assignmentHistory', 'review']);

        if ($booking->status !== BookingStatus::COMPLETED) {
            throw new HttpException(409, 'Only completed bookings can be reviewed.');
        }

        if (! $booking->payment || $booking->payment->payment_status !== PaymentStatus::PAID) {
            throw new HttpException(409, 'Only paid bookings can be reviewed.');
        }

        $hasValidAssignment = $booking->assignmentHistory()
            ->whereIn('status', [AssignmentStatus::ACCEPTED, AssignmentStatus::COMPLETED])
            ->exists();

        if (! $hasValidAssignment) {
            throw new HttpException(409, 'Only bookings with an accepted or completed provider assignment can be reviewed.');
        }

        if ($booking->review()->exists()) {
            throw new HttpException(409, 'This booking has already been reviewed.');
        }

        return $booking;
    }

    protected function resolveReviewedProvider(Booking $booking): ProviderProfile
    {
        $assignment = $booking->assignmentHistory()
            ->whereIn('status', [AssignmentStatus::COMPLETED, AssignmentStatus::ACCEPTED])
            ->latest('id')
            ->first();

        if (! $assignment) {
            throw new HttpException(409, 'No valid provider assignment found for this booking.');
        }

        return $assignment->providerProfile;
    }

    protected function ownedReview(User $customer, Review $review): Review
    {
        if ($review->customer_id !== $customer->id) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $review;
    }

    protected function ensureEditableWindow(Review $review): void
    {
        if (Carbon::now()->greaterThan($review->created_at->copy()->addDay())) {
            throw new HttpException(409, 'This review can no longer be edited.');
        }
    }

    protected function recalculateProviderRating(ProviderProfile $providerProfile): void
    {
        $aggregate = Review::query()
            ->where('provider_profile_id', $providerProfile->id)
            ->selectRaw('COUNT(*) as total_reviews, AVG(rating) as average_rating')
            ->first();

        $providerProfile->forceFill([
            'total_reviews' => (int) ($aggregate?->total_reviews ?? 0),
            'average_rating' => round((float) ($aggregate?->average_rating ?? 0), 2),
        ])->save();
    }
}
