<?php

namespace Database\Factories;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(10000, 1000000),
            'duration_days' => fake()->numberBetween(30, 365),
            'is_active' => true,
        ];
    }
}
