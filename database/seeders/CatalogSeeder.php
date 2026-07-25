<?php

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Plumbing' => [
                ['name' => 'Tap Repair', 'price' => 299, 'duration' => 45],
                ['name' => 'Pipe Leak Fix', 'price' => 499, 'duration' => 60],
            ],
            'Electrical' => [
                ['name' => 'Switch Board Repair', 'price' => 349, 'duration' => 45],
                ['name' => 'Fan Installation', 'price' => 599, 'duration' => 60],
            ],
            'Cleaning' => [
                ['name' => 'Sofa Cleaning', 'price' => 899, 'duration' => 90],
                ['name' => 'Bathroom Deep Cleaning', 'price' => 1099, 'duration' => 120],
            ],
        ];

        foreach ($catalog as $categoryName => $services) {
            $category = Category::query()->updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                [
                    'name' => $categoryName,
                    'icon' => 'wrench',
                    'description' => $categoryName.' services',
                    'status' => Status::ACTIVE,
                ]
            );

            foreach ($services as $serviceData) {
                Service::query()->updateOrCreate(
                    ['slug' => Str::slug($serviceData['name'])],
                    [
                        'category_id' => $category->id,
                        'name' => $serviceData['name'],
                        'image' => 'service.jpg',
                        'description' => $serviceData['name'].' service by FixNow',
                        'estimated_duration' => $serviceData['duration'],
                        'base_price' => $serviceData['price'],
                        'status' => Status::ACTIVE,
                    ]
                );
            }
        }
    }
}
