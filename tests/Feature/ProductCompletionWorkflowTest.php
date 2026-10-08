<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionDraftService;
use App\Services\ProductCompletionSubmissionService;
use App\Services\ProductCompletionTaskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCompletionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_accept_start_save_and_submit_are_state_safe_and_idempotent(): void
    {
        $outsourcer = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->assignedTo($outsourcer)->create();
        $taskService = app(ProductCompletionTaskService::class);
        $draftService = app(ProductCompletionDraftService::class);
        $submissionService = app(ProductCompletionSubmissionService::class);

        $task = $taskService->accept($outsourcer, $task);
        $task = $taskService->start($outsourcer, $task);
        $this->assertSame(ProductCompletionTaskStatus::IN_PROGRESS, $task->status);

        $draft = $task->current_draft_json;
        $draft['title'] = 'عنوان امن و تکمیل شده';
        $task = $draftService->save($outsourcer, $task, $draft, 0);

        $token = 'same-submit-token';
        $first = $submissionService->submit($outsourcer, $task, 1, $token);
        $second = $submissionService->submit($outsourcer, $task->refresh(), 1, $token);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, ProductCompletionSubmission::query()->where('task_id', $task->getKey())->count());
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);
    }
}
