<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@fixnow.test'],
            [
                'name' => 'Priya Verma',
                'phone' => '9876500010',
                'password' => Hash::make('Password123!'),
                'role' => UserRole::CUSTOMER,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        CustomerAddress::query()->updateOrCreate(
            [
                'user_id' => $customer->id,
                'label' => 'Home',
            ],
            [
                'contact_person' => 'Priya Verma',
                'contact_phone' => '9876500010',
                'address_line_1' => '12 MG Road',
                'address_line_2' => 'Apartment 4B',
                'landmark' => 'Near Metro Station',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'postal_code' => '560001',
                'latitude' => 12.9716,
                'longitude' => 77.5946,
                'is_default' => true,
            ]
        );

        CustomerAddress::query()->updateOrCreate(
            [
                'user_id' => $customer->id,
                'label' => 'Office',
            ],
            [
                'contact_person' => 'Priya Verma',
                'contact_phone' => '9876500010',
                'address_line_1' => '55 Residency Road',
                'address_line_2' => null,
                'landmark' => 'Business Tower',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'postal_code' => '560002',
                'latitude' => 12.9667,
                'longitude' => 77.6060,
                'is_default' => false,
            ]
        );
    }
}
