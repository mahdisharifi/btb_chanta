<?php

namespace Database\Factories;

use App\Models\ProductPrice;
use App\Models\Product;
use App\Enums\PriceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductPriceFactory extends Factory
{
    protected $model = ProductPrice::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_supplier_id' => null,
            'price_type' => PriceType::CASH,
            'price' => fake()->numberBetween(10000, 10000000),
            'min_quantity' => 1,
            'max_quantity' => null,
            'is_active' => true,
        ];
    }

    public function check(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_type' => fake()->randomElement([
                PriceType::CHECK_1_MONTH,
                PriceType::CHECK_2_MONTH,
                PriceType::CHECK_3_MONTH,
            ]),
        ]);
    }

    public function warranty(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_type' => PriceType::WARRANTY,
            'warranty_months' => fake()->numberBetween(6, 24),
        ]);
    }
}
