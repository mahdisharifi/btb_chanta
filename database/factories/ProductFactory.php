<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'sku' => 'PRD-' . strtoupper(fake()->bothify('??####')),
            'short_description' => fake()->sentence(),
            'full_description' => fake()->paragraphs(3, true),
            'specifications' => null,
            'weight' => fake()->numberBetween(100, 5000),
            'is_active' => true,
            'is_featured' => false,
            'status' => ProductStatus::ACTIVE,
            'view_count' => fake()->numberBetween(0, 1000),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => ProductStatus::INACTIVE,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
