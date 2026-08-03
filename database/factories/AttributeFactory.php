<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Enums\AttributeType;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => AttributeType::TEXT,
            'unit' => fake()->optional()->randomElement(['گرم', 'سانتی‌متر', 'میلی‌لیتر', 'وات']),
            'is_filterable' => fake()->boolean(30),
            'is_multiple' => false,
            'is_visible' => true,
        ];
    }

    public function filterable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_filterable' => true,
        ]);
    }

    public function multiple(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_multiple' => true,
        ]);
    }
}
