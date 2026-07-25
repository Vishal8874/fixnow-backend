<?php

namespace Tests\Feature\Provider;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_create_profile(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::PROVIDER, 'phone' => '9876543210']));

        $this->postJson('/api/provider/profile', [
            'about' => 'Experienced electrician',
            'experience_years' => 8,
        ])
            ->assertCreated()
            ->assertJsonPath('data.provider.verification_status', ProviderVerificationStatus::PENDING->value)
            ->assertJsonPath('data.provider.phone', '9876543210');
    }

    public function test_provider_can_update_profile(): void
    {
        $provider = User::factory()->create(['role' => UserRole::PROVIDER]);
        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'about' => 'Old bio',
            'experience_years' => 3,
        ]);

        Sanctum::actingAs($provider);

        $this->patchJson('/api/provider/profile', [
            'about' => 'Updated bio',
            'experience_years' => 4,
        ])
            ->assertOk()
            ->assertJsonPath('data.provider.about', 'Updated bio')
            ->assertJsonPath('data.provider.experience_years', 4);
    }

    public function test_provider_cannot_create_second_profile(): void
    {
        $provider = User::factory()->create(['role' => UserRole::PROVIDER]);
        ProviderProfile::factory()->create(['user_id' => $provider->id]);

        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/profile', [
            'about' => 'Another bio',
            'experience_years' => 5,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Provider profile already exists.');
    }

    public function test_admin_can_approve_provider(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $provider = User::factory()->create(['role' => UserRole::PROVIDER]);
        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::PENDING,
        ]);

        $this->patchJson("/api/admin/providers/{$provider->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.provider.verification_status', ProviderVerificationStatus::APPROVED->value);
    }

    public function test_admin_can_reject_provider(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $provider = User::factory()->create(['role' => UserRole::PROVIDER]);
        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::PENDING,
        ]);

        $this->patchJson("/api/admin/providers/{$provider->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.provider.verification_status', ProviderVerificationStatus::REJECTED->value);
    }

    public function test_approving_already_approved_provider_returns_conflict(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $provider = User::factory()->create(['role' => UserRole::PROVIDER]);
        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::APPROVED,
        ]);

        $this->patchJson("/api/admin/providers/{$provider->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Provider is already approved.');
    }

    public function test_rejecting_already_rejected_provider_returns_conflict(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
        $provider = User::factory()->create(['role' => UserRole::PROVIDER]);
        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::REJECTED,
        ]);

        $this->patchJson("/api/admin/providers/{$provider->id}/reject")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Provider is already rejected.');
    }

    public function test_unauthorized_access_is_blocked(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::CUSTOMER]));

        $this->getJson('/api/provider/profile')
            ->assertStatus(403)
            ->assertJsonPath('message', 'You are not authorized to access this resource.');
    }

    public function test_provider_registration_still_allows_login_before_profile_approval(): void
    {
        $provider = User::factory()->create([
            'role' => UserRole::PROVIDER,
            'email' => 'provider@example.com',
            'password' => 'Password123!',
            'status' => UserStatus::ACTIVE,
        ]);

        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'verification_status' => ProviderVerificationStatus::PENDING,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'provider@example.com',
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $provider->id);
    }

    public function test_provider_profile_validation_errors_follow_standard_response(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::PROVIDER]));

        $this->postJson('/api/provider/profile', [
            'about' => '',
            'experience_years' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }
}
