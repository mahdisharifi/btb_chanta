<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Services\ProductCompletionCommissionCalculator;
use Tests\TestCase;

class ProductCompletionAutomaticCommissionTest extends TestCase
{
    public function test_each_attribute_and_image_is_priced_independently_of_task_type(): void
    {
        $before = ['short_description' => null, 'full_description' => null,
            'attributes' => [['attribute_id' => 2, 'value_ids' => [14]]],
            'gallery' => ['items' => [['product_gallery_id' => 1]]],
        ];
        $after = ['short_description' => 'New short', 'full_description' => 'New full',
            'attributes' => [
                ['attribute_id' => 2, 'value_ids' => [], 'proposed_values' => ['pcs:41']],
                ['attribute_id' => 9, 'value_ids' => [20]],
                ['attribute_id' => 15, 'value_ids' => [], 'proposed_values' => ['Material']],
            ],
            'gallery' => ['items' => [['product_gallery_id' => 1], ['media_public_id' => 'new-image']]],
        ];
        foreach (['correction', 'full_completion'] as $taskType) {
            [$task, $submission] = $this->models($before, $after);
            $task->task_type = $taskType;
            $task->commission_rate_percentage = '30.00';
            $result = app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission);
            $this->assertSame('19600.00', $result['base_amount']);
            $this->assertSame('600.00', $result['breakdown']['base_units']['attribute']['priced_units'][2]['amount']);
            $this->assertSame(3, $result['breakdown']['base_units']['attribute']['payable_units']);
            $this->assertSame('10000.00', $result['breakdown']['base_units']['description']['amount']);
            $this->assertSame('93.33', $result['breakdown']['task_rate_percentage']);
        }
    }

    public function test_description_pair_is_full_rate_when_one_description_is_new(): void
    {
        [$task, $submission] = $this->models(
            ['short_description' => 'Wrong old', 'full_description' => null],
            ['short_description' => 'Corrected old', 'full_description' => 'New full'],
        );
        $this->assertSame('10000.00', app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission)['base_amount']);
        $task->initial_snapshot_json = ['short_description' => 'Wrong old', 'full_description' => 'Wrong full'];
        $this->assertSame('3000.00', app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission)['base_amount']);
    }

    public function test_resubmission_does_not_discount_content_missing_from_the_original_snapshot(): void
    {
        [$task, $submission] = $this->models(['short_description' => null], ['short_description' => 'Revised new description']);
        $submission->version_no = 2;
        $submission->diff_json = ['short_description' => ['before' => 'First contractor draft', 'after' => 'Revised new description']];
        $this->assertSame('10000.00', app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission)['base_amount']);
        $task->commission_content_snapshot_json = ['short_description' => null];
        $task->initial_snapshot_json = ['short_description' => 'Rebased product text'];
        $this->assertSame('10000.00', app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission)['base_amount']);
    }

    public function test_full_rate_exception_requires_a_recorded_approver_date_and_reason(): void
    {
        [$task, $submission] = $this->models(['short_description' => 'Old'], ['short_description' => 'Corrected']);
        $task->commission_rate_percentage = '100.00';
        $calculator = app(ProductCompletionCommissionCalculator::class);
        $this->assertSame('3000.00', $calculator->calculate($task, $submission)['base_amount']);
        $task->forceFill(['full_rate_approved_by' => 1, 'full_rate_approved_at' => now(), 'full_rate_approval_reason' => 'Approved exception']);
        $this->assertSame('10000.00', $calculator->calculate($task, $submission)['base_amount']);
        $task->full_rate_approval_reason = null;
        $this->assertSame('3000.00', $calculator->calculate($task, $submission)['base_amount']);
    }

    public function test_unchanged_content_gets_no_unit_payment_and_caps_are_respected(): void
    {
        [$task, $submission] = $this->models(['short_description' => 'Same'], ['short_description' => 'Same']);
        $this->assertSame('0.00', app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission)['base_amount']);
        $task->initial_snapshot_json = ['short_description' => null, 'full_description' => null];
        $submission->payload_json = ['short_description' => '', 'full_description' => '<p>&nbsp;</p>'];
        $this->assertSame('0.00', app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission)['base_amount']);
        [$task, $submission] = $this->models(
            ['attributes' => [['attribute_id' => 1, 'value_ids' => [1]]]],
            ['attributes' => [['attribute_id' => 1, 'value_ids' => [2]], ['attribute_id' => 2, 'value_ids' => [3]]]],
        );
        $snapshot = $task->commission_rule_snapshot_json;
        $snapshot['items'][1]['max_payable_units'] = 1;
        $snapshot['max_amount'] = '1000.00';
        $task->commission_rule_snapshot_json = $snapshot;
        $result = app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission);
        $this->assertSame('2000.00', $result['base_amount']);
        $this->assertSame('1000.00', $result['total_amount']);
        $this->assertSame('attribute:2', $result['breakdown']['base_units']['attribute']['priced_units'][0]['key']);
    }

    public function test_legacy_item_pricing_uses_all_final_units_rather_than_the_last_revision_diff(): void
    {
        [$task, $submission] = $this->models(
            ['attributes' => [['attribute_id' => 2, 'value_ids' => [14]]]],
            ['short_description' => 'New short', 'full_description' => 'New full',
                'attributes' => [['attribute_id' => 2, 'value_ids' => [], 'proposed_values' => ['pcs:41']], ['attribute_id' => 9, 'value_ids' => [20]]],
                'gallery' => ['items' => [['media_public_id' => 'new-image']]],
            ],
        );
        $task->commission_rule_snapshot_json = ['pricing_schema_version' => 1, 'calculation_type' => 'per_field_group',
            'revision_policy' => 'none', 'base_amount' => '15000.00', 'per_approved_item_amount' => '25000.00', 'max_amount' => '35000.00',
        ];
        $submission->diff_json = ['short_description' => ['before' => 'Earlier draft', 'after' => 'New short']];
        $result = app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission);
        $this->assertSame(4, $result['breakdown']['approved_item_count']);
        $this->assertSame('82500.00', $result['approved_items_amount']);
        $this->assertSame('35000.00', $result['total_amount']);
    }

    private function models(array $before, array $after): array
    {
        $task = new ProductCompletionTask(['initial_snapshot_json' => $before, 'commission_rule_snapshot_json' => [
            'pricing_schema_version' => 2, 'revision_policy' => 'none', 'max_amount' => null,
            'items' => [
                ['content_type' => 'image', 'unit_amount' => '5000.00', 'max_payable_units' => 10, 'is_enabled' => true],
                ['content_type' => 'attribute', 'unit_amount' => '2000.00', 'max_payable_units' => 10, 'is_enabled' => true],
                ['content_type' => 'description', 'unit_amount' => '10000.00', 'max_payable_units' => 1, 'is_enabled' => true],
            ],
        ]]);

        return [$task, new ProductCompletionSubmission(['payload_json' => $after])];
    }
}
