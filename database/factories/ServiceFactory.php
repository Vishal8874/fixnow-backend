<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'image' => 'service.jpg',
            'description' => fake()->sentence(),
            'estimated_duration' => fake()->numberBetween(30, 180),
            'base_price' => fake()->randomFloat(2, 100, 5000),
            'status' => Status::ACTIVE,
        ];
    }
}
