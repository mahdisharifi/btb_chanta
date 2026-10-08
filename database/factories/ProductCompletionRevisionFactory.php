<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductCompletionRevisionClassification;
use App\Enums\ProductCompletionRevisionStatus;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCompletionRevisionFactory extends Factory
{
    protected $model = ProductCompletionRevision::class;

    public function definition(): array
    {
        return [
            'task_id' => fn (array $attributes) => ProductCompletionSubmission::query()->find($attributes['submission_id'])->task_id,
            'submission_id' => ProductCompletionSubmission::factory(),
            'requested_by' => User::factory(),
            'requested_for' => User::factory(),
            'revision_no' => 1,
            'classification' => ProductCompletionRevisionClassification::CONTRACTOR_ERROR,
            'status' => ProductCompletionRevisionStatus::OPEN,
            'is_commissionable' => false,
            'reason' => 'عنوان محصول نیاز به اصلاح دارد.',
            'requested_fields_json' => ['title'],
            'requested_at' => now(),
        ];
    }
}
