<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionCommissionCalculator;
use App\Services\ProductCompletionCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCompletionReviewPricingTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Review pricing tests require the isolated test database.');
        }
        $connection = $app['db']->connection();
        if ($connection->getDatabaseName() !== 'b2b_marketplace_test'
            || $connection->selectOne('SELECT DATABASE() AS current_database', [], false)->current_database !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Resolved connection must use the isolated test database.');
        }

        return $app;
    }

    public function test_reviewer_only_content_has_zero_preview_and_final_pricing_for_both_rule_versions(): void
    {
        foreach ([1, 2] as $ruleVersion) {
            $baseline = $this->baseline();
            $contractor = array_replace($baseline, ['short_description' => 'Rejected contractor text']);
            $publication = array_replace($baseline, ['full_description' => 'Reviewer addition']);
            $task = $this->task($baseline, $ruleVersion);
            $submission = $this->reviewedSubmission($task, $contractor, $baseline, $publication);

            $preview = $this->assertPreviewMatchesFinal($task, $submission);

            $this->assertSame('0.00', $preview['base_amount']);
            $this->assertSame('0.00', $preview['revision_amount']);
            $this->assertSame('0.00', $preview['total_amount']);
        }
    }

    public function test_partial_text_and_attribute_selection_prices_only_original_accepted_changes(): void
    {
        $baseline = $this->baseline();
        $contractor = array_replace($baseline, [
            'short_description' => 'Accepted correction',
            'full_description' => 'Rejected new description',
            'attributes' => [
                ['attribute_id' => 1, 'value_ids' => [11]],
                ['attribute_id' => 2, 'value_ids' => [20]],
            ],
        ]);
        $pricing = array_replace($baseline, [
            'short_description' => $contractor['short_description'],
            'attributes' => [$contractor['attributes'][0]],
        ]);
        $publication = array_replace($contractor, [
            'short_description' => 'Reviewer edited accepted correction',
            'full_description' => 'Reviewer replacement',
            'attributes' => [
                ['attribute_id' => 1, 'value_ids' => [12]],
                ['attribute_id' => 3, 'value_ids' => [30]],
            ],
        ]);
        $task = $this->task($baseline);
        $submission = $this->reviewedSubmission($task, $contractor, $pricing, $publication);

        $preview = $this->assertPreviewMatchesFinal($task, $submission);

        $this->assertSame('90.00', $preview['base_amount']);
        $this->assertSame('30.00', $preview['breakdown']['task_rate_percentage']);
        $this->assertSame(['attribute:1'], $preview['breakdown']['base_unit_keys']['attribute']);
        $this->assertSame('30.00', $preview['breakdown']['content_rates']['description']['description:short_and_long']['rate_percentage']);
    }

    public function test_resolved_revisions_are_intersected_with_final_accepted_original_keys(): void
    {
        foreach ([1, 2] as $ruleVersion) {
            $baseline = $this->baseline();
            $task = $this->task($baseline, $ruleVersion, [
                'revision_policy' => 'all_resolved',
                'revision_percentage' => '30.00',
                'per_revision_amount' => '50.00',
            ]);
            $origin = ProductCompletionSubmission::factory()->create([
                'task_id' => $task->getKey(), 'version_no' => 1, 'payload_json' => $baseline,
            ]);
            $contractor = array_replace($baseline, [
                'attributes' => [
                    ['attribute_id' => 1, 'value_ids' => [11]],
                    ['attribute_id' => 2, 'value_ids' => [20]],
                ],
            ]);
            $pricing = array_replace($baseline, ['attributes' => [$contractor['attributes'][0]]]);
            $submission = $this->reviewedSubmission($task, $contractor, $pricing, $contractor, 2);
            foreach ([['attribute:1', 'attribute:2'], ['attribute:2']] as $index => $keys) {
                ProductCompletionRevision::factory()->create([
                    'task_id' => $task->getKey(),
                    'submission_id' => $origin->getKey(),
                    'resolved_by_submission_id' => $submission->getKey(),
                    'open_task_id' => null,
                    'revision_no' => $index + 1,
                    'status' => 'resolved',
                    'is_commissionable' => true,
                    'pricing_schema_version' => 2,
                    'changed_units_json' => ['attribute' => $keys],
                ]);
            }

            $preview = $this->assertPreviewMatchesFinal($task, $submission);

            $this->assertSame($ruleVersion === 2 ? '60.00' : '50.00', $preview['revision_amount']);
            if ($ruleVersion === 2) {
                $this->assertSame(1, $preview['breakdown']['revision_count']);
                $this->assertSame(['attribute' => ['attribute:1']], $preview['breakdown']['revisions'][0]['changed_units']);
            } else {
                $this->assertSame(1, $preview['breakdown']['commissionable_revision_count']);
            }
        }
    }

    public function test_legacy_selective_envelope_is_rejected_instead_of_pricing_unapproved_content(): void
    {
        $baseline = $this->baseline();
        $task = $this->task($baseline);
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
            'payload_json' => array_replace($baseline, ['full_description' => 'Contractor addition']),
            'approval_selection_json' => ['mode' => 'selective', 'fields' => []],
        ]);

        $this->expectException(ValidationException::class);
        app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission);
    }

    public function test_malformed_review_snapshot_cannot_fall_through_to_legacy_pricing(): void
    {
        $baseline = $this->baseline();
        $task = $this->task($baseline);
        foreach ([
            ['schema_version' => 1, 'mode' => 'item_review'],
            ['schema_version' => 2, 'mode' => 'selective'],
            ['mode' => 'item_review', 'pricing_payload' => $baseline],
            ['mode' => 'unsupported'],
            ['approved_fields' => ['short_description']],
        ] as $selection) {
            $submission = ProductCompletionSubmission::factory()->make([
                'task_id' => $task->getKey(),
                'payload_json' => $baseline,
                'approval_selection_json' => $selection,
            ]);

            try {
                app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission);
                $this->fail('Malformed review snapshots must be rejected before pricing.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('review', $exception->errors());
            }
        }
    }

    private function baseline(): array
    {
        return [
            'schema_version' => 1,
            'title' => 'Existing title',
            'short_description' => 'Existing short description',
            'full_description' => '',
            'attributes' => [['attribute_id' => 1, 'value_ids' => [10]]],
            'gallery' => ['items' => []],
        ];
    }

    private function task(array $baseline, int $ruleVersion = 2, array $ruleOverrides = []): ProductCompletionTask
    {
        return ProductCompletionTask::factory()->create([
            'assigned_to' => User::factory(),
            'reviewer_id' => User::factory(),
            'initial_snapshot_json' => $baseline,
            'commission_content_snapshot_json' => $baseline,
            'commission_rule_snapshot_json' => array_replace([
                'pricing_schema_version' => $ruleVersion,
                'calculation_type' => 'per_task',
                'revision_policy' => 'none',
                'base_amount' => '100.00',
                'per_approved_item_amount' => '0.00',
                'max_amount' => null,
                'items' => [
                    ['content_type' => 'description', 'unit_amount' => '100.00', 'max_payable_units' => 1, 'is_enabled' => true],
                    ['content_type' => 'attribute', 'unit_amount' => '200.00', 'max_payable_units' => 10, 'is_enabled' => true],
                ],
            ], $ruleOverrides),
        ]);
    }

    private function reviewedSubmission(ProductCompletionTask $task, array $contractor, array $pricing, array $publication, int $version = 1): ProductCompletionSubmission
    {
        $payloadHash = hash('sha256', json_encode($contractor, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
            'version_no' => $version,
            'status' => 'approved',
            'payload_json' => $contractor,
            'payload_hash' => $payloadHash,
            'reviewed_by' => $task->reviewer_id,
            'reviewed_at' => now(),
        ]);
        $submission->approval_selection_json = ProductCompletionSubmission::signReviewSnapshot([
            'schema_version' => 2,
            'mode' => 'item_review',
            'pricing_payload' => $pricing,
            'editor_payload' => $publication,
            'reviewed_payload' => $publication,
            'decisions' => [],
            'media_items' => [],
            'reviewer_id' => (int) $task->reviewer_id,
            'source_payload_hash' => $payloadHash,
            'source_task_id' => (int) $submission->task_id,
            'source_submission_id' => (int) $submission->getKey(),
            'product_version' => (int) $task->product_version_at_assignment,
            'source_lock_version' => (int) $task->lock_version,
            'reviewed_at' => now()->toIso8601String(),
        ]);
        $submission->save();

        return $submission;
    }

    private function assertPreviewMatchesFinal(ProductCompletionTask $task, ProductCompletionSubmission $submission): array
    {
        $originalAttributes = $submission->getAttributes();
        $preview = app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission);
        $this->assertSame($originalAttributes, $submission->getAttributes());
        $this->assertSame($submission->payload_json, $submission->fresh()->payload_json);

        $task->forceFill([
            'status' => ProductCompletionTaskStatus::APPROVED,
            'last_submission_id' => $submission->getKey(),
            'content_applied_at' => now(),
            'media_published_at' => now(),
        ])->save();
        $commission = app(ProductCompletionCommissionService::class)->calculate($task);

        $this->assertSame($preview['base_amount'], $commission->base_amount);
        $this->assertSame($preview['revision_amount'], $commission->revision_amount);
        $this->assertSame($preview['total_amount'], $commission->total_amount);
        $this->assertSame($preview['breakdown']['content_rates'], $commission->breakdown_json['content_rates']);
        $this->assertSame($preview['breakdown']['task_rate_percentage'], $task->fresh()->commission_rate_percentage);

        return $preview;
    }
}
