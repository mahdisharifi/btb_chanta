<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Jobs\FinalizeProductCompletionJob;
use App\Livewire\Admin\Outsourcing\TaskShow as AdminTaskShow;
use App\Livewire\Outsourcer\Task\Workspace;
use App\Livewire\Reviewer\Task\Review;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionConflictService;
use App\Services\ProductCompletionFinalizationService;
use App\Services\ProductCompletionMediaService;
use App\Services\ProductCompletionSnapshotService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCompletionConflictRevisionEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();
        if (! $app->environment('testing')
            || $connection->getConfig('driver') !== 'mysql'
            || $connection->getDatabaseName() !== 'b2b_marketplace_test') {
            throw new \RuntimeException('End-to-end workflow tests require the isolated test database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_conflicted_task_can_return_to_contractor_be_reviewed_and_finalize_on_isolated_copy(): void
    {
        $admin = User::factory()->create()->assignRole(UserRole::SUPER_ADMIN->value);
        $contractor = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $attribute = Attribute::factory()->create();
        $baselineValue = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'Baseline specification']);
        $currentValue = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'Current admin specification']);

        $task = ProductCompletionTask::factory()->inProgress($contractor)->create([
            'reviewer_id' => $reviewer->id,
            'scope_json' => [
                'title' => true,
                'short_description' => true,
                'attributes' => true,
                'gallery' => ['add' => true, 'reorder' => true, 'propose_removal' => true],
            ],
        ]);
        $product = $task->product;
        $product->category->attributes()->attach($attribute->id);
        $product->attributeValues()->sync([$baselineValue->id]);
        $gallery = $product->galleries()->create([
            'file_path' => 'products/e2e-primary.webp',
            'file_name' => 'e2e-primary.webp',
            'file_type' => 'image',
            'sort_order' => 1,
            'is_primary' => true,
        ]);
        $secondaryGallery = $product->galleries()->create([
            'file_path' => 'products/e2e-secondary.webp',
            'file_name' => 'e2e-secondary.webp',
            'file_type' => 'image',
            'sort_order' => 2,
            'is_primary' => false,
        ]);
        $product->forceFill([
            'title' => 'E2E baseline product',
            'short_description' => 'E2E baseline description.',
        ])->save();

        $snapshotService = app(ProductCompletionSnapshotService::class);
        $task->forceFill([
            'initial_snapshot_json' => $snapshotService->build($product->refresh(), $task->scope_json),
            'product_version_at_assignment' => $product->content_version,
        ])->save();

        // Simulate the product being edited after the task was published.
        $product->attributeValues()->sync([$currentValue->id]);
        $product->forceFill([
            'title' => 'Current admin product title',
            'short_description' => 'Current admin description.',
            'content_version' => $product->content_version + 1,
        ])->save();

        $payload = [
            'schema_version' => 1,
            'title' => 'Contractor original title',
            'short_description' => 'Contractor original description.',
            'attributes' => [[
                'attribute_id' => $attribute->id,
                'value_ids' => [],
                'proposed_values' => ['Contractor original specification'],
            ]],
            'gallery' => ['items' => [
                [
                    'product_gallery_id' => $gallery->id,
                    'action' => 'keep',
                    'sort_order' => 1,
                    'is_primary' => true,
                ],
                [
                    'product_gallery_id' => $secondaryGallery->id,
                    'action' => 'keep',
                    'sort_order' => 2,
                    'is_primary' => false,
                ],
            ]],
        ];
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->id,
            'submitted_by' => $contractor->id,
            'payload_json' => $payload,
            'media_manifest_json' => $payload['gallery'],
            'status' => ProductCompletionSubmissionStatus::CONFLICTED,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
        app(ProductCompletionMediaService::class)->freezeManifest($submission, $payload['gallery']);
        $conflict = app(ProductCompletionConflictService::class)->detect($task, $product->refresh(), $submission->publicationPayload());
        $this->assertTrue($conflict->hasConflicts());
        $task->forceFill([
            'status' => ProductCompletionTaskStatus::CONFLICTED,
            'last_submission_id' => $submission->id,
            'conflict_json' => $conflict->conflicts,
        ])->save();

        // Reviewer and contractor may work on the task but cannot resolve its conflict.
        $beforeUnauthorizedAttempt = [
            'status' => $task->status->value,
            'revision_count' => $task->revision_count,
            'lock_version' => $task->lock_version,
            'last_submission_id' => $task->last_submission_id,
            'conflict_json' => $task->conflict_json,
            'revision_rows' => $task->revisions()->count(),
            'audit_rows' => $task->audits()->count(),
            'submission_status' => $submission->status->value,
        ];
        foreach ([$reviewer, $contractor] as $unauthorizedActor) {
            try {
                app(\App\Services\ProductCompletionConflictResolutionService::class)->requestRevision(
                    $unauthorizedActor,
                    $task,
                    $task->lock_version,
                    'Unauthorized revision request.',
                );
                $this->fail('A non-admin task participant must be rejected.');
            } catch (AuthorizationException) {
                $freshTask = $task->fresh();
                $this->assertSame($beforeUnauthorizedAttempt, [
                    'status' => $freshTask->status->value,
                    'revision_count' => $freshTask->revision_count,
                    'lock_version' => $freshTask->lock_version,
                    'last_submission_id' => $freshTask->last_submission_id,
                    'conflict_json' => $freshTask->conflict_json,
                    'revision_rows' => $freshTask->revisions()->count(),
                    'audit_rows' => $freshTask->audits()->count(),
                    'submission_status' => $submission->fresh()->status->value,
                ]);
            }
        }

        // 1. Admin requests a correction through the admin Livewire page.
        Livewire::actingAs($admin)->test(AdminTaskShow::class, ['task' => $task->refresh()])
            ->set('conflictResolutionReason', 'Align the submitted specifications with the current product data.')
            ->call('returnConflictForRevision', $task->lock_version)
            ->assertHasNoErrors();
        $task->refresh();
        $this->assertSame(ProductCompletionTaskStatus::CHANGES_REQUESTED, $task->status);
        $this->assertSame(1, $task->revision_count);

        // 2-3. Contractor resumes by identity, edits the draft and submits it to review.
        $workspace = Livewire::actingAs($contractor)->test(Workspace::class, ['task' => $task]);
        $workspace->call('resumeRevision')->assertHasNoErrors();
        $draft = $workspace->get('draft');
        $draft['title'] = 'Contractor revised title';
        $draft['short_description'] = 'Contractor revised description.';
        $draft['attributes'] = [[
            'attribute_id' => $attribute->id,
            'value_ids' => [],
            'proposed_values' => ['Contractor revised specification'],
        ]];
        $workspace->set('draft', $draft)->call('submit')->assertHasNoErrors();
        $task->refresh();
        $submission = $task->lastSubmission;
        $this->assertSame(ProductCompletionTaskStatus::RESUBMITTED, $task->status);
        $this->assertSame(ProductCompletionSubmissionStatus::SUBMITTED, $submission->status);

        // 4-6. Reviewer claims, changes the spec, saves that decision, and approves.
        $reviewPage = Livewire::actingAs($reviewer)->test(Review::class, ['task' => $task]);
        $reviewPage->call('beginReview')->assertHasNoErrors();
        $reviewPage->call('toggleReviewEditor')->assertHasNoErrors();
        $reviewPayload = $reviewPage->get('reviewPayload');
        $reviewPayload['attributes'][0]['proposed_values'] = ['Reviewer approved specification'];
        $reviewDecisions = $reviewPage->get('reviewDecisions');
        $reviewDecisions['attribute:'.$attribute->id] = 'approve';
        $reviewPage->set('reviewPayload', $reviewPayload)
            ->set('reviewDecisions', $reviewDecisions)
            ->call('saveReviewEdits')
            ->assertHasNoErrors();
        $this->assertTrue($reviewPage->get('reviewEditsSaved'));
        $reviewPage->call('approve')->assertHasNoErrors();
        $task->refresh();
        $this->assertSame(ProductCompletionTaskStatus::APPROVED, $task->status);
        $this->assertSame(ProductCompletionSubmissionStatus::APPROVED, $task->lastSubmission->status);

        // 7. Job is asserted, then executed synchronously against the isolated DB copy.
        Queue::assertPushed(FinalizeProductCompletionJob::class);
        (new FinalizeProductCompletionJob($task->id))->handle(app(ProductCompletionFinalizationService::class));
        $task->refresh();
        $product->refresh();
        $this->assertSame('Contractor revised title', $product->title);
        $this->assertSame('Contractor revised description.', $product->short_description);
        $this->assertSame(['Reviewer approved specification'], $product->attributeValues()->pluck('attribute_values.value')->all());
        $this->assertSame(1, $product->galleries()->where('file_type', 'image')->where('is_primary', true)->count());
        $this->assertSame($gallery->id, $product->galleries()->where('is_primary', true)->value('id'));
        $this->assertFalse((bool) $product->galleries()->whereKey($secondaryGallery->id)->value('is_primary'));

        // 8. Final task markers, revision status, and product publication are complete.
        $this->assertSame(ProductCompletionTaskStatus::COMPLETED, $task->status);
        $this->assertNotNull($task->content_applied_at);
        $this->assertNotNull($task->media_published_at);
        $this->assertNull($task->last_error);
        $this->assertSame(ProductCompletionRevisionStatus::RESOLVED, $task->revisions()->sole()->status);
    }
}
