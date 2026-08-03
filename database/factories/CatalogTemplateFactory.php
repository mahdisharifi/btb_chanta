<?php

namespace Database\Factories;

use App\Models\CatalogTemplate;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogTemplateFactory extends Factory
{
    protected $model = CatalogTemplate::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'template_data' => [
                'pages' => fake()->numberBetween(1, 10),
                'layout' => 'grid',
            ],
            'is_active' => true,
        ];
    }
}
