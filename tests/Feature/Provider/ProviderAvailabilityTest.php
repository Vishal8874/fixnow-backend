<?php

namespace Tests\Feature\Provider;

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
use App\Models\ProviderAvailability;
use App\Models\ProviderProfile;
use App\Models\ProviderService;
use App\Models\ProviderServiceArea;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_create_availability(): void
    {
        $provider = $this->createProviderWithProfile();
        Sanctum::actingAs($provider->user);

        $this->postJson('/api/provider/availability', [
            'is_available' => true,
            'available_from' => '09:00',
            'available_until' => '18:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.availability.is_available', true)
            ->assertJsonPath('data.availability.available_from', '09:00')
            ->assertJsonPath('data.availability.available_until', '18:00');
    }

    public function test_provider_can_update_availability(): void
    {
        $provider = $this->createProviderWithProfile();
        ProviderAvailability::factory()->create([
            'provider_profile_id' => $provider->id,
            'is_available' => true,
        ]);

        Sanctum::actingAs($provider->user);

        $this->patchJson('/api/provider/availability', [
            'is_available' => false,
            'notes' => 'On leave',
        ])
            ->assertOk()
            ->assertJsonPath('data.availability.is_available', false)
            ->assertJsonPath('data.availability.notes', 'On leave');
    }

    public function test_provider_can_only_manage_own_availability_and_validation_is_enforced(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::CUSTOMER]));

        $this->getJson('/api/provider/availability')
            ->assertStatus(403)
            ->assertJsonPath('message', 'You are not authorized to access this resource.');

        $provider = $this->createProviderWithProfile();
        Sanctum::actingAs($provider->user);

        $this->postJson('/api/provider/availability', [
            'available_from' => '18:00',
            'available_until' => '09:00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed.');
    }

    public function test_unavailable_providers_are_skipped_by_assignment_engine(): void
    {
        $booking = $this->createAssignableBooking();
        $provider = $this->createEligibleProviderForBooking($booking);
        ProviderAvailability::factory()->create([
            'provider_profile_id' => $provider->id,
            'is_available' => false,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->postJson("/api/admin/bookings/{$booking->id}/assign")
            ->assertOk()
            ->assertJsonPath('message', 'No provider is currently available for this booking.');
    }

    public function test_assignment_respects_availability_window(): void
    {
        Date::setTestNow(Carbon::parse('2026-07-24 08:00:00', config('app.timezone')));

        $booking = $this->createAssignableBooking();
        $provider = $this->createEligibleProviderForBooking($booking);
        ProviderAvailability::factory()->create([
            'provider_profile_id' => $provider->id,
            'is_available' => true,
            'available_from' => '09:00:00',
            'available_until' => '18:00:00',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->postJson("/api/admin/bookings/{$booking->id}/assign")
            ->assertOk()
            ->assertJsonPath('message', 'No provider is currently available for this booking.');

        Date::setTestNow(Carbon::parse('2026-07-24 10:00:00', config('app.timezone')));

        $this->postJson("/api/admin/bookings/{$booking->id}/assign")
            ->assertCreated();

        Date::setTestNow();
    }

    public function test_assignment_regression_requires_explicit_availability_record(): void
    {
        $booking = $this->createAssignableBooking();
        $provider = $this->createEligibleProviderForBooking($booking);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->postJson("/api/admin/bookings/{$booking->id}/assign")
            ->assertOk()
            ->assertJsonPath('message', 'No provider is currently available for this booking.');

        ProviderAvailability::factory()->create([
            'provider_profile_id' => $provider->id,
            'is_available' => true,
            'available_from' => null,
            'available_until' => null,
        ]);

        $this->postJson("/api/admin/bookings/{$booking->id}/assign")
            ->assertCreated();
    }

    protected function createProviderWithProfile(): ProviderProfile
    {
        $provider = User::factory()->create([
            'role' => UserRole::PROVIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        return ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::APPROVED,
        ])->fresh('user');
    }

    protected function createAssignableBooking(): Booking
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

    protected function createEligibleProviderForBooking(Booking $booking): ProviderProfile
    {
        $profile = $this->createProviderWithProfile();

        foreach ($booking->items as $item) {
            ProviderService::factory()->create([
                'provider_profile_id' => $profile->id,
                'service_id' => $item->service_id,
            ]);
        }

        ProviderServiceArea::factory()->create([
            'provider_profile_id' => $profile->id,
            'postal_code' => $booking->customerAddress->postal_code,
        ]);

        return $profile;
    }
}
