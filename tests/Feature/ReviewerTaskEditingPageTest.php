<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Livewire\Reviewer\Task\Review;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionApprovalService;
use App\Services\ProductCompletionSnapshotService;
use App\Services\ProductCompletionSubmissionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewerTaskEditingPageTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Reviewer page tests require the isolated test database.');
        }
        $connection = $app['db']->connection();
        if ($connection->selectOne('SELECT DATABASE() AS current_database', [], false)->current_database !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Resolved connection must use the isolated test database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_review_editor_preserves_contractor_submission_and_publishes_reviewer_text(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        [$task, $reviewer] = $this->submittedTask();
        $originalPayload = $task->lastSubmission->getRawOriginal('payload_json');
        $originalHash = $task->lastSubmission->payload_hash;

        Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task])
            ->call('beginReview')->assertHasNoErrors()
            ->call('toggleReviewEditor')->assertHasNoErrors()
            ->set('reviewPayload.short_description', 'Reviewer corrected description.')
            ->call('saveReviewEdits')->assertHasNoErrors()
            ->call('approve')->assertHasNoErrors()
            ->assertRedirect(route('reviewer.outsourcing.tasks.index'));

        $task->refresh();
        $this->assertSame(ProductCompletionTaskStatus::APPROVED, $task->status);
        $this->assertSame($originalPayload, $task->lastSubmission->getRawOriginal('payload_json'));
        $this->assertSame($originalHash, $task->lastSubmission->payload_hash);
        $this->assertSame('Submitted contractor description.', $task->lastSubmission->pricingPayload()['short_description']);
        $product = app(ProductCompletionApprovalService::class)->applyApprovedContent($task);
        $this->assertSame('Reviewer corrected description.', $product->short_description);
    }

    public function test_stale_browser_tab_cannot_approve_another_tabs_review(): void
    {
        [$task, $reviewer] = $this->submittedTask();
        $first = Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task])->call('beginReview');
        $second = Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task->refresh()]);

        $first->call('toggleReviewEditor')
            ->set('reviewPayload.short_description', 'Current reviewed description.')
            ->call('saveReviewEdits')->assertHasNoErrors();
        $second->call('approve')->assertHasErrors();

        $this->assertSame(ProductCompletionTaskStatus::SUBMITTED, $task->refresh()->status);
        $this->assertNull($task->approved_at);
    }

    public function test_exhausted_revisions_require_explicit_consent_in_the_page(): void
    {
        [$task, $reviewer] = $this->submittedTask();
        $task->update(['revision_count' => 3, 'revision_limit' => 3]);
        $component = Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task])
            ->call('beginReview')
            ->set('revisionReason', 'Please correct the description once more.')
            ->call('requestRevision')->assertHasErrors('revision_limit');
        $this->assertSame(3, $task->refresh()->revision_count);

        $component->set('extendRevisionAllowance', true)
            ->call('requestRevision')->assertHasNoErrors()
            ->assertRedirect(route('reviewer.outsourcing.tasks.index'));
        $this->assertSame(4, $task->refresh()->revision_limit);
        $this->assertSame(4, $task->revision_count);
        $this->assertSame(ProductCompletionTaskStatus::CHANGES_REQUESTED, $task->status);
    }

    private function submittedTask(): array
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $contractor = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($contractor)->create([
            'scope_json' => ['short_description' => true],
            'reviewer_id' => $reviewer->id,
            'current_draft_json' => ['schema_version' => 1, 'short_description' => 'Submitted contractor description.'],
        ]);
        $snapshot = app(ProductCompletionSnapshotService::class)->build($task->product, $task->scope_json);
        $task->update([
            'initial_snapshot_json' => $snapshot,
            'product_version_at_assignment' => $task->product->content_version,
        ]);
        app(ProductCompletionSubmissionService::class)->submit($contractor, $task, 0, 'page-'.$task->public_id);

        return [$task->refresh(), $reviewer];
    }
}
