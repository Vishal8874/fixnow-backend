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

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_categories(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $createResponse = $this->postJson('/api/admin/categories', [
            'name' => 'Electrical',
            'slug' => 'electrical',
            'icon' => 'bolt',
            'description' => 'Electrical services',
            'status' => Status::ACTIVE->value,
        ]);

        $categoryId = $createResponse->json('data.id');

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.slug', 'electrical');

        $this->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1);

        $this->getJson("/api/admin/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Electrical');

        $this->patchJson("/api/admin/categories/{$categoryId}", [
            'name' => 'Electrical Repairs',
            'status' => Status::INACTIVE->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Electrical Repairs')
            ->assertJsonPath('data.status', Status::INACTIVE->value);

        $this->deleteJson("/api/admin/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('message', 'Category deleted successfully.');
    }

    public function test_category_validation_errors_follow_standard_response(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $this->postJson('/api/admin/categories', [
            'name' => '',
            'status' => 'invalid',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }

    public function test_category_delete_returns_conflict_when_services_exist(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        $category = Category::factory()->create();
        Service::factory()->create(['category_id' => $category->id]);

        $this->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Category cannot be deleted while services are linked to it.');
    }

    public function test_public_categories_returns_only_active_categories_with_pagination(): void
    {
        Category::factory()->count(11)->create(['status' => Status::ACTIVE]);
        Category::factory()->create(['status' => Status::INACTIVE]);

        $this->getJson('/api/categories?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('pagination.total', 11);
    }
}
