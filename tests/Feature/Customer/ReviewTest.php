<?php

namespace Tests\Feature\Customer;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProviderVerificationStatus;
use App\Enums\Status;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Payment;
use App\Models\ProviderAssignment;
use App\Models\ProviderAvailability;
use App\Models\ProviderProfile;
use App\Models\ProviderService;
use App\Models\ProviderServiceArea;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_review_for_completed_paid_booking(): void
    {
        [$customer, $booking, $providerProfile] = $this->createReviewableBooking();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 5,
            'comment' => 'Excellent service.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.review.rating', 5)
            ->assertJsonPath('data.review.provider.id', $providerProfile->user->id)
            ->assertJsonPath('data.review.booking.booking_number', $booking->booking_number);
    }

    public function test_duplicate_review_prevention_and_booking_ownership_are_enforced(): void
    {
        [$customer, $booking] = $this->createReviewableBooking();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 4,
        ])->assertCreated();

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 5,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This booking has already been reviewed.');

        $otherCustomer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($otherCustomer);

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 5,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'You can only review your own completed bookings.');
    }

    public function test_completed_paid_and_assignment_rules_are_enforced(): void
    {
        [$customer, $booking] = $this->createReviewableBooking();
        Sanctum::actingAs($customer);

        $booking->forceFill(['status' => BookingStatus::PROVIDER_ASSIGNED])->save();

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 5,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only completed bookings can be reviewed.');

        $booking->forceFill(['status' => BookingStatus::COMPLETED])->save();
        $booking->payment->forceFill(['payment_status' => PaymentStatus::PENDING])->save();

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 5,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only paid bookings can be reviewed.');

        $booking->payment->forceFill(['payment_status' => PaymentStatus::PAID])->save();
        ProviderAssignment::query()->where('booking_id', $booking->id)->delete();

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 5,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only bookings with an accepted or completed provider assignment can be reviewed.');
    }

    public function test_customer_can_edit_review_within_24_hours_only(): void
    {
        [$customer, $booking] = $this->createReviewableBooking();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 4,
            'comment' => 'Good service.',
        ])->assertCreated();

        $review = Review::query()->firstOrFail();

        $this->patchJson("/api/customer/reviews/{$review->id}", [
            'rating' => 5,
            'comment' => 'Excellent service.',
        ])
            ->assertOk()
            ->assertJsonPath('data.review.rating', 5);

        Date::setTestNow(Carbon::parse($review->created_at)->addHours(25));

        $this->patchJson("/api/customer/reviews/{$review->id}", [
            'rating' => 3,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This review can no longer be edited.');

        Date::setTestNow();
    }

    public function test_provider_rating_recalculation_and_average_accuracy_work(): void
    {
        [$customerOne, $bookingOne, $providerProfile] = $this->createReviewableBooking();
        [$customerTwo, $bookingTwo] = $this->createReviewableBooking($providerProfile);

        Sanctum::actingAs($customerOne);
        $this->postJson("/api/customer/bookings/{$bookingOne->id}/review", [
            'rating' => 5,
        ])->assertCreated();

        Sanctum::actingAs($customerTwo);
        $this->postJson("/api/customer/bookings/{$bookingTwo->id}/review", [
            'rating' => 3,
        ])->assertCreated();

        $providerProfile->refresh();
        $this->assertSame(2, $providerProfile->total_reviews);
        $this->assertSame('4.00', number_format((float) $providerProfile->average_rating, 2, '.', ''));
    }

    public function test_customer_and_admin_review_listing_and_authorization_work(): void
    {
        [$customer, $booking] = $this->createReviewableBooking();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 5,
        ])->assertCreated();

        $review = Review::query()->firstOrFail();

        $this->getJson('/api/customer/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/customer/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('data.review.id', $review->id);

        $otherCustomer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($otherCustomer);

        $this->getJson("/api/customer/reviews/{$review->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->getJson('/api/admin/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/admin/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('data.review.id', $review->id);
    }

    public function test_review_validation_and_cod_paid_after_completion_regression_work(): void
    {
        [$customer, $booking] = $this->createReviewableBooking();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 6,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed.');

        $codBooking = Booking::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $booking->customer_address_id,
            'status' => BookingStatus::PENDING_ASSIGNMENT,
        ]);

        $codPayment = Payment::factory()->create([
            'booking_id' => $codBooking->id,
            'payment_method' => PaymentMethod::CASH_ON_DELIVERY,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->patchJson("/api/admin/payments/{$codPayment->id}/cod-paid")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cash on delivery payments can only be marked as paid after the booking is completed.');
    }

    protected function createReviewableBooking(?ProviderProfile $existingProviderProfile = null): array
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $address = CustomerAddress::factory()->create([
            'user_id' => $customer->id,
            'postal_code' => '560001',
        ]);
        $category = Category::factory()->create(['status' => Status::ACTIVE]);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => Status::ACTIVE,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $address->id,
            'status' => BookingStatus::COMPLETED,
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'unit_price' => $service->base_price,
            'quantity' => 1,
            'subtotal' => $service->base_price,
        ]);

        $booking->payment()->create([
            'payment_method' => PaymentMethod::ONLINE,
            'payment_status' => PaymentStatus::PAID,
            'amount' => $booking->total,
            'paid_at' => now(),
        ]);

        $providerProfile = $existingProviderProfile ?? $this->createProviderProfileForService($service, $address->postal_code);

        $booking->assignmentHistory()->create([
            'provider_profile_id' => $providerProfile->id,
            'status' => AssignmentStatus::COMPLETED,
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHours(1),
            'completed_at' => now(),
        ]);

        return [$customer, $booking, $providerProfile];
    }

    protected function createProviderProfileForService(Service $service, string $postalCode): ProviderProfile
    {
        $provider = User::factory()->create([
            'role' => UserRole::PROVIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $providerProfile = ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::APPROVED,
        ]);

        ProviderService::factory()->create([
            'provider_profile_id' => $providerProfile->id,
            'service_id' => $service->id,
        ]);

        ProviderServiceArea::factory()->create([
            'provider_profile_id' => $providerProfile->id,
            'postal_code' => $postalCode,
        ]);

        ProviderAvailability::factory()->create([
            'provider_profile_id' => $providerProfile->id,
            'is_available' => true,
            'available_from' => null,
            'available_until' => null,
        ]);

        return $providerProfile;
    }
}
