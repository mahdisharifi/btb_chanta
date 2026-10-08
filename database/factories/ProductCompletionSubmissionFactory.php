<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductCompletionSubmissionStatus;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductCompletionSubmissionFactory extends Factory
{
    protected $model = ProductCompletionSubmission::class;

    public function definition(): array
    {
        $payload = [
            'schema_version' => 1,
            'title' => 'عنوان تکمیل‌شده',
            'short_description' => 'توضیح کوتاه تکمیل‌شده',
            'full_description' => 'توضیح کامل تکمیل‌شده',
            'specifications' => [],
        ];

        return [
            'task_id' => ProductCompletionTask::factory(),
            'submitted_by' => User::factory(),
            'version_no' => 1,
            'draft_version' => 1,
            'payload_json' => $payload,
            'diff_json' => ['title' => ['before' => 'عنوان اولیه', 'after' => 'عنوان تکمیل‌شده']],
            'media_manifest_json' => null,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE)),
            'base_product_version' => 1,
            'idempotency_key' => hash('sha256', (string) Str::uuid()),
            'status' => ProductCompletionSubmissionStatus::SUBMITTED,
            'submitted_at' => now(),
        ];
    }
}
