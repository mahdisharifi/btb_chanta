<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'company_name_en' => fake()->company(),
            'brand_name' => fake()->company(),
            'description' => fake()->sentence(),
            'phone' => fake()->phoneNumber(),
            'mobile' => '09' . fake()->numberBetween(100000000, 999999999),
            'email' => fake()->companyEmail(),
            'is_verified' => true,
            'is_active' => true,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'pending',
            'is_verified' => false,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'rejected',
            'is_verified' => false,
        ]);
    }
}
