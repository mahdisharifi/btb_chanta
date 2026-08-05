<?php

namespace Database\Factories;

use App\Models\RetailPrice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RetailPriceFactory extends Factory
{
    protected $model = RetailPrice::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'retail_price' => fake()->numberBetween(10000, 10000000),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
