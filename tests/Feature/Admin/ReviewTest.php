<?php

namespace Tests\Feature\Admin;

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
use App\Models\ProviderAvailability;
use App\Models\ProviderProfile;
use App\Models\ProviderService;
use App\Models\ProviderServiceArea;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_reviews_but_cannot_modify_them(): void
    {
        $review = $this->createReview();
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->getJson('/api/admin/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/admin/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('data.review.id', $review->id);

        $this->patchJson("/api/customer/reviews/{$review->id}", [
            'rating' => 3,
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'You are not authorized to access this resource.');
    }

    protected function createReview(): Review
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
            'postal_code' => '560001',
        ]);

        ProviderAvailability::factory()->create([
            'provider_profile_id' => $providerProfile->id,
            'is_available' => true,
            'available_from' => null,
            'available_until' => null,
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

        $booking->assignmentHistory()->create([
            'provider_profile_id' => $providerProfile->id,
            'status' => AssignmentStatus::COMPLETED,
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        return Review::query()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'provider_profile_id' => $providerProfile->id,
            'rating' => 5,
            'comment' => 'Great work',
        ]);
    }
}
