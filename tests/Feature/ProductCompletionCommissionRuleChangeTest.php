<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductCompletionCommissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Enums\UserRole;
use App\Livewire\Admin\Outsourcing\Commissions;
use App\Models\CommissionRule;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\OutsourcingSettlementService;
use App\Services\ProductCompletionCommissionRuleChangeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCompletionCommissionRuleChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function finance_can_change_rule_with_preview_and_preserve_adjustments_and_audit(): void
    {
        [$commission, $rule] = $this->commissionAndRule();
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $service = app(ProductCompletionCommissionRuleChangeService::class);
        $preview = $service->preview($actor, $commission, $rule);
        $service->change($actor, $commission, $rule, 'اصلاح قانون', $preview['token']);
        $commission->refresh();
        $task = $commission->task()->firstOrFail();
        $this->assertSame($rule->getKey(), $task->commission_rule_id);
        $this->assertSame($rule->getKey(), $task->commission_rule_snapshot_json['rule_id']);
        $this->assertSame($preview['result']['total_amount'], $commission->total_amount);
        $this->assertSame('100.00', $commission->bonus_amount);
        $this->assertSame(ProductCompletionCommissionStatus::CALCULATED, $commission->status);
        $this->assertNull($commission->approved_at);
        $this->assertDatabaseHas('product_completion_audits', ['task_id' => $task->getKey(), 'user_id' => $actor->getKey(), 'action' => 'commission_rule_changed']);
    }

    #[Test]
    public function admin_is_allowed_but_a_reviewer_is_not(): void
    {
        [$commission, $rule] = $this->commissionAndRule();
        $service = app(ProductCompletionCommissionRuleChangeService::class);
        $service->preview(User::factory()->create()->assignRole(UserRole::ADMIN->value), $commission, $rule);
        $this->expectException(AuthorizationException::class);
        $service->preview(User::factory()->create()->assignRole(UserRole::REVIEWER->value), $commission, $rule);
    }

    #[Test]
    public function finance_can_preview_and_change_the_rule_from_the_commissions_page(): void
    {
        [$commission, $rule] = $this->commissionAndRule();
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $this->actingAs($actor);

        Livewire::test(Commissions::class)
            ->set('ruleIds.'.$commission->public_id, $rule->getKey())
            ->set('ruleReasons.'.$commission->public_id, 'اصلاح قانون')
            ->call('previewRule', $commission->public_id)
            ->assertHasNoErrors()
            ->call('changeRule', $commission->public_id)
            ->assertHasNoErrors();

        $this->assertSame($rule->getKey(), $commission->fresh()->commission_rule_id);
    }

    #[Test]
    public function incompatible_rule_and_stale_preview_are_rejected(): void
    {
        [$commission, $rule] = $this->commissionAndRule();
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $service = app(ProductCompletionCommissionRuleChangeService::class);
        $preview = $service->preview($actor, $commission, $rule);
        $commission->forceFill(['bonus_amount' => '200.00'])->save();
        try {
            $service->change($actor, $commission, $rule, 'reason', $preview['token']);
            $this->fail('Stale preview accepted.');
        } catch (ValidationException) {
            $this->assertNotSame($rule->getKey(), $commission->fresh()->commission_rule_id);
        }
        $wrongRule = CommissionRule::factory()->create(['task_type' => ProductCompletionTaskType::MEDIA]);
        $this->expectException(ValidationException::class);
        $service->preview($actor, $commission->fresh(), $wrongRule);
    }

    #[Test]
    public function paid_commissions_cannot_be_changed_even_by_super_admin(): void
    {
        [$commission, $rule] = $this->commissionAndRule();
        $commission->forceFill(['status' => ProductCompletionCommissionStatus::PAID])->save();
        $actor = User::factory()->create()->assignRole(UserRole::SUPER_ADMIN->value);
        $this->expectException(ValidationException::class);
        app(ProductCompletionCommissionRuleChangeService::class)->preview($actor, $commission, $rule);
    }

    #[Test]
    public function bulk_change_includes_all_pages_and_only_the_chosen_contractors_matching_unreserved_commissions(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $contractor = User::factory()->create();
        $source = CommissionRule::factory()->create(['task_type' => ProductCompletionTaskType::FULL_COMPLETION]);
        $target = CommissionRule::factory()->create(['task_type' => $source->task_type, 'base_amount' => '200000.00']);
        $eligible = collect();
        for ($index = 0; $index < 21; $index++) {
            $eligible->push($this->commissionFor($contractor, $source));
        }
        $otherContractor = $this->commissionFor(User::factory()->create(), $source);
        $otherSource = $this->commissionFor($contractor, $target);
        $otherType = $this->commissionFor($contractor, CommissionRule::factory()->create(['task_type' => ProductCompletionTaskType::MEDIA]));
        $paid = $this->commissionFor($contractor, $source);
        $paid->forceFill(['status' => ProductCompletionCommissionStatus::PAID])->save();
        $reserved = $this->commissionFor($contractor, $source);
        $reserved->forceFill(['status' => ProductCompletionCommissionStatus::PAYABLE,
            'approved_by' => $actor->id, 'payable_at' => now()])->save();
        app(OutsourcingSettlementService::class)->create($actor, $contractor->id, 'IRR',
            now()->startOfMonth()->toDateString(), now()->toDateString(), token: (string) \Illuminate\Support\Str::uuid());

        $service = app(ProductCompletionCommissionRuleChangeService::class);
        $preview = $service->previewMany($actor, $contractor->id, $target, $source->id);
        $this->assertCount(21, $preview['rows']);
        $this->assertSame(2, $preview['excluded_count']);
        $this->assertSame(21, $service->changeMany($actor, $contractor->id, $target,
            array_map(fn ($row) => $row['token'], $preview['rows']), 'اصلاح گروهی قانون'));

        foreach ($eligible as $commission) {
            $commission->refresh();
            $this->assertSame($target->id, $commission->commission_rule_id);
            $this->assertSame($target->id, $commission->task()->firstOrFail()->commission_rule_id);
            $this->assertSame($preview['rows'][$commission->public_id]['new_total'], $commission->total_amount);
            $this->assertSame('100.00', $commission->bonus_amount);
            $this->assertSame(ProductCompletionCommissionStatus::CALCULATED, $commission->status);
        }
        $this->assertSame($source->id, $otherContractor->fresh()->commission_rule_id);
        $this->assertSame($target->id, $otherSource->fresh()->commission_rule_id);
        $this->assertNotSame($target->id, $otherType->fresh()->commission_rule_id);
        $this->assertSame(ProductCompletionCommissionStatus::PAID, $paid->fresh()->status);
        $this->assertNotNull($reserved->fresh()->settlement_id);
        $audits = \App\Models\ProductCompletionAudit::query()->where('action', 'commission_rule_changed')->get();
        $this->assertCount(21, $audits);
        $this->assertCount(1, $audits->pluck('metadata_json.batch_id')->unique());
        $this->assertNotNull($audits->first()->metadata_json['batch_id']);
    }

    #[Test]
    public function a_stale_member_rolls_back_the_entire_batch_including_audits(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $contractor = User::factory()->create();
        $source = CommissionRule::factory()->create(['task_type' => ProductCompletionTaskType::CONTENT]);
        $target = CommissionRule::factory()->create(['task_type' => $source->task_type, 'base_amount' => '200000.00']);
        $first = $this->commissionFor($contractor, $source);
        $second = $this->commissionFor($contractor, $source);
        $service = app(ProductCompletionCommissionRuleChangeService::class);
        $preview = $service->previewMany($actor, $contractor->id, $target);
        $second->forceFill(['bonus_amount' => '999.00'])->save();

        try {
            $service->changeMany($actor, $contractor->id, $target,
                array_map(fn ($row) => $row['token'], $preview['rows']), 'اصلاح گروهی');
            $this->fail('A stale batch was accepted.');
        } catch (ValidationException) {
            foreach ([$first, $second] as $commission) {
                $this->assertSame($source->id, $commission->fresh()->commission_rule_id);
                $this->assertSame($source->id, $commission->task()->firstOrFail()->commission_rule_id);
                $this->assertSame(ProductCompletionCommissionStatus::APPROVED, $commission->fresh()->status);
            }
            $this->assertDatabaseMissing('product_completion_audits', ['action' => 'commission_rule_changed']);
        }
    }

    #[Test]
    public function bulk_recalculation_uses_the_new_rules_content_prices_and_preserves_manual_adjustments(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value);
        $contractor = User::factory()->create();
        $source = CommissionRule::factory()->create(['task_type' => ProductCompletionTaskType::FULL_COMPLETION]);
        $target = app(\App\Services\CommissionRuleService::class)->createPerContentVersion($actor, [
            'name' => 'تکمیل کامل محصول اصلاحی ۱', 'task_type' => 'full_completion',
            'revision_percentage' => '30.00', 'currency' => 'IRR',
            'items' => [
                ['content_type' => 'description', 'unit_amount' => '10000.00', 'max_payable_units' => 1, 'is_enabled' => true],
                ['content_type' => 'attribute', 'unit_amount' => '0.00', 'max_payable_units' => 0, 'is_enabled' => false],
                ['content_type' => 'image', 'unit_amount' => '0.00', 'max_payable_units' => 0, 'is_enabled' => false],
                ['content_type' => 'video', 'unit_amount' => '0.00', 'max_payable_units' => 0, 'is_enabled' => false],
            ],
        ]);
        $commissions = [$this->commissionFor($contractor, $source), $this->commissionFor($contractor, $source)];
        $service = app(ProductCompletionCommissionRuleChangeService::class);
        $preview = $service->previewMany($actor, $contractor->id, $target, $source->id);
        $service->changeMany($actor, $contractor->id, $target,
            array_map(fn ($row) => $row['token'], $preview['rows']), 'اصلاح گروهی');

        foreach ($commissions as $commission) {
            $commission->refresh();
            $this->assertSame('3000.00', $commission->base_amount);
            $this->assertSame('3100.00', $commission->total_amount);
            $this->assertSame(2, $commission->task()->firstOrFail()->commission_rule_snapshot_json['pricing_schema_version']);
            $this->assertSame($target->id, $commission->breakdown_json['rule_id']);
        }
    }

    #[Test]
    public function the_page_can_apply_a_selected_subset_and_show_exact_totals(): void
    {
        $actor = User::factory()->create()->assignRole(UserRole::ADMIN->value);
        $contractor = User::factory()->create();
        $source = CommissionRule::factory()->create(['task_type' => ProductCompletionTaskType::CONTENT]);
        $target = CommissionRule::factory()->create(['task_type' => $source->task_type, 'base_amount' => '200000.00']);
        $first = $this->commissionFor($contractor, $source);
        $second = $this->commissionFor($contractor, $source);
        $excluded = $this->commissionFor($contractor, $source);
        $this->actingAs($actor);

        Livewire::test(Commissions::class)
            ->set('bulkUserId', (string) $contractor->id)
            ->set('bulkTaskType', $source->task_type->value)
            ->set('bulkSourceRuleId', (string) $source->id)
            ->set('bulkRuleId', (string) $target->id)
            ->call('previewBulkRule')->assertHasNoErrors()
            ->assertCount('bulkSelected', 3)
            ->call('clearBulkSelection')->assertSet('bulkSelected', [])
            ->call('selectAllBulk')->assertCount('bulkSelected', 3)
            ->set('bulkSelected', [$first->public_id, $second->public_id])
            ->assertViewHas('bulkSummary', fn ($summary) => $summary['count'] === 2 && $summary['old_total'] === '1000000.00')
            ->set('bulkReason', 'اصلاح گروهی قانون')
            ->call('changeBulkRule')->assertHasNoErrors()
            ->assertSet('bulkPreview', []);

        $this->assertSame($target->id, $first->fresh()->commission_rule_id);
        $this->assertSame($target->id, $second->fresh()->commission_rule_id);
        $this->assertSame($source->id, $excluded->fresh()->commission_rule_id);
    }

    #[Test]
    public function changing_bulk_filters_discards_the_preview_and_selection(): void
    {
        [$commission, $rule] = $this->commissionAndRule();
        $this->actingAs(User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value));
        Livewire::test(Commissions::class)
            ->set('bulkUserId', (string) $commission->user_id)
            ->set('bulkTaskType', $rule->task_type->value)
            ->set('bulkRuleId', (string) $rule->id)
            ->call('previewBulkRule')->assertHasNoErrors()->assertCount('bulkSelected', 1)
            ->set('bulkTaskType', ProductCompletionTaskType::MEDIA->value)
            ->assertSet('bulkPreview', [])->assertSet('bulkSelected', [])->assertSet('bulkRuleId', '');
    }

    #[Test]
    public function a_bulk_selection_cannot_add_a_commission_outside_the_preview(): void
    {
        [$commission, $rule] = $this->commissionAndRule();
        [$otherCommission] = $this->commissionAndRule();
        $this->actingAs(User::factory()->create()->assignRole(UserRole::FINANCE_MANAGER->value));
        Livewire::test(Commissions::class)
            ->set('bulkUserId', (string) $commission->user_id)
            ->set('bulkTaskType', $rule->task_type->value)
            ->set('bulkRuleId', (string) $rule->id)
            ->call('previewBulkRule')->assertHasNoErrors()
            ->set('bulkSelected', [$otherCommission->public_id])
            ->set('bulkReason', 'اصلاح گروهی')
            ->call('changeBulkRule')->assertHasErrors('bulk');
        $this->assertNotSame($rule->id, $commission->fresh()->commission_rule_id);
        $this->assertNotSame($rule->id, $otherCommission->fresh()->commission_rule_id);
    }

    #[Test]
    public function reviewers_cannot_preview_or_apply_bulk_rule_changes(): void
    {
        [$commission, $rule] = $this->commissionAndRule();
        $reviewer = User::factory()->create()->assignRole(UserRole::REVIEWER->value);
        $this->expectException(AuthorizationException::class);
        app(ProductCompletionCommissionRuleChangeService::class)->previewMany($reviewer, $commission->user_id, $rule);
    }

    private function commissionFor(User $contractor, CommissionRule $source): ProductCompletionCommission
    {
        $task = ProductCompletionTask::factory()->create([
            'status' => ProductCompletionTaskStatus::COMPLETED, 'active_product_id' => null,
            'task_type' => $source->task_type, 'commission_rule_id' => $source->id, 'assigned_to' => $contractor->id,
        ]);
        $submission = ProductCompletionSubmission::factory()->create(['task_id' => $task->id, 'status' => 'approved']);
        $task->forceFill(['last_submission_id' => $submission->id])->save();

        return ProductCompletionCommission::factory()->create([
            'task_id' => $task->id, 'user_id' => $contractor->id, 'commission_rule_id' => $source->id,
            'status' => ProductCompletionCommissionStatus::APPROVED, 'approved_at' => now(), 'bonus_amount' => '100.00',
        ]);
    }

    private function commissionAndRule(): array
    {
        $task = ProductCompletionTask::factory()->create(['status' => ProductCompletionTaskStatus::COMPLETED, 'active_product_id' => null]);
        $submission = ProductCompletionSubmission::factory()->create(['task_id' => $task->getKey(), 'status' => 'approved']);
        $task->forceFill(['last_submission_id' => $submission->getKey()])->save();
        $commission = ProductCompletionCommission::factory()->create([
            'task_id' => $task->getKey(), 'commission_rule_id' => $task->commission_rule_id,
            'status' => ProductCompletionCommissionStatus::APPROVED, 'approved_at' => now(), 'bonus_amount' => '100.00',
        ]);
        $rule = CommissionRule::factory()->create(['task_type' => $task->task_type, 'base_amount' => '200000.00']);

        return [$commission, $rule];
    }
}
