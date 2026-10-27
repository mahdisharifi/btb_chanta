<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionCommissionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Outsourcing\Commissions;
use App\Livewire\Reviewer\Task\Review;
use App\Models\Attribute;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionDraftService;
use App\Services\ProductCompletionDraftValidator;
use App\Services\ProductCompletionSnapshotService;
use App\Services\ProductCompletionSubmissionService;
use App\Services\ProductCompletionTaskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OutsourcingHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Workflow tests require the isolated test database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_return_without_checkboxes_reopens_work_and_allows_resubmission(): void
    {
        [$task, $reviewer, $outsourcer] = $this->submittedTask();
        Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task])
            ->set('reviewNote', 'Please correct the product description.')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertRedirect(route('reviewer.outsourcing.tasks.index'));

        $this->assertSame(ProductCompletionTaskStatus::CHANGES_REQUESTED, $task->refresh()->status);
        $this->assertNull($task->cancelled_at);
        $this->assertSame($outsourcer->id, $task->assigned_to);
        $this->assertSame(['short_description'], $task->revisions()->firstOrFail()->requested_fields_json);
        $this->assertSame(ProductCompletionSubmissionStatus::CHANGES_REQUESTED, $task->lastSubmission->status);

        $task = app(ProductCompletionTaskService::class)->resumeRevision($outsourcer, $task);
        $task = app(ProductCompletionDraftService::class)->save(
            $outsourcer, $task,
            ['schema_version' => 1, 'short_description' => 'Corrected description.'],
            $task->draft_version,
        );
        app(ProductCompletionSubmissionService::class)->submit($outsourcer, $task, $task->draft_version, 'corrected-version');
        $this->assertSame(ProductCompletionTaskStatus::RESUBMITTED, $task->refresh()->status);
        $this->assertSame('resolved', $task->revisions()->firstOrFail()->status->value);
    }

    public function test_revision_requires_reason_and_rejects_fields_outside_scope(): void
    {
        [$task, $reviewer] = $this->submittedTask();
        Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task])
            ->call('requestRevision')->assertHasErrors('reason');
        Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task])
            ->set('revisionReason', 'Please correct this field.')
            ->set('requestedFields', ['prices'])
            ->call('requestRevision')->assertHasErrors('requested_fields.0');
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);
        $this->assertSame(0, $task->revisions()->count());
    }

    public function test_finance_sees_approved_tasks_without_commissions_and_can_prepare_them(): void
    {
        [$task] = $this->submittedTask();
        $task->update(['due_at' => now()->subWeek()]);
        app(ProductCompletionTaskService::class)->expire($task);
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);
        $task->lastSubmission->update(['status' => ProductCompletionSubmissionStatus::APPROVED]);
        $task->update(['status' => ProductCompletionTaskStatus::APPROVED, 'approved_at' => now()]);
        $finance = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $component = Livewire::actingAs($finance)->test(Commissions::class)
            ->assertSee($task->product->title)
            ->assertSee('تسک‌های تأییدشده بازبین در انتظار محاسبه');
        $this->assertFalse($task->commission()->exists());

        $component->call('calculate', $task->public_id)->assertHasNoErrors();
        $commission = $task->commission()->firstOrFail();
        $this->assertSame(ProductCompletionCommissionStatus::CALCULATED, $commission->status);
        $this->assertSame(ProductCompletionTaskStatus::COMPLETED, $task->refresh()->status);
        $component->call('approve', $commission->public_id)->assertHasNoErrors();
        $this->assertSame(ProductCompletionCommissionStatus::APPROVED, $commission->refresh()->status);
        $this->assertSame(1, ProductCompletionCommission::where('task_id', $task->id)->count());
    }

    public function test_finance_cannot_prepare_a_task_before_reviewer_approval(): void
    {
        [$task] = $this->submittedTask();
        $finance = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        Livewire::actingAs($finance)->test(Commissions::class)
            ->assertDontSee($task->product->title)
            ->call('calculate', $task->public_id)->assertHasErrors('task');
        $this->assertFalse($task->commission()->exists());
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);
    }

    public function test_optional_attributes_can_be_empty_but_required_attributes_block_submission(): void
    {
        $task = ProductCompletionTask::factory()->create(['scope_json' => ['attributes' => true]]);
        DB::table('attribute_category')->where('category_id', $task->product->category_id)->delete();
        $optional = Attribute::factory()->create();
        DB::table('attribute_category')->insert([
            'attribute_id' => $optional->id, 'category_id' => $task->product->category_id, 'is_required' => false,
        ]);
        $payload = ['schema_version' => 1, 'attributes' => []];
        $validated = app(ProductCompletionDraftValidator::class)->validate($task, $payload, null, true)->toArray();
        $this->assertSame([], $validated['attributes']);

        DB::table('attribute_category')->where('attribute_id', $optional->id)->update(['is_required' => true]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ProductCompletionDraftValidator::class)->validate($task, $payload, null, true);
    }

    public function test_reviewer_can_review_and_return_work_after_contractor_deadline(): void
    {
        [$task, $reviewer, $outsourcer] = $this->submittedTask();
        $task->update(['due_at' => now()->subWeek()]);
        app(\App\Jobs\ExpireProductCompletionTasksJob::class)->handle(app(ProductCompletionTaskService::class));
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);

        Livewire::actingAs($reviewer)->test(\App\Livewire\Reviewer\Task\Index::class)
            ->set('tab', 'mine')->assertSee($task->product->title)
            ->call('beginReview', $task->public_id, $task->last_submission_id)
            ->assertHasNoErrors()->assertRedirect(route('reviewer.outsourcing.tasks.review', $task));

        Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task->refresh()])
            ->set('reviewNote', 'Please correct the description.')
            ->call('requestRevision')->assertHasNoErrors();
        $this->assertSame(ProductCompletionTaskStatus::CHANGES_REQUESTED, $task->refresh()->status);
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($outsourcer)->allows('resumeTask', $task));
        $this->assertTrue($task->due_at->isFuture());
    }

    public function test_finance_prioritizes_oldest_calculated_commissions(): void
    {
        $finance = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $recent = ProductCompletionCommission::factory()->create(['calculated_at' => now()->subDay()]);
        $old = ProductCompletionCommission::factory()->create(['calculated_at' => now()->subWeek()]);
        $oldTie = ProductCompletionCommission::factory()->create(['calculated_at' => $old->calculated_at]);

        Livewire::actingAs($finance)->test(Commissions::class)
            ->assertViewHas('commissions', fn ($commissions) => $commissions->pluck('id')->all() === [$old->id, $oldTie->id, $recent->id]);
    }

    public function test_review_and_finance_queues_use_stage_time_then_id(): void
    {
        [$recent, $reviewer] = $this->submittedTask();
        [$old] = $this->submittedTask();
        [$tie] = $this->submittedTask();
        $recent->update(['reviewer_id' => $reviewer->id, 'submitted_at' => now()->subDay(), 'accepted_at' => now()->subMonth()]);
        $old->update(['reviewer_id' => $reviewer->id, 'submitted_at' => now()->subWeek(), 'accepted_at' => now()->subWeek()]);
        $tie->update(['reviewer_id' => $reviewer->id, 'submitted_at' => $old->submitted_at, 'accepted_at' => now()->subMonth()]);
        $expected = [$old->id, $tie->id, $recent->id];

        Livewire::actingAs($reviewer)->test(\App\Livewire\Reviewer\Task\Index::class)
            ->set('tab', 'mine')
            ->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === $expected);

        foreach ([$recent, $old, $tie] as $task) {
            $task->lastSubmission->update(['status' => ProductCompletionSubmissionStatus::APPROVED]);
            $task->update(['status' => ProductCompletionTaskStatus::APPROVED, 'approved_at' => $task->submitted_at]);
        }
        $finance = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        Livewire::actingAs($finance)->test(Commissions::class)
            ->assertViewHas('approvedTasks', fn ($tasks) => $tasks->pluck('id')->all() === $expected);
    }

    public function test_contractor_expiration_preserves_open_reviewer_view_access(): void
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $outsourcer = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($outsourcer)->create([
            'reviewer_id' => null,
            'review_mode' => \App\Enums\ProductCompletionReviewMode::REVIEWER,
            'due_at' => now()->subDay(),
        ]);
        $gate = \Illuminate\Support\Facades\Gate::forUser($reviewer);
        $this->assertTrue($gate->allows('viewTask', $task));
        app(ProductCompletionTaskService::class)->expire($task);
        $task->refresh();
        $this->assertSame(ProductCompletionTaskStatus::EXPIRED, $task->status);
        $this->assertTrue($gate->allows('viewTask', $task));
        $this->assertFalse($gate->allows('reviewSubmission', $task));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($outsourcer)->allows('updateDraft', $task));
    }

    public function test_admin_recovers_interrupted_approval_without_approving_or_paying(): void
    {
        [$task, $reviewer] = $this->submittedTask();
        $submission = $task->lastSubmission;
        $submission->update(['status' => ProductCompletionSubmissionStatus::APPROVED, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => 'Original decision.']);
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\Outsourcing\TaskShow::class, ['task' => $task])
            ->assertSee('بازگردانی تأیید ناقص به بازبینی')
            ->set('recoveryReason', 'Approval was interrupted before task approval.')
            ->call('recoverInterruptedApproval', $task->lock_version)->assertHasNoErrors();

        $this->assertSame(ProductCompletionSubmissionStatus::SUBMITTED, $submission->refresh()->status);
        $this->assertNull($submission->reviewed_at);
        $this->assertNull($submission->review_note);
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);
        $this->assertSame($reviewer->id, $task->reviewer_id);
        $this->assertNull($task->approved_at);
        $this->assertFalse($task->commission()->exists());
        $audit = $task->audits()->where('action', 'interrupted_approval_recovered')->firstOrFail();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('Original decision.', $audit->before_json['review_note']);
        $this->assertSame('approved', $audit->before_json['status']);
        Livewire::actingAs($reviewer)->test(\App\Livewire\Reviewer\Task\Index::class)
            ->set('tab', 'mine')->assertSee($task->product->title);
    }

    public function test_recovery_requires_permission_reason_and_current_version(): void
    {
        [$task, $reviewer] = $this->submittedTask();
        $task->lastSubmission->update(['status' => ProductCompletionSubmissionStatus::APPROVED]);
        Livewire::actingAs($reviewer)->test(\App\Livewire\Admin\Outsourcing\TaskShow::class, ['task' => $task])
            ->set('recoveryReason', 'Not authorized to recover.')
            ->call('recoverInterruptedApproval', $task->lock_version)->assertNotFound();
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\Outsourcing\TaskShow::class, ['task' => $task])
            ->call('recoverInterruptedApproval', $task->lock_version)->assertHasErrors('recoveryReason')
            ->set('recoveryReason', 'Recover the interrupted approval.')
            ->call('recoverInterruptedApproval', $task->lock_version + 1)->assertHasErrors('recovery');
        $this->assertSame(ProductCompletionSubmissionStatus::APPROVED, $task->lastSubmission->refresh()->status);
    }

    public function test_recovery_rejects_financial_and_published_work(): void
    {
        [$task] = $this->submittedTask();
        $task->lastSubmission->update(['status' => ProductCompletionSubmissionStatus::APPROVED]);
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        ProductCompletionCommission::factory()->create(['task_id' => $task->id]);
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\Outsourcing\TaskShow::class, ['task' => $task])
            ->set('recoveryReason', 'Must not reset financially processed work.')
            ->call('recoverInterruptedApproval', $task->lock_version)->assertHasErrors('recovery');
        $this->assertSame(ProductCompletionSubmissionStatus::APPROVED, $task->lastSubmission->refresh()->status);
        $task->commission()->delete();
        $task->update(['content_applied_at' => now()]);
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\Outsourcing\TaskShow::class, ['task' => $task])
            ->set('recoveryReason', 'Must not reset already published content.')
            ->call('recoverInterruptedApproval', $task->lock_version)->assertHasErrors('recovery');
    }

    public function test_failed_approval_audit_rolls_back_both_statuses(): void
    {
        [$task, $reviewer] = $this->submittedTask();
        $this->mock(\App\Services\ProductCompletionAuditService::class)
            ->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable.'));
        try {
            app(\App\Services\ProductCompletionApprovalService::class)->approve($reviewer, $task->lastSubmission);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);
        $this->assertSame(ProductCompletionSubmissionStatus::SUBMITTED, $task->lastSubmission->status);
        $this->assertNull($task->approved_at);
        $this->assertFalse($task->commission()->exists());
    }

    public function test_failed_recovery_audit_keeps_original_decision(): void
    {
        [$task] = $this->submittedTask();
        $task->lastSubmission->update(['status' => ProductCompletionSubmissionStatus::APPROVED, 'review_note' => 'Preserve this decision.']);
        $admin = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        $originalVersion = $task->lock_version;
        $this->mock(\App\Services\ProductCompletionAuditService::class)
            ->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable.'));
        try {
            app(\App\Services\ProductCompletionRecoveryService::class)->recover($admin, $task, $originalVersion, 'Recover interrupted approval.');
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }
        $this->assertSame($originalVersion, $task->refresh()->lock_version);
        $this->assertSame(ProductCompletionSubmissionStatus::APPROVED, $task->lastSubmission->status);
        $this->assertSame('Preserve this decision.', $task->lastSubmission->review_note);
    }

    public function test_approval_rejects_inconsistent_submission_without_writes(): void
    {
        [$task, $reviewer] = $this->submittedTask();
        $task->lastSubmission->update(['status' => ProductCompletionSubmissionStatus::APPROVED]);
        Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task])
            ->call('approve')->assertHasErrors('submission');
        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);
        $this->assertNull($task->approved_at);
    }

    public function test_approval_preserves_raw_legacy_commission_snapshot(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        [$task, $reviewer] = $this->submittedTask();
        $raw = json_encode([...$task->initial_snapshot_json, 'seo_title' => 'Legacy excluded field'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        DB::table('product_completion_tasks')->where('id', $task->id)->update(['commission_content_snapshot_json' => $raw]);
        $task->refresh();
        $original = $task->getRawOriginal('commission_content_snapshot_json');

        app(\App\Services\ProductCompletionApprovalService::class)->approve($reviewer, $task->lastSubmission);

        $this->assertSame(ProductCompletionTaskStatus::APPROVED, $task->refresh()->status);
        $this->assertSame($original, $task->getRawOriginal('commission_content_snapshot_json'));
        $this->assertSame(ProductCompletionSubmissionStatus::APPROVED, $task->lastSubmission->status);
    }

    private function submittedTask(): array
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $outsourcer = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($outsourcer)->create([
            'scope_json' => ['short_description' => true],
            'reviewer_id' => $reviewer->id,
            'current_draft_json' => ['schema_version' => 1, 'short_description' => 'Submitted description.'],
        ]);
        $task->update([
            'initial_snapshot_json' => app(ProductCompletionSnapshotService::class)->build($task->product, $task->scope_json),
            'product_version_at_assignment' => $task->product->content_version,
        ]);
        app(ProductCompletionSubmissionService::class)->submit($outsourcer, $task, 0, 'original-'.$task->public_id);

        return [$task->refresh(), $reviewer, $outsourcer];
    }
}
