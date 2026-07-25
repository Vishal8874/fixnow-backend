<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('login@example.com|login');
        RateLimiter::clear('asha@example.com|register');
        RateLimiter::clear('127.0.0.1|google-callback');

        parent::tearDown();
    }

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/auth/register/customer', [
            'name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'ios-app',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'asha@example.com')
            ->assertJsonPath('data.user.role', UserRole::CUSTOMER->value)
            ->assertJsonPath('data.user.status', UserStatus::ACTIVE->value);
    }

    public function test_provider_can_register_with_active_status(): void
    {
        $response = $this->postJson('/api/auth/register/provider', [
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', UserRole::PROVIDER->value)
            ->assertJsonPath('data.user.status', UserStatus::ACTIVE->value);
    }

    public function test_user_can_login_with_email_only_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'web-browser',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'Password123!',
            'status' => UserStatus::SUSPENDED,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your account has been suspended.');
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $meResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $meResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $user->email);

        $logoutResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $logoutResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logout successful.');
    }

    public function test_auth_endpoints_are_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'login@example.com',
                'password' => 'Password123!',
            ])->assertOk();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ])->assertStatus(429);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/auth/register/customer', [
                'name' => 'Asha Verma',
                'email' => 'asha@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])->assertStatus($attempt === 1 ? 201 : 422);
        }

        $this->postJson('/api/auth/register/customer', [
            'name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(429);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->getJson('/api/auth/google/callback')->assertStatus(500);
        }

        $this->getJson('/api/auth/google/callback')->assertStatus(429);
    }
}
