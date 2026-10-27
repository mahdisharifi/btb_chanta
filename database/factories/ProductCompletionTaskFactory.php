<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductCompletionReviewMode;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCompletionTaskFactory extends Factory
{
    protected $model = ProductCompletionTask::class;

    public function definition(): array
    {
        $scope = config('product_completion.scope_presets.content');

        return [
            'product_id' => Product::factory(),
            'active_product_id' => fn (array $attributes) => $attributes['product_id'],
            'supplier_id' => fn (array $attributes) => Product::query()->find($attributes['product_id'])?->supplier_id,
            'created_by' => User::factory(),
            'task_type' => ProductCompletionTaskType::CONTENT,
            'status' => ProductCompletionTaskStatus::DRAFT,
            'review_mode' => ProductCompletionReviewMode::REVIEWER,
            'scope_schema_version' => 1,
            'scope_json' => $scope,
            'initial_snapshot_json' => [
                'schema_version' => 1,
                'product_content_version' => 1,
                'category_id' => 1,
                'title' => 'عنوان اولیه',
                'short_description' => 'توضیح کوتاه اولیه',
                'full_description' => 'توضیح کامل اولیه',
                'specifications' => [],
            ],
            'current_draft_json' => [
                'schema_version' => 1,
                'title' => 'عنوان اولیه',
                'short_description' => 'توضیح کوتاه اولیه',
                'full_description' => 'توضیح کامل اولیه',
                'specifications' => [],
            ],
            'draft_version' => 0,
            'lock_version' => 0,
            'product_version_at_assignment' => 1,
            'product_updated_at_assignment' => now(),
            'commission_rule_id' => CommissionRule::factory()->state([
                'task_type' => ProductCompletionTaskType::CONTENT,
            ]),
            'commission_rule_snapshot_json' => [
                'rule_id' => null,
                'rule_version' => 1,
                'task_type' => 'content',
                'calculation_type' => 'per_task',
                'revision_policy' => 'scope_change_only',
                'base_amount' => '500000.00',
                'per_revision_amount' => '50000.00',
                'per_approved_item_amount' => '0.00',
                'max_amount' => '1000000.00',
                'currency' => 'IRR',
            ],
            'quoted_base_amount' => '500000.00',
            'currency' => 'IRR',
            'revision_limit' => 3,
            'revision_count' => 0,
            'commission_rate_percentage' => '100.00',
            'priority' => 0,
            'due_at' => now()->addDays(3),
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (): array => [
            'assigned_to' => $user->getKey(),
            'status' => ProductCompletionTaskStatus::ASSIGNED,
            'assigned_at' => now(),
            'assignment_expires_at' => now()->addDay(),
        ]);
    }

    public function inProgress(User $user): static
    {
        return $this->assignedTo($user)->state(fn (): array => [
            'status' => ProductCompletionTaskStatus::IN_PROGRESS,
            'accepted_at' => now()->subHour(),
            'started_at' => now()->subMinutes(30),
        ]);
    }
}
