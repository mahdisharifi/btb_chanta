<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Jobs\ExpireProductCompletionTasksJob;
use App\Livewire\Reviewer\Task\Review;
use App\Models\ProductCompletionAudit;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionConflictResolutionService;
use App\Services\ProductCompletionSnapshotService;
use App\Services\ProductCompletionSubmissionService;
use App\Services\ProductCompletionTaskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCompletionReviewRecoveryWindowTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Recovery-window tests require the isolated test database.');
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

    public function test_stale_review_return_is_rejected_without_writes(): void
    {
        [$task, $reviewer] = $this->submittedTask();
        $first = Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task])->call('beginReview');
        $stale = Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task->refresh()]);

        $first->call('toggleReviewEditor')
            ->set('reviewPayload.short_description', 'Reviewer saved this review.')
            ->call('saveReviewEdits')
            ->assertHasNoErrors();

        $taskBefore = $task->refresh()->getRawOriginal();
        $submissionBefore = $task->lastSubmission->getRawOriginal();
        $auditBefore = ProductCompletionAudit::where('task_id', $task->id)->count();
        $revisionBefore = ProductCompletionRevision::where('task_id', $task->id)->count();

        $stale->set('revisionReason', 'Please correct the description.')
            ->call('requestRevision')
            ->assertHasErrors('lock_version');

        $this->assertSame($taskBefore, $task->refresh()->getRawOriginal());
        $this->assertSame($submissionBefore, $task->lastSubmission->refresh()->getRawOriginal());
        $this->assertSame($auditBefore, ProductCompletionAudit::where('task_id', $task->id)->count());
        $this->assertSame($revisionBefore, ProductCompletionRevision::where('task_id', $task->id)->count());
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->status);
    }

    public function test_overdue_conflict_return_refreshes_window_and_preserves_commission_baseline(): void
    {
        [$task, $submission, $reviewer, $contractor] = $this->conflictedTask();
        $baseline = $task->commission_content_snapshot_json;
        $task->update([
            'due_at' => now()->subWeek(),
            'completion_duration_seconds' => 7200,
        ]);
        $expectedLockVersion = $task->refresh()->lock_version;

        $revision = app(ProductCompletionConflictResolutionService::class)->requestRevision(
            $reviewer,
            $task,
            $expectedLockVersion,
            'Please resolve the conflicting description.',
        );

        $task->refresh();
        $this->assertSame(ProductCompletionTaskStatus::CHANGES_REQUESTED, $task->status);
        $this->assertSame(7200, $task->completion_duration_seconds);
        $this->assertTrue($task->due_at->equalTo(now()->addSeconds(7200)));
        $this->assertSame($baseline, $task->commission_content_snapshot_json);
        $this->assertSame(ProductCompletionRevisionStatus::OPEN, $revision->status);
        $this->assertTrue(Gate::forUser($contractor)->allows('resumeTask', $task));

        (new ExpireProductCompletionTasksJob())->handle(app(ProductCompletionTaskService::class));
        $this->assertSame(ProductCompletionTaskStatus::CHANGES_REQUESTED, $task->refresh()->status);

        $this->travel(7201)->seconds();
        (new ExpireProductCompletionTasksJob())->handle(app(ProductCompletionTaskService::class));
        $this->assertSame(ProductCompletionTaskStatus::EXPIRED, $task->refresh()->status);
        $this->assertSame('cancelled', $revision->refresh()->status->value);
        $this->assertSame($baseline, $task->commission_content_snapshot_json);
    }

    private function submittedTask(): array
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $contractor = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($contractor)->create([
            'scope_json' => ['short_description' => true],
            'reviewer_id' => $reviewer->id,
            'current_draft_json' => ['schema_version' => 1, 'short_description' => 'Submitted description.'],
        ]);
        $task->update([
            'initial_snapshot_json' => app(ProductCompletionSnapshotService::class)->build($task->product, $task->scope_json),
            'product_version_at_assignment' => $task->product->content_version,
        ]);
        app(ProductCompletionSubmissionService::class)->submit($contractor, $task, 0, 'recovery-'.$task->public_id);

        return [$task->refresh(), $reviewer];
    }

    private function conflictedTask(): array
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        $contractor = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($contractor)->create([
            'status' => ProductCompletionTaskStatus::CONFLICTED,
            'reviewer_id' => $reviewer->id,
            'scope_json' => ['short_description' => true],
            'conflict_json' => ['short_description' => ['current' => 'Current', 'submitted' => 'Submitted']],
            'commission_content_snapshot_json' => ['schema_version' => 1, 'short_description' => 'Original commission baseline'],
            'completion_duration_seconds' => 7200,
        ]);
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->id,
            'submitted_by' => $contractor->id,
            'status' => ProductCompletionSubmissionStatus::CONFLICTED,
        ]);
        $task->update(['last_submission_id' => $submission->id]);

        return [$task->refresh(), $submission->refresh(), $reviewer, $contractor];
    }
}
