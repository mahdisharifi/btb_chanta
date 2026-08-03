<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => '09' . fake()->numberBetween(100000000, 999999999),
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user's mobile is unverified.
     */
    public function unverifiedMobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'mobile_verified_at' => null,
        ]);
    }

    /**
     * Create a super admin user.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole(\App\Enums\UserRole::SUPER_ADMIN);
        });
    }

    /**
     * Create a supplier user.
     */
    public function supplier(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole(\App\Enums\UserRole::SUPPLIER);
            \App\Models\Supplier::factory()->create([
                'user_id' => $user->id,
                'verification_status' => 'approved',
            ]);
        });
    }

    /**
     * Create a retailer premium user.
     */
    public function retailerPremium(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_expires_at' => now()->addYear(),
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole(\App\Enums\UserRole::RETAILER_PREMIUM);
        });
    }

    /**
     * Create a retailer basic user.
     */
    public function retailerBasic(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_expires_at' => now()->addYear(),
        ])->afterCreating(function (\App\Models\User $user) {
            $user->assignRole(\App\Enums\UserRole::RETAILER_BASIC);
        });
    }
}
