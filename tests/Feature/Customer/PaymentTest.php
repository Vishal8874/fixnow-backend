<?php

namespace Tests\Feature\Customer;

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
use App\Models\ProviderAvailability;
use App\Models\ProviderProfile;
use App\Models\ProviderService;
use App\Models\ProviderServiceArea;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_cod_payment_and_booking_moves_to_pending_assignment(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($user);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'customer_address_id' => $address->id,
            'status' => BookingStatus::CREATED,
            'total' => 850.00,
        ]);
        $service = $this->attachBookableServiceToBooking($booking);
        $providerProfile = $this->createEligibleProvider($service, $address->postal_code);

        $this->postJson("/api/customer/bookings/{$booking->id}/payment", [
            'payment_method' => PaymentMethod::CASH_ON_DELIVERY->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment.method', PaymentMethod::CASH_ON_DELIVERY->value)
            ->assertJsonPath('data.payment.status', PaymentStatus::PENDING->value)
            ->assertJsonPath('data.payment.amount', 850);

        $this->assertDatabaseHas('provider_assignments', [
            'booking_id' => $booking->id,
            'provider_profile_id' => $providerProfile->id,
            'status' => 'assigned',
        ]);
    }

    public function test_online_payment_moves_booking_to_pending_payment_then_gateway_callback_triggers_assignment(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($customer);
        $address = CustomerAddress::factory()->create(['user_id' => $customer->id]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $address->id,
            'status' => BookingStatus::CREATED,
            'total' => 999.00,
        ]);
        $service = $this->attachBookableServiceToBooking($booking);
        $providerProfile = $this->createEligibleProvider($service, $address->postal_code);

        $createResponse = $this->postJson("/api/customer/bookings/{$booking->id}/payment", [
            'payment_method' => PaymentMethod::ONLINE->value,
            'gateway' => 'simulated',
        ])->assertCreated();

        $paymentId = $createResponse->json('data.payment.id');

        // Booking should be in PENDING_PAYMENT, not PENDING_ASSIGNMENT
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::PENDING_PAYMENT->value,
        ]);

        // Simulate gateway callback
        $this->postJson('/api/gateway/payment/callback', [
            'payment_id' => $paymentId,
            'gateway_transaction_id' => 'TXN-123',
            'status' => 'success',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.status', PaymentStatus::PAID->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::PENDING_ASSIGNMENT->value,
        ]);

        $this->assertDatabaseHas('provider_assignments', [
            'booking_id' => $booking->id,
            'provider_profile_id' => $providerProfile->id,
        ]);
    }

    public function test_gateway_callback_with_failure_marks_payment_failed(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($customer);
        $address = CustomerAddress::factory()->create(['user_id' => $customer->id]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $address->id,
            'status' => BookingStatus::CREATED,
            'total' => 500.00,
        ]);

        $this->postJson("/api/customer/bookings/{$booking->id}/payment", [
            'payment_method' => PaymentMethod::ONLINE->value,
            'gateway' => 'simulated',
        ])->assertCreated();

        $paymentId = Payment::query()->where('booking_id', $booking->id)->value('id');

        $this->postJson('/api/gateway/payment/callback', [
            'payment_id' => $paymentId,
            'status' => 'failed',
            'notes' => 'Insufficient funds',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.status', PaymentStatus::FAILED->value);
    }

    public function test_admin_can_mark_online_payment_failed_as_override(): void
    {
        $booking = Booking::factory()->create();
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'payment_method' => PaymentMethod::ONLINE,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->patchJson("/api/admin/payments/{$payment->id}/failed", [
            'notes' => 'Gateway declined',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.status', PaymentStatus::FAILED->value);
    }

    public function test_payment_cannot_be_created_twice_and_customer_can_only_view_own_payment(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $other = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $address = CustomerAddress::factory()->create(['user_id' => $customer->id]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $address->id,
            'total' => 500,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/payment", [
            'payment_method' => PaymentMethod::ONLINE->value,
        ])->assertCreated();

        $this->postJson("/api/customer/bookings/{$booking->id}/payment", [
            'payment_method' => PaymentMethod::ONLINE->value,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Payment already exists for this booking.');

        Sanctum::actingAs($other);

        $this->getJson("/api/customer/bookings/{$booking->id}/payment")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_payment_validation_and_booking_status_history_updates_work(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($customer);
        $address = CustomerAddress::factory()->create(['user_id' => $customer->id]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $address->id,
            'status' => BookingStatus::CREATED,
            'total' => 400,
        ]);

        $this->postJson("/api/customer/bookings/{$booking->id}/payment", [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');

        $this->postJson("/api/customer/bookings/{$booking->id}/payment", [
            'payment_method' => PaymentMethod::CASH_ON_DELIVERY->value,
        ])->assertCreated();

        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'status' => BookingStatus::PENDING_ASSIGNMENT->value,
        ]);
    }

    protected function attachBookableServiceToBooking(Booking $booking): Service
    {
        $category = Category::factory()->create(['status' => Status::ACTIVE]);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => Status::ACTIVE,
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'unit_price' => $service->base_price,
            'quantity' => 1,
            'subtotal' => $service->base_price,
        ]);

        return $service;
    }

    protected function createEligibleProvider(Service $service, string $postalCode): ProviderProfile
    {
        $provider = User::factory()->create([
            'role' => UserRole::PROVIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $profile = ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::APPROVED,
        ]);

        ProviderService::factory()->create([
            'provider_profile_id' => $profile->id,
            'service_id' => $service->id,
        ]);

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

        return $profile;
    }
}
