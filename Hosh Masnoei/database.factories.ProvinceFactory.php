<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    public function definition(): array
    {
        return [
            'name' => fake()->state(),
            'slug' => fake()->unique()->slug(),
            'is_active' => true,
        ];
    }
}
