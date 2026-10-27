<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductCompletionCommissionStatus;
use App\Models\CommissionRule;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCompletionCommissionFactory extends Factory
{
    protected $model = ProductCompletionCommission::class;

    public function definition(): array
    {
        return [
            'task_id' => ProductCompletionTask::factory(),
            'user_id' => User::factory(),
            'commission_rule_id' => CommissionRule::factory(),
            'currency' => 'IRR',
            'base_amount' => '500000.00',
            'revision_amount' => '0.00',
            'bonus_amount' => '0.00',
            'penalty_amount' => '0.00',
            'adjustment_amount' => '0.00',
            'total_amount' => '500000.00',
            'breakdown_json' => ['rule_version' => 1],
            'status' => ProductCompletionCommissionStatus::CALCULATED,
            'calculated_at' => now(),
        ];
    }
}
