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
            'selected_categories' => [1, 2],
            'selected_price_types' => ['cash', 'check_1_month'],
            'settings' => [
                'show_prices' => true,
                'layout' => 'grid',
            ],
        ];
    }
}
