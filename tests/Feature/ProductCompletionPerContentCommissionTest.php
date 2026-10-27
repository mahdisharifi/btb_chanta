<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionRevisionClassification;
use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\UserRole;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionCommissionCalculator;
use App\Services\ProductCompletionDraftService;
use App\Services\ProductCompletionRevisionService;
use App\Services\ProductCompletionSubmissionService;
use App\Services\ProductCompletionTaskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCompletionPerContentCommissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prices_content_units_with_independent_caps_and_revision_percentage(): void
    {
        $initialPayload = [
            'short_description' => 'توضیح قبلی',
            'full_description' => 'متن قبلی',
            'attributes' => [
                ['attribute_id' => 1, 'value_ids' => [10]],
            ],
            'gallery' => ['items' => [
                ['product_gallery_id' => 100, 'action' => 'keep'],
            ]],
        ];
        $finalPayload = [
            'short_description' => 'توضیح جدید',
            'full_description' => 'متن جدید',
            'attributes' => [
                ['attribute_id' => 1, 'value_ids' => [20, 30]],
                ['attribute_id' => 2, 'value_ids' => [40]],
            ],
            'gallery' => ['items' => [
                ['product_gallery_id' => 100, 'action' => 'remove'],
                ['media_public_id' => '01NEWIMAGE000000000000000', 'action' => 'add'],
            ]],
        ];
        $task = ProductCompletionTask::factory()->create([
            'active_product_id' => null,
            'commission_rate_percentage' => '100.00',
            'initial_snapshot_json' => $initialPayload,
            'commission_rule_snapshot_json' => [
                'pricing_schema_version' => 2,
                'rule_id' => 1,
                'rule_version' => 1,
                'revision_policy' => 'all_resolved',
                'revision_percentage' => '30.00',
                'max_amount' => null,
                'items' => [
                    ['content_type' => 'image', 'unit_amount' => '5000.00', 'max_payable_units' => 1, 'is_enabled' => true],
                    ['content_type' => 'attribute', 'unit_amount' => '2000.00', 'max_payable_units' => 2, 'is_enabled' => true],
                    ['content_type' => 'description', 'unit_amount' => '10000.00', 'max_payable_units' => 1, 'is_enabled' => true],
                    ['content_type' => 'video', 'unit_amount' => '9000.00', 'max_payable_units' => 3, 'is_enabled' => false],
                ],
            ],
        ]);
        $origin = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
            'version_no' => 1,
            'payload_json' => $initialPayload,
        ]);
        $resolved = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
            'version_no' => 2,
            'payload_json' => $finalPayload,
        ]);
        ProductCompletionRevision::factory()->create([
            'task_id' => $task->getKey(),
            'open_task_id' => null,
            'submission_id' => $origin->getKey(),
            'resolved_by_submission_id' => $resolved->getKey(),
            'revision_no' => 1,
            'classification' => ProductCompletionRevisionClassification::SUPPLIER_SCOPE_CHANGE,
            'status' => ProductCompletionRevisionStatus::RESOLVED,
            'is_commissionable' => true,
            'pricing_schema_version' => 2,
            'changed_units_json' => null,
            'changed_unit_count' => 0,
            'resolved_at' => now(),
        ]);

        $calculated = app(ProductCompletionCommissionCalculator::class)->calculate($task, $resolved);

        // New image: 5000; corrected attribute: 600; new attribute: 2000; corrected description pair: 3000.
        $this->assertSame('10600.00', $calculated['base_amount']);
        // Revision: 30% of the same 19000 changed units = 5700.
        $this->assertSame('5700.00', $calculated['revision_amount']);
        $this->assertSame('16300.00', $calculated['total_amount']);
        $this->assertSame(1, $calculated['breakdown']['base_units']['image']['payable_units']);
        $this->assertSame(2, $calculated['breakdown']['base_units']['attribute']['payable_units']);
        $this->assertSame(1, $calculated['breakdown']['base_units']['description']['payable_units']);
        $this->assertSame(0, $calculated['breakdown']['base_units']['video']['payable_units']);
    }

    #[Test]
    public function resubmission_persists_changed_units_for_the_resolved_revision(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $outsourcer = User::factory()->create()->assignRole(UserRole::OUTSOURCER->value);
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $task = ProductCompletionTask::factory()->inProgress($outsourcer)->create([
            'reviewer_id' => $reviewer->getKey(),
            'current_draft_json' => [
                'schema_version' => 1,
                'title' => 'Valid product title',
                'short_description' => 'Valid short description',
                'full_description' => 'Valid full description',
                'specifications' => ['Material' => 'Plastic'],
            ],
            'commission_rate_percentage' => '100.00',
            'commission_rule_snapshot_json' => [
                'pricing_schema_version' => 2,
                'revision_policy' => 'all_resolved',
                'revision_percentage' => '30.00',
                'max_amount' => null,
                'items' => [
                    ['content_type' => 'description', 'unit_amount' => '10000.00', 'max_payable_units' => 1, 'is_enabled' => true],
                ],
            ],
        ]);
        $submissionService = app(ProductCompletionSubmissionService::class);
        $origin = $submissionService->submit($outsourcer, $task, 0, 'per-content-origin');
        $revision = app(ProductCompletionRevisionService::class)->request(
            $reviewer,
            $origin,
            ['short_description'],
            'توضیح کوتاه نیازمند اصلاح است.',
        );
        $task = app(ProductCompletionTaskService::class)->resumeRevision($outsourcer, $task->refresh());
        $draft = $task->current_draft_json;
        $draft['short_description'] = 'توضیح کوتاه اصلاح‌شده';
        $task = app(ProductCompletionDraftService::class)->save(
            $outsourcer,
            $task,
            $draft,
            $task->draft_version,
        );
        $submissionService->submit($outsourcer, $task, $task->draft_version, 'per-content-resolved');

        $revision->refresh();

        $this->assertSame(ProductCompletionRevisionStatus::RESOLVED, $revision->status);
        $this->assertSame(2, $revision->pricing_schema_version);
        $this->assertSame(1, $revision->changed_unit_count);
        $this->assertSame(
            ['description:short_and_long'],
            $revision->changed_units_json['description'],
        );
    }

    #[Test]
    public function legacy_schema_rates_the_fixed_amount_against_the_initial_snapshot(): void
    {
        $task = ProductCompletionTask::factory()->create([
            'active_product_id' => null,
            'commission_rule_snapshot_json' => [
                'pricing_schema_version' => 1,
                'calculation_type' => 'per_task',
                'revision_policy' => 'none',
                'base_amount' => '500000.00',
                'per_revision_amount' => '0.00',
                'per_approved_item_amount' => '0.00',
                'max_amount' => null,
            ],
        ]);
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
        ]);

        $calculated = app(ProductCompletionCommissionCalculator::class)->calculate($task, $submission);

        // All submitted descriptions and the title replace pre-existing values in the factory snapshot.
        $this->assertSame('150000.00', $calculated['base_amount']);
        $this->assertSame('150000.00', $calculated['total_amount']);
    }
}
