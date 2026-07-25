<?php

namespace Tests\Feature\Customer;

use App\Enums\BookingStatus;
use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingStatusHistory;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_booking(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($user);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $category = Category::factory()->create(['status' => Status::ACTIVE]);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'AC Repair',
            'base_price' => 799.99,
            'status' => Status::ACTIVE,
        ]);

        $response = $this->postJson('/api/customer/bookings', [
            'customer_address_id' => $address->id,
            'booking_date' => '2026-07-25',
            'booking_time' => '10:30',
            'service_charge' => 50,
            'tax' => 25,
            'discount' => 10,
            'services' => [
                [
                    'service_id' => $service->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.booking.booking_number', 'BK202600001')
            ->assertJsonPath('data.services.0.name', 'AC Repair')
            ->assertJsonPath('data.summary.subtotal', '1599.98')
            ->assertJsonPath('data.summary.total', '1664.98')
            ->assertJsonPath('data.status.current', BookingStatus::CREATED->value);
    }

    public function test_customer_can_list_and_view_own_bookings(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($user);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'customer_address_id' => $address->id,
            'booking_number' => 'BK202600001',
        ]);
        BookingItem::factory()->create(['booking_id' => $booking->id]);
        BookingStatusHistory::factory()->create(['booking_id' => $booking->id]);

        $this->getJson('/api/customer/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_number', 'BK202600001');

        $this->getJson("/api/customer/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.booking_number', 'BK202600001');
    }

    public function test_customer_can_cancel_booking(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($user);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'customer_address_id' => $address->id,
            'status' => BookingStatus::CREATED,
        ]);

        $this->patchJson("/api/customer/bookings/{$booking->id}/cancel", [
            'remarks' => 'Change of plans',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
            'remarks' => 'Change of plans',
        ]);
    }

    public function test_booking_number_generation_increments(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($user);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $category = Category::factory()->create(['status' => Status::ACTIVE]);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => Status::ACTIVE,
        ]);

        $payload = [
            'customer_address_id' => $address->id,
            'booking_date' => '2026-07-25',
            'booking_time' => '10:30',
            'services' => [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
        ];

        $this->postJson('/api/customer/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.booking.booking_number', 'BK202600001');

        $this->postJson('/api/customer/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.booking.booking_number', 'BK202600002');
    }

    public function test_booking_number_generation_recovers_from_unique_constraint_collision(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $address = CustomerAddress::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $category = Category::factory()->create(['status' => Status::ACTIVE]);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => Status::ACTIVE,
        ]);

        Booking::factory()->create([
            'booking_number' => 'BK202600001',
            'user_id' => $user->id,
            'customer_address_id' => $address->id,
        ]);

        $bookingService = new class extends BookingService
        {
            private int $attempts = 0;

            protected function generateBookingNumber(): string
            {
                $this->attempts++;

                return $this->attempts === 1 ? 'BK202600001' : 'BK202600002';
            }
        };

        $booking = $bookingService->create($user, [
            'customer_address_id' => $address->id,
            'booking_date' => '2026-07-25',
            'booking_time' => '10:30',
            'services' => [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
        ]);

        $this->assertSame('BK202600002', $booking->booking_number);
        $this->assertDatabaseHas('bookings', [
            'booking_number' => 'BK202600002',
        ]);
    }

    public function test_customer_cannot_access_another_customers_booking(): void
    {
        $owner = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $other = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $address = CustomerAddress::factory()->create(['user_id' => $owner->id, 'is_default' => true]);
        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'customer_address_id' => $address->id,
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/customer/bookings/{$booking->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_customer_must_own_address_and_booking_must_capture_status_history(): void
    {
        $owner = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $other = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($owner);
        $address = CustomerAddress::factory()->create(['user_id' => $other->id]);
        $category = Category::factory()->create(['status' => Status::ACTIVE]);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => Status::ACTIVE,
        ]);

        $this->postJson('/api/customer/bookings', [
            'customer_address_id' => $address->id,
            'booking_date' => '2026-07-25',
            'booking_time' => '10:30',
            'services' => [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
        ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');

        $ownedAddress = CustomerAddress::factory()->create(['user_id' => $owner->id]);

        $response = $this->postJson('/api/customer/bookings', [
            'customer_address_id' => $ownedAddress->id,
            'booking_date' => '2026-07-25',
            'booking_time' => '10:30',
            'services' => [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $bookingId = Booking::query()->value('id');

        $response->assertJsonPath('data.status.history.0.status', BookingStatus::CREATED->value);

        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $bookingId,
            'status' => BookingStatus::CREATED->value,
        ]);
    }

    public function test_booking_validation_errors_follow_standard_response(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::CUSTOMER]));

        $this->postJson('/api/customer/bookings', [
            'services' => [],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }
}
