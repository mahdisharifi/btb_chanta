<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Jobs\FinalizeProductCompletionJob;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionAuditService;
use App\Services\ProductCompletionApprovalService;
use App\Services\ProductCompletionMediaService;
use App\Services\ProductCompletionPrimaryImage;
use App\Services\ProductCompletionReviewService;
use App\Services\ProductCompletionSnapshotService;
use App\Services\ProductCompletionSubmissionWorkflow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProductCompletionReviewEditingTest extends TestCase
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

    public function test_text_edits_are_audited_without_changing_contractor_content_or_pricing(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $immutable = $submission->only(['payload_json', 'payload_hash', 'diff_json', 'media_manifest_json']);
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['payload']['short_description'] = 'Reviewer replacement.';
        $version = $task->lock_version;

        $saved = $service->save($reviewer, $submission, $version, $editor['payload'], $editor['decisions']);

        $this->assertSame($immutable, $saved->refresh()->only(array_keys($immutable)));
        $this->assertSame('Reviewer replacement.', $saved->publicationPayload()['short_description']);
        $this->assertSame('Contractor description.', $saved->pricingPayload()['short_description']);
        $this->assertSame(2, $saved->approval_selection_json['schema_version']);
        $this->assertSame('item_review', $saved->approval_selection_json['mode']);
        $this->assertSame($version + 1, $task->refresh()->lock_version);
        $this->assertSame(ProductCompletionSubmissionStatus::UNDER_REVIEW, $saved->status);
        $this->assertFalse($task->commission()->exists());
        $audit = $task->audits()->where('action', 'submission_review_saved')->sole();
        $this->assertSame($immutable['payload_json'], $audit->metadata_json['original_payload']);
        $this->assertSame($saved->approval_selection_json, $audit->after_json);
        $this->assertSame($saved->approval_selection_json['editor_payload'], $service->initialize($saved)['payload']);
        $this->assertSame($reviewer->id, $saved->approval_selection_json['reviewer_id']);
        $this->assertSame($version, $saved->approval_selection_json['source_lock_version']);
        $this->assertSame($saved->payload_hash, $saved->approval_selection_json['source_payload_hash']);
    }

    public function test_rejected_text_restores_baseline_and_does_not_price_the_edit(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['payload']['short_description'] = 'Ignored reviewer edit.';
        $editor['decisions']['text:short_description'] = 'reject';

        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);

        $this->assertSame('Baseline description.', $saved->publicationPayload()['short_description']);
        $this->assertSame('Baseline description.', $saved->pricingPayload()['short_description']);
    }

    public function test_rejected_editor_content_survives_reload_and_can_be_approved_later(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['payload']['short_description'] = 'Preserved reviewer proposal.';
        $editor['decisions']['text:short_description'] = 'reject';
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        $editor = $service->initialize($saved->refresh());
        $this->assertSame('Preserved reviewer proposal.', $editor['payload']['short_description']);
        $editor['decisions']['text:short_description'] = 'approve';
        $saved = $service->save($reviewer, $saved, $task->refresh()->lock_version, $editor['payload'], $editor['decisions']);

        $this->assertSame('Preserved reviewer proposal.', $saved->publicationPayload()['short_description']);
        $this->assertSame('Contractor description.', $saved->pricingPayload()['short_description']);
    }

    public function test_approved_reviewed_text_is_applied_without_mutating_original_submission(): void
    {
        Queue::fake();
        [$task, $submission, $reviewer] = $this->submission();
        $original = $submission->getRawOriginal('payload_json');
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['payload']['short_description'] = 'Published reviewer description.';
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        app(ProductCompletionApprovalService::class)->approve($reviewer, $saved, expectedLockVersion: $task->refresh()->lock_version);
        $this->assertSame(ProductCompletionTaskStatus::APPROVED, $task->refresh()->status);
        Queue::assertPushed(FinalizeProductCompletionJob::class);

        $product = app(ProductCompletionApprovalService::class)->applyApprovedContent($task->refresh());

        $this->assertSame('Published reviewer description.', $product->short_description);
        $this->assertSame($original, $submission->refresh()->getRawOriginal('payload_json'));
    }

    public function test_applying_edited_attribute_a_preserves_rejected_attribute_b(): void
    {
        Queue::fake();
        $first = Attribute::factory()->create();
        $second = Attribute::factory()->create();
        $beforeFirst = AttributeValue::query()->create(['attribute_id' => $first->id, 'value' => 'Original A']);
        $beforeSecond = AttributeValue::query()->create(['attribute_id' => $second->id, 'value' => 'Original B']);
        [$task, $submission, $reviewer] = $this->submission(['attributes' => true], [], function ($task) use ($first, $second, $beforeFirst, $beforeSecond): array {
            $task->product->category->attributes()->attach([$first->id, $second->id]);
            $task->product->attributeValues()->sync([$beforeFirst->id, $beforeSecond->id]);

            return ['attributes' => [
                ['attribute_id' => $first->id, 'value_ids' => [], 'proposed_values' => ['Contractor A']],
                ['attribute_id' => $second->id, 'value_ids' => [], 'proposed_values' => ['Contractor B']],
            ]];
        });
        $original = $submission->payload_json;
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['payload']['attributes'][0]['proposed_values'] = ['Reviewed A'];
        $editor['decisions']['attribute:'.$second->id] = 'reject';
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        app(ProductCompletionApprovalService::class)->approve($reviewer, $saved, expectedLockVersion: $task->refresh()->lock_version);
        $this->assertSame(ProductCompletionTaskStatus::APPROVED, $task->refresh()->status);
        Queue::assertPushed(FinalizeProductCompletionJob::class);

        $product = app(ProductCompletionApprovalService::class)->applyApprovedContent($task->refresh());

        $this->assertSame(['Original B', 'Reviewed A'], $product->attributeValues()->orderBy('attribute_values.value')->pluck('attribute_values.value')->all());
        $this->assertSame($original, $submission->refresh()->payload_json);
    }

    public function test_attribute_rejection_preserves_baseline_and_reviewer_additions_are_not_priced(): void
    {
        $single = Attribute::factory()->create();
        $multiple = Attribute::factory()->multiple()->create();
        $added = Attribute::factory()->create();
        $before = AttributeValue::query()->create(['attribute_id' => $single->id, 'value' => 'Before']);
        $after = AttributeValue::query()->create(['attribute_id' => $single->id, 'value' => 'After']);
        [$task, $submission, $reviewer] = $this->submission(['attributes' => true], [], function ($task) use ($single, $multiple, $added, $before, $after): array {
            $task->product->category->attributes()->attach([$single->id, $multiple->id, $added->id]);
            $task->product->attributeValues()->sync([$before->id]);

            return ['attributes' => [
                ['attribute_id' => $single->id, 'value_ids' => [$after->id], 'proposed_values' => []],
                ['attribute_id' => $multiple->id, 'value_ids' => [], 'proposed_values' => ['Contractor value']],
            ]];
        });
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['decisions']['attribute:'.$single->id] = 'reject';
        $editor['payload']['attributes'][1]['proposed_values'] = ['Reviewer value', 'Another value'];
        $editor['payload']['attributes'][] = ['attribute_id' => $added->id, 'value_ids' => [], 'proposed_values' => ['Reviewer only']];

        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);

        $published = collect($saved->publicationPayload()['attributes'])->keyBy('attribute_id');
        $pricing = collect($saved->pricingPayload()['attributes'])->keyBy('attribute_id');
        $this->assertSame([$before->id], $published[$single->id]['value_ids']);
        $this->assertSame([$before->id], $pricing[$single->id]['value_ids']);
        $this->assertSame(['Reviewer value', 'Another value'], $published[$multiple->id]['proposed_values']);
        $this->assertSame(['Contractor value'], $pricing[$multiple->id]['proposed_values']);
        $this->assertTrue($published->has($added->id));
        $this->assertFalse($pricing->has($added->id));
        $this->assertSame('approve', $saved->approval_selection_json['decisions']['attribute:'.$added->id]);
        $this->assertFalse(AttributeValue::query()->where('value', 'Reviewer only')->exists());
        $this->assertSame([$before->id], $task->product->attributeValues()->pluck('attribute_values.id')->all());
    }

    public function test_optional_attribute_deletion_is_explicit_and_does_not_rewrite_original_pricing(): void
    {
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::query()->create(['attribute_id' => $attribute->id, 'value' => 'Original']);
        [$task, $submission, $reviewer] = $this->submission(['attributes' => true], [], function ($task) use ($attribute, $value): array {
            $task->product->category->attributes()->attach($attribute->id);
            $task->product->attributeValues()->sync([$value->id]);

            return ['attributes' => [['attribute_id' => $attribute->id, 'value_ids' => [$value->id], 'proposed_values' => []]]];
        });
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['payload']['attributes'] = [];
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);

        $this->assertSame([], $saved->publicationPayload()['attributes']);
        $this->assertSame([$value->id], $saved->pricingPayload()['attributes'][0]['value_ids']);
    }

    public function test_required_final_attribute_cannot_be_deleted(): void
    {
        $attribute = Attribute::factory()->create();
        [$task, $submission, $reviewer] = $this->submission(['attributes' => true], [], function ($task) use ($attribute): array {
            $task->product->category->attributes()->attach($attribute->id, ['is_required' => true]);

            return ['attributes' => [['attribute_id' => $attribute->id, 'value_ids' => [], 'proposed_values' => ['Required value']]]];
        });
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        $editor['payload']['attributes'] = [];

        $this->assertSaveInvalid($task, $submission, $reviewer, $editor, 'attributes.required');
    }

    public function test_foreign_attribute_values_are_rejected_without_publishing_values(): void
    {
        $allowed = Attribute::factory()->create();
        $foreign = AttributeValue::query()->create(['attribute_id' => Attribute::factory()->create()->id, 'value' => 'Foreign']);
        [$task, $submission, $reviewer] = $this->submission(['attributes' => true], ['attributes' => []], function ($task) use ($allowed): array {
            $task->product->category->attributes()->attach($allowed->id);

            return [];
        });
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        $editor['payload']['attributes'][] = ['attribute_id' => $allowed->id, 'value_ids' => [$foreign->id], 'proposed_values' => []];
        $editor['decisions']['attribute:'.$allowed->id] = 'approve';

        $this->assertSaveInvalid($task, $submission, $reviewer, $editor, 'attributes.0.value_ids');
    }

    public function test_rejected_gallery_add_and_removal_produce_frozen_row_overlays(): void
    {
        [$task, $submission, $reviewer] = $this->gallerySubmission();
        $rows = $submission->mediaItems()->orderBy('id')->get();
        $frozen = $rows->map->getAttributes()->all();
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        $editor['decisions']['media:'.$rows[0]->id] = 'reject';
        $editor['decisions']['media:'.$rows[1]->id] = 'reject';

        $saved = app(ProductCompletionReviewService::class)->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);

        $overlays = collect($saved->approval_selection_json['media_items'])->keyBy('id');
        $this->assertFalse($overlays->has($rows[1]->id));
        $this->assertSame('keep', $overlays[$rows[0]->id]['action']);
        $this->assertTrue($overlays[$rows[0]->id]['is_primary']);
        $this->assertTrue($overlays[$rows[0]->id]['is_seo_image']);
        $this->assertSame([1, 2], $overlays->pluck('sort_order')->all());
        $this->assertSame($frozen, $submission->mediaItems()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($saved->publicationPayload()['gallery'], $saved->pricingPayload()['gallery']);
        $this->assertSame($rows[0]->product_gallery_id, $saved->publicationPayload()['gallery']['items'][0]['product_gallery_id']);
        $this->assertSame($rows[2]->media->public_id, $saved->publicationPayload()['gallery']['items'][1]['media_public_id']);
        foreach ($overlays as $overlay) {
            $this->assertSame(['id', 'action', 'sort_order', 'is_primary', 'is_seo_image', 'title', 'alt_text'], array_keys($overlay));
        }
    }

    public function test_gallery_editor_cannot_inject_a_reference_or_change_an_action(): void
    {
        [$task, $submission, $reviewer] = $this->gallerySubmission();
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        $editor['payload']['gallery']['items'][0]['action'] = 'keep';

        $this->assertSaveInvalid($task, $submission, $reviewer, $editor, 'gallery');
    }

    public function test_stale_version_and_unknown_decision_are_rejected(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $version = $task->lock_version;
        $service->save($reviewer, $submission, $version, $editor['payload'], $editor['decisions']);
        try {
            $service->save($reviewer, $submission, $version, $editor['payload'], $editor['decisions']);
            $this->fail('Expected a stale review error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lock_version', $exception->errors());
        }
        $editor['decisions']['media:999999'] = 'approve';
        $this->assertSaveInvalid($task->refresh(), $submission->refresh(), $reviewer, $editor, 'decisions');
    }

    public function test_unclaimed_or_noncurrent_reviewer_cannot_edit(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        $other = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        try {
            app(ProductCompletionReviewService::class)->save($other, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
            $this->fail('Expected reviewer ownership to be enforced.');
        } catch (AuthorizationException|HttpException $exception) {
            $this->assertNull($submission->refresh()->approval_selection_json);
        }
        $submission->update(['status' => ProductCompletionSubmissionStatus::SUBMITTED]);
        $this->assertSaveInvalid($task, $submission, $reviewer, $editor, 'submission');
    }

    public function test_a_reviewer_must_claim_an_open_review_before_editing(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        $task->update(['reviewer_id' => null]);
        $version = $task->lock_version;
        $this->expectException(HttpException::class);

        try {
            app(ProductCompletionReviewService::class)->save($reviewer, $submission, $version, $editor['payload'], $editor['decisions']);
        } finally {
            $this->assertNull($submission->refresh()->approval_selection_json);
            $this->assertSame($version, $task->refresh()->lock_version);
        }
    }

    public function test_an_old_submission_cannot_be_edited_after_a_new_submission_arrives(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        $newer = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->id, 'submitted_by' => $submission->submitted_by,
            'version_no' => $submission->version_no + 1,
        ]);
        $task->update(['last_submission_id' => $newer->id]);
        $version = $task->lock_version;
        $this->expectException(AuthorizationException::class);

        try {
            app(ProductCompletionReviewService::class)->save($reviewer, $submission, $version, $editor['payload'], $editor['decisions']);
        } finally {
            $this->assertNull($submission->refresh()->approval_selection_json);
            $this->assertSame($version, $task->refresh()->lock_version);
        }
    }

    public function test_failed_audit_rolls_back_snapshot_and_version(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        $version = $task->lock_version;
        $this->mock(ProductCompletionAuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable.'));
        try {
            app(ProductCompletionReviewService::class)->save($reviewer, $submission, $version, $editor['payload'], $editor['decisions']);
            $this->fail('Expected an audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }
        $this->assertNull($submission->refresh()->approval_selection_json);
        $this->assertSame($version, $task->refresh()->lock_version);
    }

    public function test_snapshot_from_a_different_source_payload_is_rejected(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        $snapshot = $saved->approval_selection_json;
        $snapshot['source_payload_hash'] = str_repeat('0', 64);
        $saved->approval_selection_json = $snapshot;

        $this->expectException(ValidationException::class);
        $saved->publicationPayload();
    }

    public function test_signed_snapshot_cannot_be_replayed_on_another_submission_with_the_same_payload_hash(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        $other = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->id,
            'submitted_by' => $submission->submitted_by,
            'version_no' => $submission->version_no + 1,
            'payload_json' => $submission->payload_json,
            'payload_hash' => $submission->payload_hash,
            'approval_selection_json' => $saved->approval_selection_json,
        ]);
        $this->assertSame($saved->payload_hash, $other->payload_hash);
        $this->assertSame($task->id, $saved->approval_selection_json['source_task_id']);
        $this->assertSame($saved->id, $saved->approval_selection_json['source_submission_id']);
        foreach (['publicationPayload', 'pricingPayload'] as $helper) {
            try {
                $other->{$helper}();
                $this->fail('A signed review must belong to its exact submission.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('review', $exception->errors());
            }
        }
    }

    public function test_persisted_projection_and_provenance_tampering_fail_for_both_helpers(): void
    {
        [$task, $submission, $reviewer] = $this->submission(
            ['title' => true, 'short_description' => true],
            ['title' => 'Contractor title', 'short_description' => 'Contractor description.'],
        );
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        $signed = $saved->approval_selection_json;
        foreach ([
            'reviewed_payload.title' => 'Corrupted publication title',
            'pricing_payload.title' => 'Corrupted pricing title',
            'editor_payload.title' => 'Corrupted editor title',
            'decisions.text:title' => 'reject',
            'product_version' => $signed['product_version'] + 1,
            'signature' => str_repeat('0', 64),
        ] as $path => $value) {
            $snapshot = $signed;
            data_set($snapshot, $path, $value);
            $saved->forceFill(['approval_selection_json' => $snapshot])->save();
            $saved->refresh();
            $this->assertSame($signed['source_payload_hash'], $saved->approval_selection_json['source_payload_hash']);
            foreach (['publicationPayload', 'pricingPayload'] as $helper) {
                try {
                    $saved->{$helper}();
                    $this->fail('Persisted review tampering must be rejected.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('review', $exception->errors());
                }
            }
        }
        unset($signed['signature']);
        $saved->forceFill(['approval_selection_json' => $signed])->save();
        $this->expectException(ValidationException::class);
        $saved->refresh()->publicationPayload();
    }

    public function test_persisted_gallery_and_overlay_tampering_are_rejected(): void
    {
        [$task, $submission, $reviewer] = $this->gallerySubmission();
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        $signed = $saved->approval_selection_json;
        foreach (['reviewed_payload.gallery.items.0.title', 'pricing_payload.gallery.items.0.title', 'media_items.0.title'] as $path) {
            $snapshot = $signed;
            data_set($snapshot, $path, 'Corrupted gallery title');
            $saved->forceFill(['approval_selection_json' => $snapshot])->save();
            foreach (['publicationPayload', 'pricingPayload'] as $helper) {
                try {
                    $saved->refresh()->{$helper}();
                    $this->fail('Gallery tampering must be rejected.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('review', $exception->errors());
                }
            }
        }
    }

    public function test_signatures_ignore_object_key_order_and_require_an_application_key(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        $snapshot = $saved->approval_selection_json;
        $reordered = array_reverse($snapshot, true);
        $reordered['reviewed_payload'] = array_reverse($reordered['reviewed_payload'], true);
        $this->assertSame($snapshot['signature'], ProductCompletionSubmission::signReviewSnapshot($reordered)['signature']);
        $saved->approval_selection_json = $reordered;
        $this->assertEquals($snapshot['reviewed_payload'], $saved->publicationPayload());
        config(['app.key' => '']);
        $this->expectException(ValidationException::class);
        $saved->pricingPayload();
    }

    public function test_key_rotation_preserves_snapshots_only_while_the_previous_key_is_configured(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        $snapshot = $saved->approval_selection_json;
        $previousKey = config('app.key');
        config(['app.key' => 'review-rotation-current-test-key', 'app.previous_keys' => [$previousKey]]);

        $this->assertSame($snapshot['reviewed_payload'], $saved->publicationPayload());
        $this->assertSame($snapshot['pricing_payload'], $saved->pricingPayload());
        $resigned = ProductCompletionSubmission::signReviewSnapshot($snapshot);
        $this->assertNotSame($snapshot['signature'], $resigned['signature']);
        config(['app.previous_keys' => []]);
        $saved->approval_selection_json = $resigned;
        $this->assertSame($snapshot['reviewed_payload'], $saved->publicationPayload());
        $saved->approval_selection_json = $snapshot;
        foreach (['publicationPayload', 'pricingPayload'] as $helper) {
            try {
                $saved->{$helper}();
                $this->fail('Removing the previous key must invalidate snapshots signed with it.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('review', $exception->errors());
            }
        }
    }

    public function test_rejected_text_can_retain_empty_baseline_while_another_field_is_approved(): void
    {
        [$task, $submission, $reviewer] = $this->submission(
            ['title' => true, 'short_description' => true],
            ['title' => 'Contractor title', 'short_description' => 'Contractor description.'],
            function ($task): array {
                $task->product->update(['short_description' => null]);

                return [];
            },
        );
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['payload']['title'] = 'Reviewed title';
        $editor['decisions']['text:short_description'] = 'reject';
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);
        $actual = $service->validatePublication($task, $saved, $saved->publicationPayload(), $reviewer);

        $this->assertNull($actual['short_description']);
        $this->assertSame('Reviewed title', $actual['title']);
        $this->assertSame('Contractor description.', $saved->approval_selection_json['editor_payload']['short_description']);
        $this->assertNull($saved->pricingPayload()['short_description']);
    }

    public function test_rejected_required_attribute_can_retain_an_empty_baseline(): void
    {
        $attribute = Attribute::factory()->create();
        [$task, $submission, $reviewer] = $this->submission(['attributes' => true], [], function ($task) use ($attribute): array {
            $task->product->category->attributes()->attach($attribute->id, ['is_required' => true]);

            return ['attributes' => [['attribute_id' => $attribute->id, 'value_ids' => [], 'proposed_values' => ['Contractor proposal']]]];
        });
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['decisions']['attribute:'.$attribute->id] = 'reject';
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);

        $this->assertSame([], $saved->publicationPayload()['attributes']);
        $this->assertSame([], $service->validatePublication($task, $saved, $saved->publicationPayload(), $reviewer)['attributes']);
        $this->assertSame(['Contractor proposal'], $saved->approval_selection_json['editor_payload']['attributes'][0]['proposed_values']);
    }

    public function test_accepted_required_text_cannot_be_cleared(): void
    {
        [$task, $submission, $reviewer] = $this->submission();
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        $editor['payload']['short_description'] = null;

        $this->assertSaveInvalid($task, $submission, $reviewer, $editor, 'short_description');
    }

    public function test_rejected_text_preserves_baseline_markup_and_whitespace(): void
    {
        $baseline = "  <p>Existing description.</p>\n";
        [$task, $submission, $reviewer] = $this->submission(
            ['short_description' => true],
            ['short_description' => 'Contractor proposal.'],
            function ($task) use ($baseline): array {
                $task->product->update(['short_description' => $baseline]);

                return [];
            },
        );
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['decisions']['text:short_description'] = 'reject';
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);

        $this->assertSame($baseline, $saved->publicationPayload()['short_description']);
        $this->assertSame($baseline, $service->validatePublication($task, $saved, $saved->publicationPayload(), $reviewer)['short_description']);
    }

    public function test_all_additions_can_be_rejected_when_the_original_gallery_was_empty(): void
    {
        [$task, $submission, $reviewer] = $this->submission(
            ['gallery' => ['add' => true, 'reorder' => true, 'propose_removal' => true]],
            [],
            function ($task): array {
                $media = ProductCompletionMedia::factory()->create(['task_id' => $task->id]);

                return ['gallery' => ['items' => [
                    ['media_public_id' => $media->public_id, 'action' => 'add', 'sort_order' => 1, 'is_primary' => true],
                ]]];
            },
        );
        $service = app(ProductCompletionReviewService::class);
        $editor = $service->initialize($submission);
        $editor['decisions']['media:'.$submission->mediaItems()->sole()->id] = 'reject';
        $saved = $service->save($reviewer, $submission, $task->lock_version, $editor['payload'], $editor['decisions']);

        $this->assertSame(['items' => []], $saved->publicationPayload()['gallery']);
        $this->assertSame(['items' => []], $saved->pricingPayload()['gallery']);
        $this->assertSame([], $saved->approval_selection_json['media_items']);
    }

    public function test_an_approved_removal_cannot_empty_an_existing_gallery(): void
    {
        [$task, $submission, $reviewer] = $this->gallerySubmission();
        $editor = app(ProductCompletionReviewService::class)->initialize($submission);
        foreach ($submission->mediaItems()->where('action', 'add')->get() as $row) {
            $editor['decisions']['media:'.$row->id] = 'reject';
        }

        $this->assertSaveInvalid($task, $submission, $reviewer, $editor, 'gallery');
    }

    public function test_legacy_helpers_fall_back_and_malformed_v2_fails_closed(): void
    {
        [, $submission] = $this->submission();
        $this->assertSame($submission->payload_json, $submission->publicationPayload());
        $submission->approval_selection_json = ['mode' => 'selective', 'approved_fields' => ['short_description']];
        $this->assertSame($submission->payload_json, $submission->pricingPayload());
        foreach ([['schema_version' => 2, 'mode' => 'item_review'], ['mode' => 'item_review'], ['schema_version' => 3]] as $snapshot) {
            $submission->approval_selection_json = $snapshot;
            foreach (['publicationPayload', 'pricingPayload'] as $helper) {
                try {
                    $submission->{$helper}();
                    $this->fail('Malformed snapshots must not fall back to contractor content.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('review', $exception->errors());
                }
            }
        }
    }

    private function submission(array $scope = ['short_description' => true], array $payload = ['short_description' => 'Contractor description.'], ?\Closure $prepare = null): array
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $contractor = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $task = ProductCompletionTask::factory()->inProgress($contractor)->create(['scope_json' => $scope, 'reviewer_id' => $reviewer->id]);
        $task->product->update(['short_description' => 'Baseline description.']);
        $payload = array_replace($payload, $prepare ? $prepare($task) : []);
        $task->update([
            'initial_snapshot_json' => app(ProductCompletionSnapshotService::class)->build($task->product->refresh(), $scope),
            'status' => ProductCompletionTaskStatus::SUBMITTED,
        ]);
        if (isset($payload['gallery'])) {
            $payload['gallery'] = app(ProductCompletionPrimaryImage::class)->apply($task, $payload['gallery']);
        }
        $payload = ['schema_version' => 1, ...$payload];
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->id, 'submitted_by' => $contractor->id, 'payload_json' => $payload,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'media_manifest_json' => $payload['gallery'] ?? null,
        ]);
        $task->update(['last_submission_id' => $submission->id]);
        if (isset($payload['gallery'])) {
            app(ProductCompletionMediaService::class)->freezeManifest($submission, $payload['gallery']);
        }
        app(ProductCompletionSubmissionWorkflow::class)->beginReview($reviewer, $task, $submission->id);

        return [$task->refresh(), $submission->refresh(), $reviewer];
    }

    private function gallerySubmission(): array
    {
        return $this->submission(['gallery' => ['add' => true, 'reorder' => true, 'propose_removal' => true]], [], function ($task): array {
            $gallery = $task->product->galleries()->create([
                'file_path' => 'products/existing.png', 'file_name' => 'existing.png', 'file_type' => 'image',
                'sort_order' => 1, 'is_primary' => true, 'is_seo_image' => true,
            ]);
            $first = ProductCompletionMedia::factory()->create(['task_id' => $task->id]);
            $second = ProductCompletionMedia::factory()->create(['task_id' => $task->id]);

            return ['gallery' => ['items' => [
                ['product_gallery_id' => $gallery->id, 'action' => 'remove', 'sort_order' => null, 'is_primary' => false],
                ['media_public_id' => $first->public_id, 'action' => 'add', 'sort_order' => 1, 'is_primary' => true],
                ['media_public_id' => $second->public_id, 'action' => 'add', 'sort_order' => 2, 'is_primary' => false],
            ]]];
        });
    }

    private function assertSaveInvalid(ProductCompletionTask $task, ProductCompletionSubmission $submission, User $reviewer, array $editor, string $field): void
    {
        $before = $submission->approval_selection_json;
        $version = $task->lock_version;
        try {
            app(ProductCompletionReviewService::class)->save($reviewer, $submission, $version, $editor['payload'], $editor['decisions']);
            $this->fail('Expected invalid review input.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertSame($before, $submission->refresh()->approval_selection_json);
        $this->assertSame($version, $task->refresh()->lock_version);
    }
}
