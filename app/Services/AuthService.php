<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthService
{
    public function registerCustomer(array $data): array
    {
        return $this->register($data, UserRole::CUSTOMER, UserStatus::ACTIVE);
    }

    public function registerProvider(array $data): array
    {
        return $this->register($data, UserRole::PROVIDER, UserStatus::ACTIVE);
    }

    public function login(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            throw new AuthenticationException('Invalid email or password.');
        }

        if ($user->status === UserStatus::SUSPENDED) {
            throw new AuthenticationException('Your account has been suspended.');
        }

        if ($user->status === UserStatus::INACTIVE) {
            throw new AuthenticationException('Your account is inactive.');
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return [
            'user' => $user->fresh(),
            'token' => $user->createToken($credentials['device_name'] ?? 'api-token')->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function googleRedirectUrl(): string
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }

    public function handleGoogleCallback(): array
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = DB::transaction(function () use ($googleUser): User {
            $user = User::query()
                ->where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user && $user->role !== UserRole::CUSTOMER) {
                throw new AuthenticationException('Google login is available for customer accounts only.');
            }

            if (! $user) {
                $user = User::query()->create([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'role' => UserRole::CUSTOMER,
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => now(),
                    'last_login_at' => now(),
                ]);

                return $user;
            }

            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'last_login_at' => now(),
            ])->save();

            return $user;
        });

        if ($user->status === UserStatus::SUSPENDED) {
            throw new AuthenticationException('Your account has been suspended.');
        }

        if ($user->status === UserStatus::INACTIVE) {
            throw new AuthenticationException('Your account is inactive.');
        }

        return [
            'user' => $user->fresh(),
            'token' => $user->createToken('google-login')->plainTextToken,
        ];
    }

    protected function register(array $data, UserRole $role, UserStatus $status): array
    {
        $user = DB::transaction(function () use ($data, $role, $status): User {
            return User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $role,
                'status' => $status,
            ]);
        });

        return [
            'user' => $user->fresh(),
            'token' => $user->createToken($data['device_name'] ?? 'api-token')->plainTextToken,
        ];
    }
}
