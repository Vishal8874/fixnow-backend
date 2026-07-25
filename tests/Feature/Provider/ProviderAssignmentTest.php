<?php

namespace Tests\Feature\Provider;

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
use App\Models\ProviderAssignment;
use App\Models\ProviderAvailability;
use App\Models\ProviderProfile;
use App\Models\ProviderService;
use App\Models\ProviderServiceArea;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_assignment_for_eligible_provider(): void
    {
        $booking = $this->createAssignableBooking();
        $providerProfile = $this->createEligibleProviderForBooking($booking);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->postJson("/api/admin/bookings/{$booking->id}/assign")
            ->assertCreated()
            ->assertJsonPath('data.assignment.status', AssignmentStatus::ASSIGNED->value)
            ->assertJsonPath('data.assignment.provider.id', $providerProfile->user->id);
    }

    public function test_provider_can_accept_assignment_and_booking_status_updates(): void
    {
        $booking = $this->createAssignableBooking();
        $providerProfile = $this->createEligibleProviderForBooking($booking);
        $assignment = ProviderAssignment::factory()->create([
            'booking_id' => $booking->id,
            'provider_profile_id' => $providerProfile->id,
            'status' => AssignmentStatus::ASSIGNED,
        ]);

        Sanctum::actingAs($providerProfile->user);

        $this->patchJson("/api/provider/assignments/{$assignment->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.assignment.status', AssignmentStatus::ACCEPTED->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::PROVIDER_ASSIGNED->value,
        ]);
    }

    public function test_provider_rejection_triggers_automatic_reassignment(): void
    {
        $booking = $this->createAssignableBooking();
        $providerProfile = $this->createEligibleProviderForBooking($booking);
        $nextProviderProfile = $this->createEligibleProviderForBooking($booking);
        $assignment = ProviderAssignment::factory()->create([
            'booking_id' => $booking->id,
            'provider_profile_id' => $providerProfile->id,
            'status' => AssignmentStatus::ASSIGNED,
        ]);

        Sanctum::actingAs($providerProfile->user);

        $this->patchJson("/api/provider/assignments/{$assignment->id}/reject", [
            'rejection_reason' => 'Unavailable',
        ])
            ->assertOk()
            ->assertJsonPath('data.assignment.status', AssignmentStatus::REJECTED->value)
            ->assertJsonPath('data.assignment.rejection_reason', 'Unavailable');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::PENDING_ASSIGNMENT->value,
        ]);

        $this->assertDatabaseHas('provider_assignments', [
            'booking_id' => $booking->id,
            'provider_profile_id' => $nextProviderProfile->id,
            'status' => AssignmentStatus::ASSIGNED->value,
        ]);
    }

    public function test_duplicate_active_assignments_are_prevented_and_reassignment_is_possible_after_rejection(): void
    {
        $booking = $this->createAssignableBooking();
        $providerProfile = $this->createEligibleProviderForBooking($booking);
        $replacementProvider = $this->createEligibleProviderForBooking($booking);

        ProviderAssignment::factory()->create([
            'booking_id' => $booking->id,
            'provider_profile_id' => $providerProfile->id,
            'status' => AssignmentStatus::ASSIGNED,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->postJson("/api/admin/bookings/{$booking->id}/assign")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This booking already has an active provider assignment.');

        ProviderAssignment::query()->update([
            'status' => AssignmentStatus::REJECTED,
            'rejected_at' => now(),
        ]);

        $this->postJson("/api/admin/bookings/{$booking->id}/assign")
            ->assertCreated()
            ->assertJsonPath('data.assignment.status', AssignmentStatus::ASSIGNED->value)
            ->assertJsonPath('data.assignment.provider.id', $replacementProvider->user->id);
    }

    public function test_assignment_stops_gracefully_when_no_replacement_provider_is_available(): void
    {
        $booking = $this->createAssignableBooking();
        $providerProfile = $this->createEligibleProviderForBooking($booking);
        $assignment = ProviderAssignment::factory()->create([
            'booking_id' => $booking->id,
            'provider_profile_id' => $providerProfile->id,
            'status' => AssignmentStatus::ASSIGNED,
        ]);

        Sanctum::actingAs($providerProfile->user);

        $this->patchJson("/api/provider/assignments/{$assignment->id}/reject", [
            'rejection_reason' => 'Unavailable',
        ])->assertOk();

        $this->assertSame(1, ProviderAssignment::query()->where('booking_id', $booking->id)->count());
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::PENDING_ASSIGNMENT->value,
        ]);
    }

    public function test_provider_can_only_access_own_assignments(): void
    {
        $booking = $this->createAssignableBooking();
        $providerProfile = $this->createEligibleProviderForBooking($booking);
        $otherProvider = $this->createEligibleProviderForBooking($booking, '400001');
        $assignment = ProviderAssignment::factory()->create([
            'booking_id' => $booking->id,
            'provider_profile_id' => $providerProfile->id,
            'status' => AssignmentStatus::ASSIGNED,
        ]);

        Sanctum::actingAs($otherProvider->user);

        $this->getJson("/api/provider/assignments/{$assignment->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_provider_can_complete_accepted_assignment_and_booking_becomes_completed(): void
    {
        $booking = $this->createAssignableBooking();
        $providerProfile = $this->createEligibleProviderForBooking($booking);
        $assignment = ProviderAssignment::factory()->create([
            'booking_id' => $booking->id,
            'provider_profile_id' => $providerProfile->id,
            'status' => AssignmentStatus::ACCEPTED,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($providerProfile->user);

        $this->patchJson("/api/provider/assignments/{$assignment->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.assignment.status', AssignmentStatus::COMPLETED->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::COMPLETED->value,
        ]);
    }

    public function test_assignment_returns_no_provider_available_when_eligibility_rules_fail(): void
    {
        $booking = $this->createAssignableBooking();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->postJson("/api/admin/bookings/{$booking->id}/assign")
            ->assertOk()
            ->assertJsonPath('message', 'No provider is currently available for this booking.');
    }

    public function test_assignment_validation_and_provider_listing_work(): void
    {
        $booking = $this->createAssignableBooking();
        $providerProfile = $this->createEligibleProviderForBooking($booking);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $createResponse = $this->postJson("/api/admin/bookings/{$booking->id}/assign", [
            'notes' => 'Auto assigned',
        ])->assertCreated();

        $assignmentId = $createResponse->json('data.assignment.id');

        Sanctum::actingAs($providerProfile->user);

        $this->getJson('/api/provider/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/provider/assignments/{$assignmentId}")
            ->assertOk()
            ->assertJsonPath('data.assignment.provider.id', $providerProfile->user->id);
    }

    protected function createAssignableBooking(): Booking
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $address = CustomerAddress::factory()->create([
            'user_id' => $customer->id,
            'postal_code' => '560001',
            'is_default' => true,
        ]);
        $category = Category::factory()->create(['status' => Status::ACTIVE]);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => Status::ACTIVE,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $address->id,
            'status' => BookingStatus::PENDING_ASSIGNMENT,
            'booking_date' => '2026-07-25',
            'booking_time' => '10:00:00',
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'unit_price' => $service->base_price,
            'quantity' => 1,
            'subtotal' => $service->base_price,
        ]);

        $booking->payment()->create([
            'payment_method' => PaymentMethod::CASH_ON_DELIVERY,
            'payment_status' => PaymentStatus::PENDING,
            'amount' => $booking->total,
        ]);

        return $booking;
    }

    protected function createEligibleProviderForBooking(Booking $booking, string $postalCode = '560001'): ProviderProfile
    {
        $provider = User::factory()->create([
            'role' => UserRole::PROVIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $profile = ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::APPROVED,
        ]);

        foreach ($booking->items as $item) {
            ProviderService::factory()->create([
                'provider_profile_id' => $profile->id,
                'service_id' => $item->service_id,
            ]);
        }

        ProviderServiceArea::factory()->create([
            'provider_profile_id' => $profile->id,
            'postal_code' => $postalCode,
        ]);

        ProviderAvailability::factory()->create([
            'provider_profile_id' => $profile->id,
            'is_available' => true,
            'available_from' => null,
            'available_until' => null,
        ]);

        return $profile->fresh('user');
    }
}
