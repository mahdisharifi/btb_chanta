<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionCommissionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionCommissionService;
use App\Services\ProductCompletionMediaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCompletionMediaAndCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_malicious_or_unsupported_file_is_rejected_before_staging(): void
    {
        Storage::fake('local');
        $outsourcer = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($outsourcer)->create([
            'scope_json' => config('product_completion.scope_presets.media'),
        ]);

        $this->expectException(ValidationException::class);
        app(ProductCompletionMediaService::class)->stage(
            $outsourcer,
            $task,
            UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml')
        );
    }

    public function test_commission_is_created_only_once_when_task_is_fully_applied(): void
    {
        $outsourcer = User::factory()->create();
        $task = ProductCompletionTask::factory()->create([
            'assigned_to' => $outsourcer->getKey(),
            'status' => ProductCompletionTaskStatus::APPROVED,
            'content_applied_at' => now(),
            'media_published_at' => now(),
        ]);
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
            'submitted_by' => $outsourcer->getKey(),
            'status' => ProductCompletionSubmissionStatus::APPROVED,
        ]);
        $task->update(['last_submission_id' => $submission->getKey()]);

        $service = app(ProductCompletionCommissionService::class);
        $first = $service->calculate($task);
        $second = $service->calculate($task->refresh());

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, ProductCompletionCommission::query()->where('task_id', $task->getKey())->count());
        $this->assertSame(ProductCompletionCommissionStatus::CALCULATED, $first->status);
        $this->assertSame(ProductCompletionTaskStatus::COMPLETED, $task->refresh()->status);
    }
}
