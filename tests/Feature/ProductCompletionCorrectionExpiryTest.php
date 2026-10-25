<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Enums\UserRole;
use App\Jobs\ExpireProductCompletionTasksJob;
use App\Models\CommissionRule;
use App\Models\ProductCompletionAudit;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionAuditService;
use App\Services\ProductCompletionRevisionService;
use App\Services\ProductCompletionTaskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProductCompletionCorrectionExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Correction expiry tests require the isolated test database.');
        }
        $connection = $app['db']->connection();
        if ($connection->getDatabaseName() !== 'b2b_marketplace_test'
            || $connection->selectOne('SELECT DATABASE() AS current_database', [], false)->current_database !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Resolved connection must use the isolated test database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->freezeSecond();
    }

    public function test_overdue_return_expires_closes_only_open_revision_and_allows_replacement(): void
    {
        [$task, $submission, $contractor, $revision] = $this->returnedTask();
        $history = ProductCompletionRevision::factory()->create([
            'task_id' => $task->id, 'submission_id' => $submission->id,
            'revision_no' => 2, 'status' => ProductCompletionRevisionStatus::RESOLVED,
            'resolved_at' => now()->subDay(),
        ]);
        $historyBefore = $history->refresh()->getRawOriginal();
        $submissionBefore = $submission->refresh()->getRawOriginal();
        $this->travel(3601)->seconds();
        $this->assertFalse(Gate::forUser($contractor)->allows('resumeTask', $task->refresh()));

        $service = app(ProductCompletionTaskService::class);
        (new ExpireProductCompletionTasksJob())->handle($service);

        $this->assertSame(ProductCompletionTaskStatus::EXPIRED, $task->refresh()->status);
        $this->assertNull($task->active_product_id);
        $this->assertTrue($task->expired_at->equalTo(now()));
        $this->assertSame(ProductCompletionRevisionStatus::CANCELLED, $revision->refresh()->status);
        $this->assertNull($revision->open_task_id);
        $this->assertTrue($revision->resolved_at->equalTo(now()));
        $this->assertSame($historyBefore, $history->refresh()->getRawOriginal());
        $this->assertSame($submissionBefore, $submission->refresh()->getRawOriginal());
        $this->assertSame(2, $task->revisions()->count());
        $audit = ProductCompletionAudit::query()->where('task_id', $task->id)->where('action', 'task_expired')->sole();
        $this->assertSame($revision->id, $audit->metadata_json['cancelled_revision_id']);
        $service->expire($task);
        $this->assertSame(1, ProductCompletionAudit::query()->where('task_id', $task->id)->where('action', 'task_expired')->count());

        $actor = User::factory()->create();
        $actor->givePermissionTo('outsourcing.create_task');
        $rule = CommissionRule::factory()->create(['task_type' => ProductCompletionTaskType::CONTENT]);
        $replacement = $service->create($actor, $task->product, $rule, ProductCompletionTaskType::CONTENT);
        $this->assertSame($task->product_id, $replacement->active_product_id);
    }

    public function test_unexpired_return_is_unchanged_by_job_and_direct_expiry(): void
    {
        [$task, , , $revision] = $this->returnedTask();
        $taskBefore = $task->refresh()->getRawOriginal();
        $revisionBefore = $revision->refresh()->getRawOriginal();
        $service = app(ProductCompletionTaskService::class);
        (new ExpireProductCompletionTasksJob())->handle($service);
        $service->expire($task);
        $this->assertSame($taskBefore, $task->refresh()->getRawOriginal());
        $this->assertSame($revisionBefore, $revision->refresh()->getRawOriginal());
    }

    public function test_overdue_submissions_waiting_for_reviewer_never_expire(): void
    {
        $service = app(ProductCompletionTaskService::class);
        foreach ([ProductCompletionTaskStatus::SUBMITTED, ProductCompletionTaskStatus::RESUBMITTED] as $status) {
            $task = ProductCompletionTask::factory()->create([
                'status' => $status, 'due_at' => now()->subWeek(),
                'assignment_expires_at' => now()->subWeek(),
            ]);
            $submission = ProductCompletionSubmission::factory()->create(['task_id' => $task->id]);
            $task->update(['last_submission_id' => $submission->id]);
            $taskBefore = $task->refresh()->getRawOriginal();
            $submissionBefore = $submission->refresh()->getRawOriginal();
            (new ExpireProductCompletionTasksJob())->handle($service);
            $service->expire($task);
            $this->assertSame($taskBefore, $task->refresh()->getRawOriginal());
            $this->assertSame($submissionBefore, $submission->refresh()->getRawOriginal());
            $this->assertFalse(ProductCompletionAudit::query()->where('task_id', $task->id)->exists());
        }
    }

    public function test_expiry_audit_failure_rolls_back_task_and_open_revision(): void
    {
        [$task, , , $revision] = $this->returnedTask();
        $taskBefore = $task->refresh()->getRawOriginal();
        $revisionBefore = $revision->refresh()->getRawOriginal();
        $this->travel(3601)->seconds();
        $this->mock(ProductCompletionAuditService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failed.'));
        });
        try {
            app(ProductCompletionTaskService::class)->expire($task);
            $this->fail('Expiry must be atomic with revision closure and audit.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit failed.', $exception->getMessage());
            $this->assertSame($taskBefore, $task->refresh()->getRawOriginal());
            $this->assertSame($revisionBefore, $revision->refresh()->getRawOriginal());
        }
    }

    private function returnedTask(): array
    {
        $contractor = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $task = ProductCompletionTask::factory()->inProgress($contractor)->create([
            'status' => ProductCompletionTaskStatus::SUBMITTED,
            'reviewer_id' => $reviewer->id, 'completion_duration_seconds' => 3600,
            'due_at' => now()->subWeek(),
        ]);
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->id, 'submitted_by' => $contractor->id,
            'status' => ProductCompletionSubmissionStatus::SUBMITTED,
        ]);
        $task->update(['last_submission_id' => $submission->id]);
        $revision = app(ProductCompletionRevisionService::class)->request(
            $reviewer, $submission, ['title'], 'Correct the title.',
        );

        return [$task->refresh(), $submission->refresh(), $contractor, $revision->refresh()];
    }
}
