<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionRevisionClassification;
use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\UserRole;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionCommissionCalculator;
use App\Services\ProductCompletionRevisionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCompletionCorrectionFinancialControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function correction_of_existing_content_uses_thirty_percent_and_a_recorded_exception_can_use_full_rate(): void
    {
        $task = ProductCompletionTask::factory()->create([
            'active_product_id' => null,
            'commission_rate_percentage' => '30.00',
            'initial_snapshot_json' => [
                'short_description' => 'توضیح قبلی',
                'full_description' => 'متن قبلی',
            ],
            'commission_rule_snapshot_json' => [
                'pricing_schema_version' => 2,
                'revision_policy' => 'none',
                'revision_percentage' => '30.00',
                'max_amount' => null,
                'items' => [
                    ['content_type' => 'description', 'unit_amount' => '10000.00', 'max_payable_units' => 1, 'is_enabled' => true],
                ],
            ],
        ]);
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
            'payload_json' => [
                'short_description' => 'توضیح اصلاح‌شده',
                'full_description' => 'متن قبلی',
            ],
        ]);
        $calculator = app(ProductCompletionCommissionCalculator::class);

        $this->assertSame('3000.00', $calculator->calculate($task, $submission)['base_amount']);

        $task->forceFill(['commission_rate_percentage' => '100.00', 'full_rate_approved_by' => 1,
            'full_rate_approved_at' => now(), 'full_rate_approval_reason' => 'Approved full-rate exception.']);

        $this->assertSame('10000.00', $calculator->calculate($task, $submission)['base_amount']);
    }

    #[Test]
    public function reviewer_can_adjust_only_commissions_for_tasks_reviewed_by_them(): void
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $otherReviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $task = ProductCompletionTask::factory()->create([
            'active_product_id' => null,
            'reviewer_id' => $reviewer->getKey(),
        ]);
        $approvedSubmission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
            'reviewed_by' => $reviewer->getKey(),
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
        $task->forceFill(['last_submission_id' => $approvedSubmission->getKey()])->save();
        $commission = ProductCompletionCommission::factory()->create([
            'task_id' => $task->getKey(),
            'commission_rule_id' => $task->commission_rule_id,
        ]);

        $this->assertTrue(Gate::forUser($reviewer)->allows('viewCommission', $commission));
        $this->assertTrue(Gate::forUser($reviewer)->allows('adjustCommission', $commission));
        $this->assertFalse(Gate::forUser($reviewer)->allows('approveCommission', $commission));
        $this->assertFalse(Gate::forUser($reviewer)->allows('payCommission', $commission));
        $this->assertFalse(Gate::forUser($otherReviewer)->allows('viewCommission', $commission));
        $this->assertFalse(Gate::forUser($otherReviewer)->allows('adjustCommission', $commission));
    }

    #[Test]
    public function legacy_all_resolved_policy_does_not_pay_non_commissionable_revision(): void
    {
        $task = ProductCompletionTask::factory()->create([
            'active_product_id' => null,
            'commission_rate_percentage' => '100.00',
            'commission_rule_snapshot_json' => [
                'pricing_schema_version' => 1,
                'calculation_type' => 'per_task',
                'revision_policy' => 'all_resolved',
                'base_amount' => '0.00',
                'per_revision_amount' => '10000.00',
                'per_approved_item_amount' => '0.00',
                'max_amount' => null,
            ],
        ]);
        $origin = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
            'version_no' => 1,
        ]);
        $resolved = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
            'version_no' => 2,
        ]);
        ProductCompletionRevision::factory()->create([
            'task_id' => $task->getKey(),
            'open_task_id' => null,
            'submission_id' => $origin->getKey(),
            'resolved_by_submission_id' => $resolved->getKey(),
            'revision_no' => 1,
            'classification' => ProductCompletionRevisionClassification::CONTRACTOR_ERROR,
            'status' => ProductCompletionRevisionStatus::RESOLVED,
            'is_commissionable' => false,
            'resolved_at' => now(),
        ]);

        $calculated = app(ProductCompletionCommissionCalculator::class)->calculate($task, $resolved);

        $this->assertSame('0.00', $calculated['revision_amount']);
        $this->assertSame('0.00', $calculated['total_amount']);
    }

    #[Test]
    public function contractor_error_is_not_commissionable_but_supplier_scope_change_is(): void
    {
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $contractorErrorSubmission = $this->submittedTask($reviewer);
        $contractorErrorRevision = app(ProductCompletionRevisionService::class)->request(
            $reviewer,
            $contractorErrorSubmission,
            ['short_description'],
            'رفع خطای ثبت‌شده توسط پیمانکار',
            ProductCompletionRevisionClassification::CONTRACTOR_ERROR,
        );
        $supplierChangeSubmission = $this->submittedTask($reviewer);
        $supplierChangeRevision = app(ProductCompletionRevisionService::class)->request(
            $reviewer,
            $supplierChangeSubmission,
            ['short_description'],
            'تأمین‌کننده درخواست جدیدی ثبت کرده است',
            ProductCompletionRevisionClassification::SUPPLIER_SCOPE_CHANGE,
        );

        $this->assertFalse($contractorErrorRevision->is_commissionable);
        $this->assertTrue($supplierChangeRevision->is_commissionable);
    }

    private function submittedTask(User $reviewer): ProductCompletionSubmission
    {
        $task = ProductCompletionTask::factory()->create([
            'active_product_id' => null,
            'reviewer_id' => $reviewer->getKey(),
            'status' => ProductCompletionTaskStatus::SUBMITTED,
        ]);
        $submission = ProductCompletionSubmission::factory()->create([
            'task_id' => $task->getKey(),
        ]);
        $task->forceFill(['last_submission_id' => $submission->getKey()])->save();

        return $submission->refresh();
    }
}
