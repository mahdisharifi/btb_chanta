<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionRevisionClassification;
use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Exceptions\ProductCompletionRevisionLimitException;
use App\Models\ProductCompletionAudit;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionAuditService;
use App\Services\ProductCompletionRevisionService;
use App\Services\ProductCompletionStorageGuard;
use App\Services\ProductCompletionTaskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductCompletionRevisionAllowanceTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Revision allowance tests require the isolated test database.');
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

    public function test_exhaustion_requires_explicit_consent_without_writes(): void
    {
        [$task, $submission, $reviewer] = $this->reviewableTask();
        $before = $task->refresh()->getRawOriginal();
        try {
            app(ProductCompletionRevisionService::class)->request($reviewer, $submission, ['title'], 'Correct the title.');
            $this->fail('Exhaustion must require explicit consent.');
        } catch (ProductCompletionRevisionLimitException) {
            $this->assertSame($before, $task->refresh()->getRawOriginal());
            $this->assertSame(0, $task->revisions()->count());
            $this->assertSame(0, ProductCompletionAudit::where('task_id', $task->id)->count());
        }
    }

    public function test_consent_preserves_history_and_grants_a_fresh_work_window(): void
    {
        [$task, $submission, $reviewer, $contractor] = $this->reviewableTask();
        $history = ProductCompletionRevision::factory()->create([
            'submission_id' => $submission->id, 'task_id' => $task->id,
            'requested_by' => $reviewer->id, 'requested_for' => $contractor->id,
            'revision_no' => 3, 'status' => ProductCompletionRevisionStatus::RESOLVED,
            'resolved_at' => now()->subDays(4),
        ]);
        $historyBefore = $history->refresh()->getRawOriginal();
        $revision = $this->request($reviewer, $submission);

        $this->assertSame(4, $task->refresh()->revision_count);
        $this->assertSame(4, $task->revision_limit);
        $this->assertSame(4, $revision->revision_no);
        $this->assertFalse($revision->is_commissionable);
        $this->assertSame($historyBefore, $history->refresh()->getRawOriginal());
        $this->assertTrue($task->due_at->equalTo(now()->addSeconds(7200)));
        $this->assertSame(7200, $task->completion_duration_seconds);
        $audits = ProductCompletionAudit::where('task_id', $task->id)->orderBy('id')->get();
        $this->assertSame(['revision_limit_extended', 'revision_requested'], $audits->pluck('action')->all());
        $this->assertSame(['revision_limit' => 3], $audits[0]->before_json);
        $this->assertSame(['revision_limit' => 4], $audits[0]->after_json);
        $this->assertSame('Correct the title.', $audits[0]->metadata_json['reason']);
        $this->assertSame(ProductCompletionTaskStatus::IN_PROGRESS,
            app(ProductCompletionTaskService::class)->resumeRevision($contractor, $task)->status);
    }

    #[DataProvider('allowanceCases')]
    public function test_allowance_bounds(int $count, int $limit, bool $valid): void
    {
        [$task, $submission, $reviewer] = $this->reviewableTask();
        $task->update(['revision_count' => $count, 'revision_limit' => $limit]);
        if (! $valid) {
            $this->expectException(ValidationException::class);
        }
        $revision = $this->request($reviewer, $submission);
        $this->assertSame($count + 1, $revision->revision_no);
        $this->assertSame($count + 1, $task->refresh()->revision_limit);
    }

    public static function allowanceCases(): array
    {
        return [[2, 3, false], [100, 100, false], [99, 99, true], [5, 3, false], [0, 0, true]];
    }

    public function test_inconsistent_counters_cannot_extend_or_change_history(): void
    {
        [$task, $submission, $reviewer, $contractor] = $this->reviewableTask();
        $task->update(['revision_count' => 5]);
        $history = ProductCompletionRevision::factory()->create([
            'task_id' => $task->id, 'submission_id' => $submission->id,
            'requested_by' => $reviewer->id, 'requested_for' => $contractor->id,
            'revision_no' => 5, 'status' => ProductCompletionRevisionStatus::RESOLVED,
            'resolved_at' => now()->subDay(),
        ]);
        $taskBefore = $task->refresh()->getRawOriginal();
        $submissionBefore = $submission->refresh()->getRawOriginal();
        $historyBefore = $history->refresh()->getRawOriginal();
        try {
            $this->request($reviewer, $submission);
            $this->fail('Inconsistent counters require reconciliation before extension.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('revision_limit', $exception->errors());
            $this->assertSame($taskBefore, $task->refresh()->getRawOriginal());
            $this->assertSame($submissionBefore, $submission->refresh()->getRawOriginal());
            $this->assertSame($historyBefore, $history->refresh()->getRawOriginal());
            $this->assertSame(1, $task->revisions()->count());
            $this->assertFalse(ProductCompletionAudit::query()->where('task_id', $task->id)->exists());
        }
    }

    #[DataProvider('invalidStates')]
    public function test_locked_state_checks_apply_even_with_gate_bypass(string $condition): void
    {
        [$task, $submission, $reviewer] = $this->reviewableTask();
        Gate::before(static fn () => true);
        if ($condition === 'task') {
            $task->update(['status' => ProductCompletionTaskStatus::COMPLETED]);
        } elseif ($condition === 'latest') {
            $task->update(['last_submission_id' => null]);
        } else {
            $submission->update(['status' => ProductCompletionSubmissionStatus::WITHDRAWN]);
        }
        $before = $task->refresh()->getRawOriginal();
        try {
            $this->request($reviewer, $submission);
            $this->fail('Gate bypass must not bypass domain checks.');
        } catch (ValidationException) {
            $this->assertSame($before, $task->refresh()->getRawOriginal());
            $this->assertSame(0, $task->revisions()->count());
        }
    }

    public static function invalidStates(): array
    {
        return [['task'], ['latest'], ['submission']];
    }

    public function test_invalid_scope_and_open_revision_cannot_extend_allowance(): void
    {
        [$task, $submission, $reviewer] = $this->reviewableTask();
        foreach ([['prices'], ['title']] as $fields) {
            if ($fields === ['title']) {
                ProductCompletionRevision::factory()->create([
                    'task_id' => $task->id, 'submission_id' => $submission->id,
                    'open_task_id' => $task->id,
                ]);
            }
            try {
                app(ProductCompletionRevisionService::class)->request(
                    $reviewer, $submission, $fields, 'Correct the title.', extendRevisionAllowance: true,
                );
                $this->fail('Invalid requests must fail before changing the allowance.');
            } catch (ValidationException) {
                $this->assertSame(3, $task->refresh()->revision_limit);
                $this->assertSame(3, $task->revision_count);
                $this->assertSame(0, ProductCompletionAudit::where('task_id', $task->id)->count());
            }
        }
    }

    public function test_audit_failure_rolls_back_the_entire_request(): void
    {
        [$task, $submission, $reviewer] = $this->reviewableTask();
        $taskBefore = $task->refresh()->getRawOriginal();
        $submissionBefore = $submission->refresh()->getRawOriginal();
        $this->mock(ProductCompletionAuditService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failed.'));
        });
        try {
            $this->request($reviewer, $submission);
            $this->fail('Audit failure must abort the request.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit failed.', $exception->getMessage());
            $this->assertSame($taskBefore, $task->refresh()->getRawOriginal());
            $this->assertSame($submissionBefore, $submission->refresh()->getRawOriginal());
            $this->assertSame(0, $task->revisions()->count());
        }
    }

    public function test_storage_guard_prevents_writes(): void
    {
        [$task, $submission, $reviewer] = $this->reviewableTask();
        $this->mock(ProductCompletionStorageGuard::class, function ($mock): void {
            $mock->shouldReceive('assertTransactional')->once()->andThrow(
                ValidationException::withMessages(['storage' => 'Transactional storage required.']),
            );
        });
        $this->expectException(ValidationException::class);
        $this->request($reviewer, $submission);
    }

    public function test_contractor_cannot_consent_and_scope_change_still_requires_permission(): void
    {
        [$task, $submission, $reviewer, $contractor] = $this->reviewableTask();
        $reviewer->syncRoles([]);
        $reviewer->syncPermissions(['outsourcing.request_revision', 'outsourcing.review_submission']);
        foreach ([$contractor, $reviewer] as $actor) {
            try {
                app(ProductCompletionRevisionService::class)->request(
                    $actor, $submission, ['title'], 'Correct the title.',
                    ProductCompletionRevisionClassification::SUPPLIER_SCOPE_CHANGE, true,
                );
                $this->fail('Consent cannot bypass authorization.');
            } catch (AuthorizationException) {
                $this->assertSame(3, $task->refresh()->revision_limit);
                $this->assertSame(0, $task->revisions()->count());
            }
        }
        $reviewer->givePermissionTo('outsourcing.approve_revision_commission');
        $revision = app(ProductCompletionRevisionService::class)->request(
            $reviewer, $submission, ['title'], 'Correct the title.',
            ProductCompletionRevisionClassification::SUPPLIER_SCOPE_CHANGE, true,
        );
        $this->assertTrue($revision->is_commissionable);
    }

    #[DataProvider('durationCases')]
    public function test_normal_return_refreshes_deadline_with_bounded_duration(?int $duration, int $days, int $expected): void
    {
        [$task, $submission, $reviewer] = $this->reviewableTask();
        $task->update(['revision_count' => 0, 'completion_duration_seconds' => $duration]);
        config(['product_completion.defaults.due_days' => $days]);
        app(ProductCompletionRevisionService::class)->request($reviewer, $submission, ['title'], 'Correct the title.');
        $this->assertSame($expected, $task->refresh()->completion_duration_seconds);
        $this->assertTrue($task->due_at->equalTo(now()->addSeconds($expected)));
        $this->assertSame(3, $task->revision_limit);
        $this->assertSame(1, $task->revision_count);
        $this->assertSame(['revision_requested'], ProductCompletionAudit::where('task_id', $task->id)->pluck('action')->all());
    }

    public static function durationCases(): array
    {
        return [[null, 3, 259200], [0, 0, 86400], [null, 999, 7776000], [9000000, 3, 7776000], [1, 3, 1]];
    }

    private function request(User $actor, ProductCompletionSubmission $submission): ProductCompletionRevision
    {
        return app(ProductCompletionRevisionService::class)->request(
            $actor, $submission, ['title'], 'Correct the title.', extendRevisionAllowance: true,
        );
    }

    private function reviewableTask(): array
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $contractor = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($contractor)->create([
            'status' => ProductCompletionTaskStatus::SUBMITTED, 'reviewer_id' => $reviewer->id,
            'revision_count' => 3, 'revision_limit' => 3,
            'completion_duration_seconds' => 7200, 'due_at' => now()->subWeek(),
        ]);
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->id, 'submitted_by' => $contractor->id,
        ]);
        $task->update(['last_submission_id' => $submission->id]);

        return [$task, $submission, $reviewer, $contractor];
    }
}
