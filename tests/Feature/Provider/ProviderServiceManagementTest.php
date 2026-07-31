<?php

namespace Tests\Feature\Provider;

use App\Enums\ProviderVerificationStatus;
use App\Enums\Status;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\ProviderService;
use App\Models\ProviderServiceArea;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_add_and_list_selected_services(): void
    {
        $provider = $this->createProviderWithProfile();
        $service = $this->createActiveService();

        Sanctum::actingAs($provider->user);

        $this->postJson('/api/provider/services', [
            'service_id' => $service->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.service.id', $service->id);

        $this->getJson('/api/provider/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service.id', $service->id);
    }

    public function test_provider_cannot_add_duplicate_or_inactive_service(): void
    {
        $provider = $this->createProviderWithProfile();
        $service = $this->createActiveService();
        $inactiveService = $this->createActiveService(status: Status::INACTIVE);

        Sanctum::actingAs($provider->user);

        $this->postJson('/api/provider/services', [
            'service_id' => $service->id,
        ])->assertCreated();

        $this->postJson('/api/provider/services', [
            'service_id' => $service->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This service is already selected by the provider.');

        $this->postJson('/api/provider/services', [
            'service_id' => $inactiveService->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only active services from active categories can be selected.');
    }

    public function test_provider_can_remove_only_own_service(): void
    {
        $provider = $this->createProviderWithProfile();
        $otherProvider = $this->createProviderWithProfile('other@example.com');
        $service = $this->createActiveService();
        $providerService = ProviderService::factory()->create([
            'provider_profile_id' => $provider->id,
            'service_id' => $service->id,
        ]);
        $otherProviderService = ProviderService::factory()->create([
            'provider_profile_id' => $otherProvider->id,
            'service_id' => $service->id,
        ]);

        Sanctum::actingAs($provider->user);

        $this->deleteJson("/api/provider/services/{$otherProviderService->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');

        $this->deleteJson("/api/provider/services/{$providerService->id}")
            ->assertOk();

        $this->assertDatabaseMissing('provider_services', [
            'id' => $providerService->id,
        ]);
    }

    public function test_provider_can_manage_service_areas(): void
    {
        $provider = $this->createProviderWithProfile();

        Sanctum::actingAs($provider->user);

        $this->postJson('/api/provider/service-areas', [
            'postal_code' => '700016',
            'city' => 'Kolkata',
            'state' => 'West Bengal',
        ])
            ->assertCreated()
            ->assertJsonPath('data.postal_code', '700016');

        $serviceAreaId = ProviderServiceArea::query()->value('id');

        $this->patchJson("/api/provider/service-areas/{$serviceAreaId}", [
            'city' => 'Howrah',
        ])
            ->assertOk()
            ->assertJsonPath('data.city', 'Howrah');

        $this->getJson('/api/provider/service-areas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city', 'Howrah');

        $this->deleteJson("/api/provider/service-areas/{$serviceAreaId}")
            ->assertOk();

        $this->assertDatabaseMissing('provider_service_areas', [
            'id' => $serviceAreaId,
        ]);
    }

    public function test_provider_cannot_manage_duplicate_or_other_service_areas(): void
    {
        $provider = $this->createProviderWithProfile();
        $otherProvider = $this->createProviderWithProfile('another@example.com');
        $serviceArea = ProviderServiceArea::factory()->create([
            'provider_profile_id' => $provider->id,
            'postal_code' => '700016',
        ]);
        $otherArea = ProviderServiceArea::factory()->create([
            'provider_profile_id' => $otherProvider->id,
            'postal_code' => '700017',
        ]);

        Sanctum::actingAs($provider->user);

        $this->postJson('/api/provider/service-areas', [
            'postal_code' => '700016',
            'city' => 'Kolkata',
            'state' => 'West Bengal',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This postal code is already covered by the provider.');

        $this->patchJson("/api/provider/service-areas/{$otherArea->id}", [
            'city' => 'Kolkata',
        ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');

        $this->deleteJson("/api/provider/service-areas/{$otherArea->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');

        $duplicateArea = ProviderServiceArea::factory()->create([
            'provider_profile_id' => $provider->id,
            'postal_code' => '700018',
        ]);

        $this->patchJson("/api/provider/service-areas/{$duplicateArea->id}", [
            'postal_code' => '700016',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This postal code is already covered by the provider.');

        $this->assertDatabaseHas('provider_service_areas', [
            'id' => $serviceArea->id,
        ]);
    }

    public function test_provider_service_management_honors_authorization_and_validation_format(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::CUSTOMER]));

        $this->getJson('/api/provider/services')
            ->assertStatus(403)
            ->assertJsonPath('message', 'You are not authorized to access this resource.');

        $provider = $this->createProviderWithProfile('validated@example.com');
        Sanctum::actingAs($provider->user);

        $this->postJson('/api/provider/services', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');

        $this->postJson('/api/provider/service-areas', [
            'postal_code' => '',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }

    protected function createProviderWithProfile(string $email = 'provider@example.com'): ProviderProfile
    {
        $provider = User::factory()->create([
            'email' => $email,
            'role' => UserRole::PROVIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        return ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::APPROVED,
        ])->fresh('user');
    }

    protected function createActiveService(Status $status = Status::ACTIVE): Service
    {
        $category = Category::factory()->create([
            'status' => Status::ACTIVE,
        ]);

        return Service::factory()->create([
            'category_id' => $category->id,
            'status' => $status,
        ]);
    }
}
