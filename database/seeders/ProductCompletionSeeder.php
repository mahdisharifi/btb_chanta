<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CommissionCalculationType;
use App\Enums\ProductCompletionRevisionPolicy;
use App\Enums\ProductCompletionTaskType;
use App\Models\CommissionRule;
use Illuminate\Database\Seeder;

class ProductCompletionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ProductCompletionTaskType::cases() as $taskType) {
            CommissionRule::query()->firstOrCreate(
                [
                    'name' => 'نرخ پایه '.$taskType->label(),
                    'version' => 1,
                ],
                [
                    'task_type' => $taskType,
                    'calculation_type' => CommissionCalculationType::PER_TASK,
                    'revision_policy' => ProductCompletionRevisionPolicy::SCOPE_CHANGE_ONLY,
                    'base_amount' => '0.00',
                    'per_revision_amount' => '0.00',
                    'per_approved_item_amount' => '0.00',
                    'max_amount' => null,
                    'currency' => config('product_completion.currency', 'IRR'),
                    'conditions_json' => null,
                    'is_active' => false,
                ]
            );
        }
    }
}
