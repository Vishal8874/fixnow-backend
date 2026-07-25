<?php

namespace Tests\Feature\Admin;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_services(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $category = Category::factory()->create();

        $createResponse = $this->postJson('/api/admin/services', [
            'category_id' => $category->id,
            'name' => 'AC Repair',
            'slug' => 'ac-repair',
            'image' => 'ac.jpg',
            'description' => 'Cooling repair',
            'estimated_duration' => 90,
            'base_price' => 799.99,
            'status' => Status::ACTIVE->value,
        ]);

        $serviceId = $createResponse->json('data.id');

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.base_price', '799.99');

        $this->getJson('/api/admin/services')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1);

        $this->getJson("/api/admin/services/{$serviceId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'AC Repair');

        $this->patchJson("/api/admin/services/{$serviceId}", [
            'name' => 'AC Deep Repair',
            'estimated_duration' => 120,
            'base_price' => 999.50,
            'status' => Status::INACTIVE->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'AC Deep Repair')
            ->assertJsonPath('data.status', Status::INACTIVE->value);

        $this->deleteJson("/api/admin/services/{$serviceId}")
            ->assertOk()
            ->assertJsonPath('message', 'Service deleted successfully.');
    }

    public function test_public_service_search_and_category_filter_work(): void
    {
        $plumbing = Category::factory()->create(['name' => 'Plumbing', 'slug' => 'plumbing']);
        $electrical = Category::factory()->create(['name' => 'Electrical', 'slug' => 'electrical']);

        Service::factory()->create([
            'category_id' => $plumbing->id,
            'name' => 'Pipe Leak Repair',
            'slug' => 'pipe-leak-repair',
            'status' => Status::ACTIVE,
        ]);

        Service::factory()->create([
            'category_id' => $electrical->id,
            'name' => 'Wiring Check',
            'slug' => 'wiring-check',
            'status' => Status::ACTIVE,
        ]);

        Service::factory()->create([
            'category_id' => $electrical->id,
            'name' => 'Hidden Inactive Service',
            'slug' => 'hidden-inactive-service',
            'status' => Status::INACTIVE,
        ]);

        $this->getJson("/api/services?search=plumbing&category_id={$plumbing->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pipe Leak Repair');
    }

    public function test_public_category_services_returns_only_active_services(): void
    {
        $category = Category::factory()->create(['status' => Status::ACTIVE]);

        Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'Active Service',
            'status' => Status::ACTIVE,
        ]);

        Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'Inactive Service',
            'status' => Status::INACTIVE,
        ]);

        $this->getJson("/api/categories/{$category->id}/services")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Service');
    }

    public function test_service_validation_and_pagination_work(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $category = Category::factory()->create();

        $this->postJson('/api/admin/services', [
            'category_id' => $category->id,
            'name' => '',
            'estimated_duration' => 0,
            'base_price' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');

        Service::factory()->count(12)->create(['category_id' => $category->id]);

        $this->getJson('/api/admin/services?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('pagination.total', 12);
    }

    public function test_status_filter_is_available_on_admin_service_listing(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $category = Category::factory()->create();

        Service::factory()->create(['category_id' => $category->id, 'status' => Status::ACTIVE]);
        Service::factory()->create(['category_id' => $category->id, 'status' => Status::INACTIVE]);

        $this->getJson('/api/admin/services?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', Status::INACTIVE->value);
    }

    public function test_service_name_must_be_unique_within_same_category(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $category = Category::factory()->create();

        Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'AC Repair',
        ]);

        $this->postJson('/api/admin/services', [
            'category_id' => $category->id,
            'name' => 'AC Repair',
            'estimated_duration' => 60,
            'base_price' => 499,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A service with this name already exists in the selected category.');
    }

    public function test_service_resource_returns_nested_category_object(): void
    {
        $category = Category::factory()->create();
        Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'AC Repair',
            'status' => Status::ACTIVE,
        ]);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonMissingPath('data.0.category_id');
    }
}
