<?php

namespace Tests\Feature\Customer;

use App\Enums\UserRole;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_address_and_first_address_becomes_default(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::CUSTOMER]));

        $response = $this->postJson('/api/customer/addresses', [
            'label' => 'Home',
            'contact_person' => 'Rahul Sharma',
            'contact_phone' => '9876543210',
            'address_line_1' => '123 Main Street',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'postal_code' => '302001',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.label', 'Home')
            ->assertJsonPath('data.is_default', true);
    }

    public function test_customer_can_update_and_list_own_addresses(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($user);

        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'label' => 'Office',
            'is_default' => true,
        ]);

        $this->patchJson("/api/customer/addresses/{$address->id}", [
            'label' => 'Work',
            'city' => 'Delhi',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Work')
            ->assertJsonPath('data.address.city', 'Delhi');

        $this->getJson('/api/customer/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Work');
    }

    public function test_customer_can_delete_address_and_latest_remaining_becomes_default(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($user);

        $older = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'label' => 'Old',
            'is_default' => false,
        ]);

        $default = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'label' => 'Current',
            'is_default' => true,
        ]);

        $this->deleteJson("/api/customer/addresses/{$default->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Customer address deleted successfully.');

        $this->assertSoftDeleted('customer_addresses', ['id' => $default->id]);
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $older->id,
            'is_default' => true,
        ]);
    }

    public function test_customer_can_set_default_and_only_one_default_exists(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        Sanctum::actingAs($user);

        $first = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        $second = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $this->patchJson("/api/customer/addresses/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('data.id', $second->id)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $first->id,
            'is_default' => false,
        ]);

        $this->assertSame(1, CustomerAddress::query()->where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_customer_cannot_access_another_customers_address(): void
    {
        $owner = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $otherUser = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $address = CustomerAddress::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($otherUser);

        $this->getJson("/api/customer/addresses/{$address->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_non_customer_cannot_access_customer_address_routes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->getJson('/api/customer/addresses')
            ->assertStatus(403)
            ->assertJsonPath('message', 'You are not authorized to access this resource.');
    }

    public function test_customer_address_validation_errors_follow_standard_response(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::CUSTOMER]));

        $this->postJson('/api/customer/addresses', [
            'label' => '',
            'contact_phone' => '123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }
}
