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
