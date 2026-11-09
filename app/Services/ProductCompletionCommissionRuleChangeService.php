<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionCommissionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Models\CommissionRule;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCompletionCommissionRuleChangeService
{
    public function __construct(
        private readonly ProductCompletionCommissionCalculator $calculator,
        private readonly ProductCompletionTaskService $taskService,
        private readonly ProductCompletionAuditService $auditService,
        private readonly ProductCompletionInputSanitizer $sanitizer,
    ) {}

    public function preview(User $actor, ProductCompletionCommission $commission, CommissionRule $rule): array
    {
        $commission = $commission->fresh(['task.lastSubmission']);
        $rule = CommissionRule::query()->findOrFail($rule->getKey());

        return $this->buildPreview($actor, $commission, $rule, $this->taskService->commissionRuleSnapshot($rule));
    }

    public function previewMany(User $actor, int $userId, CommissionRule $rule, ?int $sourceRuleId = null): array
    {
        Gate::forUser($actor)->authorize('changeRules', ProductCompletionCommission::class);
        $rule = CommissionRule::query()->findOrFail($rule->getKey());
        $query = ProductCompletionCommission::query()->where('user_id', $userId)
            ->whereHas('task', fn ($tasks) => $tasks->where('task_type', $rule->task_type))
            ->when($sourceRuleId !== null, fn ($commissions) => $commissions->where('commission_rule_id', $sourceRuleId));
        $totalCount = (clone $query)->count();
        $commissions = $query->whereNull('settlement_id')
            ->whereIn('status', [ProductCompletionCommissionStatus::CALCULATED, ProductCompletionCommissionStatus::UNDER_REVIEW,
                ProductCompletionCommissionStatus::APPROVED, ProductCompletionCommissionStatus::PAYABLE])
            ->where('currency', $rule->currency)
            ->whereHas('task', fn ($tasks) => $tasks->where('status', ProductCompletionTaskStatus::COMPLETED)
                ->whereHas('lastSubmission', fn ($submissions) => $submissions->where('status', ProductCompletionSubmissionStatus::APPROVED)))
            ->with(['task.lastSubmission', 'task.product:id,title', 'commissionRule:id,name,version'])
            ->orderBy('id')->get();
        $snapshot = $this->taskService->commissionRuleSnapshot($rule);
        $rows = [];
        foreach ($commissions as $commission) {
            $preview = $this->buildPreview($actor, $commission, $rule, $snapshot);
            $rows[$commission->public_id] = [
                'task_public_id' => $commission->task->public_id,
                'product_title' => $commission->task->product?->title,
                'old_rule' => $commission->commissionRule?->name,
                'old_rule_version' => $commission->commissionRule?->version,
                'old_total' => $preview['old_total'], 'new_total' => $preview['result']['total_amount'],
                'token' => $preview['token'],
            ];
        }

        return ['user_id' => $userId, 'rule_id' => $rule->getKey(), 'source_rule_id' => $sourceRuleId, 'task_type' => $rule->task_type->value,
            'currency' => $rule->currency, 'excluded_count' => $totalCount - count($rows), 'rows' => $rows];
    }

    public function changeMany(User $actor, int $userId, CommissionRule $rule, array $tokens, string $reason): int
    {
        Gate::forUser($actor)->authorize('changeRules', ProductCompletionCommission::class);
        $reason = $this->validatedReason($reason);
        if ($tokens === []) {
            throw ValidationException::withMessages(['bulk' => 'حداقل یک تسک را انتخاب کنید.']);
        }
        app(ProductCompletionStorageGuard::class)->assertTransactional();

        return DB::transaction(function () use ($actor, $userId, $rule, $tokens, $reason): int {
            $commissions = ProductCompletionCommission::query()->where('user_id', $userId)
                ->whereIn('public_id', array_keys($tokens))->orderBy('id')->lockForUpdate()->get();
            if ($commissions->count() !== count($tokens)) {
                throw ValidationException::withMessages(['bulk' => 'فهرست تسک‌ها تغییر کرده است؛ پیش‌نمایش را دوباره بگیرید.']);
            }
            $batchId = (string) Str::uuid();
            foreach ($commissions as $commission) {
                $this->applyChange($actor, $commission, $rule, $reason, (string) $tokens[$commission->public_id], $batchId);
            }

            return $commissions->count();
        }, 3);
    }

    private function buildPreview(User $actor, ProductCompletionCommission $commission, CommissionRule $rule, array $snapshot): array
    {
        Gate::forUser($actor)->authorize('changeRule', $commission);
        $task = $commission->task;
        $submission = $task?->lastSubmission;
        if ($commission->settlement_id !== null || ! in_array($commission->status, [
            ProductCompletionCommissionStatus::CALCULATED, ProductCompletionCommissionStatus::UNDER_REVIEW,
            ProductCompletionCommissionStatus::APPROVED, ProductCompletionCommissionStatus::PAYABLE,
        ], true) || $task?->status !== ProductCompletionTaskStatus::COMPLETED
            || $submission?->status !== ProductCompletionSubmissionStatus::APPROVED) {
            throw ValidationException::withMessages(['commission' => 'این پورسانت در وضعیت قابل تغییر نیست.']);
        }
        if (! $rule->is_active || $rule->task_type !== $task->task_type || $rule->currency !== $task->currency
            || ($rule->effective_from !== null && $rule->effective_from->isFuture())
            || ($rule->effective_until !== null && $rule->effective_until->isPast())) {
            throw ValidationException::withMessages(['rule' => 'قانون باید فعال، معتبر و هم‌نوع و هم‌ارز تسک باشد.']);
        }

        $pricingTask = clone $task;
        $pricingTask->commission_rule_snapshot_json = $snapshot;
        $result = $this->calculator->calculate($pricingTask, $submission, $commission->bonus_amount, $commission->penalty_amount, $commission->adjustment_amount);
        $token = hash('sha256', json_encode([
            $commission->getKey(), $commission->user_id, $commission->task_id, $commission->commission_rule_id,
            $commission->status->value, $commission->total_amount,
            $commission->breakdown_json, $task->lock_version, $task->commission_rule_snapshot_json,
            $submission->getKey(), $snapshot, $result,
        ], JSON_THROW_ON_ERROR));

        return ['old_total' => $commission->total_amount, 'result' => $result, 'snapshot' => $snapshot, 'token' => $token];
    }

    public function change(User $actor, ProductCompletionCommission $commission, CommissionRule $rule, string $reason, string $token): ProductCompletionCommission
    {
        $reason = $this->validatedReason($reason);
        app(ProductCompletionStorageGuard::class)->assertTransactional();

        return DB::transaction(fn () => $this->applyChange($actor, $commission, $rule, $reason, $token), 3);
    }

    private function validatedReason(string $reason): string
    {
        $reason = $this->sanitizer->plainText($reason, 2000, true);
        if ($reason === null) {
            throw ValidationException::withMessages(['reason' => 'ثبت دلیل تغییر قانون الزامی است.']);
        }

        return $reason;
    }

    private function applyChange(User $actor, ProductCompletionCommission $commission, CommissionRule $rule, string $reason, string $token, ?string $batchId = null): ProductCompletionCommission
    {
        $locked = ProductCompletionCommission::query()->lockForUpdate()->findOrFail($commission->getKey());
        $task = ProductCompletionTask::query()->lockForUpdate()->findOrFail($locked->task_id);
        $lockedRule = CommissionRule::query()->lockForUpdate()->findOrFail($rule->getKey());
        $locked->setRelation('task', $task->load('lastSubmission'));
        $preview = $this->buildPreview($actor, $locked, $lockedRule, $this->taskService->commissionRuleSnapshot($lockedRule));
        if (! hash_equals($preview['token'], $token)) {
            throw ValidationException::withMessages(['rule' => 'اطلاعات تغییر کرده است؛ پیش‌نمایش را دوباره محاسبه کنید.']);
        }
        $before = ['rule_id' => $task->commission_rule_id, 'snapshot' => $task->commission_rule_snapshot_json,
            'commission' => $locked->only(['base_amount', 'revision_amount', 'bonus_amount', 'penalty_amount', 'adjustment_amount', 'total_amount', 'breakdown_json', 'status'])];
        $result = $preview['result'];
        $breakdown = $result['breakdown'];
        $breakdown['approved_items_amount'] = $result['approved_items_amount'];
        ProductCompletionTask::query()->whereKey($task->getKey())->update([
            'commission_rule_id' => $lockedRule->getKey(),
            'commission_rule_snapshot_json' => json_encode($preview['snapshot'], JSON_THROW_ON_ERROR),
            'commission_rate_percentage' => $breakdown['task_rate_percentage'],
            'lock_version' => $task->lock_version + 1,
        ]);
        $locked->forceFill([
            'commission_rule_id' => $lockedRule->getKey(), 'base_amount' => $result['base_amount'],
            'revision_amount' => $result['revision_amount'], 'bonus_amount' => $result['bonus_amount'],
            'penalty_amount' => $result['penalty_amount'], 'adjustment_amount' => $result['adjustment_amount'],
            'total_amount' => $result['total_amount'], 'breakdown_json' => $breakdown,
            'status' => ProductCompletionCommissionStatus::CALCULATED, 'calculated_at' => now(),
            'approved_by' => null, 'approved_at' => null, 'payable_at' => null,
        ])->save();
        $this->auditService->record(action: 'commission_rule_changed', task: $task, actor: $actor,
            metadata: ['reason' => $reason, 'batch_id' => $batchId], before: $before,
            after: ['rule_id' => $lockedRule->getKey(), 'snapshot' => $preview['snapshot'],
                'commission' => $locked->only(['base_amount', 'revision_amount', 'bonus_amount', 'penalty_amount', 'adjustment_amount', 'total_amount', 'breakdown_json', 'status'])], entity: $locked);

        return $locked;
    }
}
