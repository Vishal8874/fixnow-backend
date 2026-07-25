<?php

namespace Database\Seeders;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\ProviderAvailability;
use App\Models\ProviderProfile;
use App\Models\ProviderService;
use App\Models\ProviderServiceArea;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ApprovedProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providerDefinitions = [
            [
                'name' => 'Rahul Sharma',
                'email' => 'provider1@fixnow.test',
                'phone' => '9876500001',
                'about' => 'Experienced plumbing and electrical technician.',
                'experience_years' => 8,
                'postal_codes' => ['560001', '560002'],
                'service_categories' => ['Plumbing', 'Electrical'],
            ],
            [
                'name' => 'Amit Kumar',
                'email' => 'provider2@fixnow.test',
                'phone' => '9876500002',
                'about' => 'Cleaning and home maintenance specialist.',
                'experience_years' => 6,
                'postal_codes' => ['560001', '560003'],
                'service_categories' => ['Cleaning'],
            ],
            [
                'name' => 'Neha Patel',
                'email' => 'provider3@fixnow.test',
                'phone' => '9876500003',
                'about' => 'Multi-service field technician for urgent household jobs.',
                'experience_years' => 5,
                'postal_codes' => ['560002', '560004'],
                'service_categories' => ['Plumbing', 'Cleaning'],
            ],
        ];

        foreach ($providerDefinitions as $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'phone' => $definition['phone'],
                    'password' => Hash::make('Password123!'),
                    'role' => UserRole::PROVIDER,
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => now(),
                ]
            );

            $profile = ProviderProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'profile_image' => null,
                    'about' => $definition['about'],
                    'experience_years' => $definition['experience_years'],
                    'verification_status' => ProviderVerificationStatus::APPROVED,
                    'average_rating' => 0,
                    'total_reviews' => 0,
                ]
            );

            $serviceIds = Service::query()
                ->whereIn('category_id', Category::query()->whereIn('name', $definition['service_categories'])->pluck('id'))
                ->pluck('id');

            foreach ($serviceIds as $serviceId) {
                ProviderService::query()->firstOrCreate([
                    'provider_profile_id' => $profile->id,
                    'service_id' => $serviceId,
                ]);
            }

            foreach ($definition['postal_codes'] as $postalCode) {
                ProviderServiceArea::query()->updateOrCreate(
                    [
                        'provider_profile_id' => $profile->id,
                        'postal_code' => $postalCode,
                    ],
                    [
                        'city' => 'Bengaluru',
                        'state' => 'Karnataka',
                    ]
                );
            }

            ProviderAvailability::query()->updateOrCreate(
                ['provider_profile_id' => $profile->id],
                [
                    'is_available' => true,
                    'available_from' => '09:00:00',
                    'available_until' => '18:00:00',
                    'notes' => 'Seeded development availability',
                ]
            );
        }
    }
}
