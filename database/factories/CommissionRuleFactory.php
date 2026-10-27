<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommissionCalculationType;
use App\Enums\ProductCompletionRevisionPolicy;
use App\Enums\ProductCompletionTaskType;
use App\Models\CommissionRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    public function definition(): array
    {
        return [
            'name' => 'قانون '.fake()->unique()->numberBetween(1000, 999999),
            'version' => 1,
            'task_type' => ProductCompletionTaskType::FULL_COMPLETION,
            'calculation_type' => CommissionCalculationType::PER_TASK,
            'revision_policy' => ProductCompletionRevisionPolicy::SCOPE_CHANGE_ONLY,
            'base_amount' => '500000.00',
            'per_revision_amount' => '50000.00',
            'per_approved_item_amount' => '0.00',
            'max_amount' => '1000000.00',
            'currency' => 'IRR',
            'conditions_json' => null,
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'effective_until' => now()->addYear(),
            'created_by' => User::factory(),
        ];
    }
}
