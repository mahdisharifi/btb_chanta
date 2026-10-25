<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionMediaStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionSubmissionMedia;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionApprovalService;
use App\Services\ProductCompletionCommissionService;
use App\Services\ProductCompletionMediaService;
use App\Services\ProductCompletionReviewService;
use App\Services\ProductCompletionSnapshotService;
use App\Services\ProductCompletionSubmissionWorkflow;
use App\Services\ProductImageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCompletionReviewedMediaFinalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_reviewed_media_finalizes_idempotently_and_prices_original_payload(): void
    {
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');
        [$task, $submission, $reviewer, $baseline, $acceptedAdd, $rejectedAdd] = $this->fixture();
        $originalPayload = $submission->payload_json;
        $originalHash = $submission->payload_hash;
        $originalRows = $submission->mediaItems()->orderBy('id')->get()->map->getAttributes()->all();

        $review = app(ProductCompletionReviewService::class);
        $editor = $review->initialize($submission);
        foreach ($submission->mediaItems as $row) {
            $editor['decisions']['media:'.$row->id] = $row->media_id === $rejectedAdd->id ? 'reject' : 'approve';
        }
        $saved = $review->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        $preview = app(\App\Services\ProductCompletionCommissionCalculator::class)->calculate($task, $saved);

        app(ProductCompletionApprovalService::class)->approve($reviewer, $saved, expectedLockVersion: $task->refresh()->lock_version);
        $task->refresh();
        app(ProductCompletionApprovalService::class)->applyApprovedContent($task);
        $media = app(ProductCompletionMediaService::class);
        $this->mock(ProductImageService::class, function ($mock): void {
            $mock->shouldReceive('process')->never();
        });
        $media->prepareApprovedMedia($task->refresh());
        $media->publishApprovedMedia($task->refresh());
        $commission = app(ProductCompletionCommissionService::class)->calculate($task->refresh());
        $media->prepareApprovedMedia($task->refresh());
        $media->publishApprovedMedia($task->refresh());
        $sameCommission = app(ProductCompletionCommissionService::class)->calculate($task->refresh());

        $galleries = $task->product->galleries()->where('file_type', 'image')->orderBy('sort_order')->get();
        $this->assertCount(2, $galleries);
        $this->assertSame([1, 2], $galleries->pluck('sort_order')->all());
        $this->assertTrue((bool) $galleries->firstWhere('id', $baseline->id)->is_primary);
        $this->assertSame(ProductCompletionMediaStatus::PUBLISHED, $acceptedAdd->refresh()->status);
        $this->assertSame(ProductCompletionMediaStatus::UNDER_REVIEW, $rejectedAdd->refresh()->status);
        $this->assertSame($preview['total_amount'], $commission->total_amount);
        $this->assertSame($commission->id, $sameCommission->id);
        $this->assertSame($originalPayload, $submission->refresh()->payload_json);
        $this->assertSame($originalHash, $submission->payload_hash);
        $this->assertSame($originalRows, $submission->mediaItems()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(ProductCompletionTaskStatus::COMPLETED, $task->refresh()->status);
    }

    private function fixture(): array
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $contractor = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($contractor)->create([
            'reviewer_id' => $reviewer->id,
            'scope_json' => ['gallery' => ['add' => true, 'reorder' => true, 'propose_removal' => true]],
        ]);
        $baseline = $task->product->galleries()->create([
            'file_path' => 'products/base.webp', 'file_name' => 'base.webp', 'file_type' => 'image',
            'sort_order' => 1, 'is_primary' => true, 'is_seo_image' => true,
        ]);
        $acceptedAdd = ProductCompletionMedia::factory()->create(['task_id' => $task->id, 'status' => ProductCompletionMediaStatus::ACCEPTED, 'prepared_path' => 'prepared/a.webp', 'final_path' => 'products/gallery/a.webp']);
        $rejectedAdd = ProductCompletionMedia::factory()->create(['task_id' => $task->id, 'status' => ProductCompletionMediaStatus::UNDER_REVIEW]);
        $task->update(['initial_snapshot_json' => app(ProductCompletionSnapshotService::class)->build($task->product->refresh(), $task->scope_json)]);
        $payload = ['schema_version' => 1, 'gallery' => ['items' => [
            ['product_gallery_id' => $baseline->id, 'action' => 'remove', 'sort_order' => null, 'is_primary' => false],
            ['media_public_id' => $acceptedAdd->public_id, 'action' => 'add', 'sort_order' => 2, 'is_primary' => false],
            ['media_public_id' => $rejectedAdd->public_id, 'action' => 'add', 'sort_order' => 3, 'is_primary' => false],
        ]]];
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->id, 'submitted_by' => $contractor->id, 'payload_json' => $payload,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
        $task->update(['last_submission_id' => $submission->id]);
        ProductCompletionSubmissionMedia::query()->createMany([
            ['submission_id' => $submission->id, 'media_id' => null, 'product_gallery_id' => $baseline->id, 'action' => 'remove', 'sort_order' => null, 'is_primary' => false, 'is_seo_image' => true, 'review_status' => 'pending'],
            ['submission_id' => $submission->id, 'media_id' => $acceptedAdd->id, 'product_gallery_id' => null, 'action' => 'add', 'sort_order' => 2, 'is_primary' => false, 'is_seo_image' => false, 'review_status' => 'pending'],
            ['submission_id' => $submission->id, 'media_id' => $rejectedAdd->id, 'product_gallery_id' => null, 'action' => 'add', 'sort_order' => 3, 'is_primary' => false, 'is_seo_image' => false, 'review_status' => 'pending'],
        ]);
        app(ProductCompletionSubmissionWorkflow::class)->beginReview($reviewer, $task->refresh(), $submission->id);

        return [$task->refresh(), $submission->refresh(), $reviewer, $baseline, $acceptedAdd, $rejectedAdd];
    }
}
