<?php

namespace Database\Factories;

use App\Models\Flowchart;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlowchartFactory extends Factory
{
    protected $model = Flowchart::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'content' => [
                'nodes' => [],
                'edges' => [],
            ],
            'is_active' => true,
        ];
    }
}
