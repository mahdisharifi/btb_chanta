<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionCommissionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductCompletionCommissionService
{
    public function __construct(
        private readonly ProductCompletionCommissionCalculator $calculator,
        private readonly ProductCompletionInputSanitizer $sanitizer,
        private readonly ProductCompletionAuditService $auditService,
    ) {
    }

    public function calculate(ProductCompletionTask $task): ProductCompletionCommission
    {
        return DB::transaction(function () use ($task): ProductCompletionCommission {
            $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            $existing = ProductCompletionCommission::query()
                ->where('task_id', $lockedTask->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($lockedTask->status !== ProductCompletionTaskStatus::APPROVED
                || $lockedTask->content_applied_at === null
                || $lockedTask->media_published_at === null) {
                throw ValidationException::withMessages([
                    'task' => 'پورسانت فقط پس از اعمال کامل محتوا و رسانه قابل محاسبه است.',
                ]);
            }

            if ($lockedTask->assigned_to === null) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'پیمانکار تسک مشخص نیست.',
                ]);
            }

            $submission = ProductCompletionSubmission::query()
                ->lockForUpdate()
                ->findOrFail($lockedTask->last_submission_id);

            if ($submission->status !== ProductCompletionSubmissionStatus::APPROVED) {
                throw ValidationException::withMessages([
                    'submission' => 'نسخه نهایی تأیید نشده است.',
                ]);
            }

            $calculated = $this->calculator->calculate($lockedTask, $submission);
            $breakdown = $calculated['breakdown'];
            $breakdown['approved_items_amount'] = $calculated['approved_items_amount'];
            $commission = ProductCompletionCommission::query()->create([
                'task_id' => $lockedTask->getKey(),
                'user_id' => $lockedTask->assigned_to,
                'commission_rule_id' => $lockedTask->commission_rule_id,
                'currency' => $lockedTask->currency,
                'base_amount' => $calculated['base_amount'],
                'revision_amount' => $calculated['revision_amount'],
                'bonus_amount' => $calculated['bonus_amount'],
                'penalty_amount' => $calculated['penalty_amount'],
                'adjustment_amount' => $calculated['adjustment_amount'],
                'total_amount' => $calculated['total_amount'],
                'breakdown_json' => $breakdown,
                'status' => ProductCompletionCommissionStatus::CALCULATED,
                'calculated_at' => now(),
            ]);

            $lockedTask->forceFill([
                'commission_rate_percentage' => $breakdown['task_rate_percentage'],
                'status' => ProductCompletionTaskStatus::COMPLETED,
                'active_product_id' => null,
                'completed_at' => now(),
                'lock_version' => $lockedTask->lock_version + 1,
            ])->save();
            $this->auditService->record(
                action: 'commission_calculated',
                task: $lockedTask,
                metadata: [
                    'commission_id' => $commission->getKey(),
                    'total_amount' => $commission->total_amount,
                    'currency' => $commission->currency,
                ],
                entity: $commission,
            );

            DB::afterCommit(static function () use ($lockedTask, $commission): void {
                \App\Events\TaskCompleted::dispatch($lockedTask);
                \App\Events\CommissionCalculated::dispatch($commission);
            });

            return $commission;
        }, 3);
    }

    public function approve(
        User $actor,
        ProductCompletionCommission $commission,
    ): ProductCompletionCommission {
        Gate::forUser($actor)->authorize('approveCommission', $commission);

        return DB::transaction(function () use ($actor, $commission): ProductCompletionCommission {
            $locked = ProductCompletionCommission::query()->lockForUpdate()->findOrFail($commission->getKey());
            Gate::forUser($actor)->authorize('approveCommission', $locked);
            $this->assertUnreservedStatus($locked, [ProductCompletionCommissionStatus::CALCULATED, ProductCompletionCommissionStatus::UNDER_REVIEW]);
            $locked->forceFill([
                'status' => ProductCompletionCommissionStatus::APPROVED,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ])->save();
            $this->auditFinancialChange($actor, $locked, 'commission_approved');
            DB::afterCommit(static fn () => \App\Events\CommissionApproved::dispatch($locked));

            return $locked;
        }, 3);
    }

    public function markPayable(
        User $actor,
        ProductCompletionCommission $commission,
        ?\DateTimeInterface $payableAt = null,
    ): ProductCompletionCommission {
        Gate::forUser($actor)->authorize('markPayable', $commission);

        return DB::transaction(function () use ($actor, $commission, $payableAt): ProductCompletionCommission {
            $locked = ProductCompletionCommission::query()->lockForUpdate()->findOrFail($commission->getKey());
            Gate::forUser($actor)->authorize('markPayable', $locked);
            $this->assertUnreservedStatus($locked, [ProductCompletionCommissionStatus::APPROVED]);
            $locked->forceFill([
                'status' => ProductCompletionCommissionStatus::PAYABLE,
                'payable_at' => $payableAt ?? now(),
            ])->save();
            $this->auditFinancialChange($actor, $locked, 'commission_marked_payable');

            return $locked;
        }, 3);
    }

    public function adjust(
        User $actor,
        ProductCompletionCommission $commission,
        string $bonus,
        string $penalty,
        string $adjustment,
        string $reason,
    ): ProductCompletionCommission {
        Gate::forUser($actor)->authorize('adjustCommission', $commission);
        $reason = $this->sanitizer->plainText($reason, 2000, true);

        Validator::make(
            compact('bonus', 'penalty', 'adjustment', 'reason'),
            [
                'bonus' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
                'penalty' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
                'adjustment' => ['required', 'regex:/^-?\d{1,12}(?:\.\d{1,2})?$/'],
                'reason' => ['required', 'string', 'max:2000'],
            ],
            [
                'reason.required' => 'دلیل اصلاح مبلغ الزامی است.',
                '*.regex' => 'فرمت مبلغ نامعتبر است یا بیش از دو رقم اعشار دارد.',
            ]
        )->validate();

        return DB::transaction(function () use (
            $actor,
            $commission,
            $bonus,
            $penalty,
            $adjustment,
            $reason,
        ): ProductCompletionCommission {
            $locked = ProductCompletionCommission::query()->lockForUpdate()->findOrFail($commission->getKey());
            Gate::forUser($actor)->authorize('adjustCommission', $locked);
            $this->assertUnreservedStatus($locked, [ProductCompletionCommissionStatus::CALCULATED, ProductCompletionCommissionStatus::UNDER_REVIEW]);
            $before = $this->amountSnapshot($locked);
            $approvedItemsAmount = (string) data_get(
                $locked->breakdown_json,
                'approved_items_amount',
                '0'
            );
            $maxAmount = data_get($locked->task->commission_rule_snapshot_json, 'max_amount');
            $total = $this->calculator->recalculateTotal(
                base: $locked->base_amount,
                revision: $locked->revision_amount,
                approvedItems: $approvedItemsAmount,
                bonus: $bonus,
                penalty: $penalty,
                adjustment: $adjustment,
                maxAmount: $maxAmount,
            );
            $locked->forceFill([
                'bonus_amount' => $bonus,
                'penalty_amount' => $penalty,
                'adjustment_amount' => $adjustment,
                'total_amount' => $total,
            ])->save();
            $this->auditService->record(
                action: 'commission_adjusted',
                task: $locked->task,
                actor: $actor,
                metadata: ['reason' => $reason],
                before: $before,
                after: $this->amountSnapshot($locked),
                entity: $locked,
            );

            return $locked;
        }, 3);
    }

    public function pay(
        User $actor,
        ProductCompletionCommission $commission,
        string $transactionId,
        string $idempotencyToken,
    ): ProductCompletionCommission {
        $transactionId = $this->sanitizer->plainText($transactionId, 255);
        $idempotencyKey = hash('sha256', trim($idempotencyToken));

        if ($transactionId === null || trim($idempotencyToken) === '') {
            throw ValidationException::withMessages([
                'payment' => 'شناسه تراکنش و شناسه یکتای پرداخت الزامی است.',
            ]);
        }

        Gate::forUser($actor)->authorize('outsourcing.pay_commission');

        if ($commission->status === ProductCompletionCommissionStatus::PAID
            && hash_equals((string) $commission->payment_idempotency_key, $idempotencyKey)) {
            return $commission;
        }

        Gate::forUser($actor)->authorize('payCommission', $commission);

        throw ValidationException::withMessages([
            'payment' => 'پرداخت جدید باید از صفحه تسویه تجمیعی پیمانکاران ثبت شود.',
        ]);
    }

    private function assertUnreservedStatus(ProductCompletionCommission $commission, array $statuses): void
    {
        if ($commission->settlement_id !== null || ! in_array($commission->status, $statuses, true)) {
            throw ValidationException::withMessages([
                'commission' => 'کارکرد رزروشده، پرداخت‌شده یا دارای وضعیت نامعتبر قابل تغییر نیست.',
            ]);
        }
    }

    private function auditFinancialChange(
        User $actor,
        ProductCompletionCommission $commission,
        string $action,
    ): void {
        $this->auditService->record(
            action: $action,
            task: $commission->task,
            actor: $actor,
            metadata: [
                'commission_id' => $commission->getKey(),
                'status' => $commission->status->value,
                'transaction_id' => $commission->transaction_id,
            ],
            entity: $commission,
        );
    }

    private function amountSnapshot(ProductCompletionCommission $commission): array
    {
        return [
            'base_amount' => $commission->base_amount,
            'revision_amount' => $commission->revision_amount,
            'bonus_amount' => $commission->bonus_amount,
            'penalty_amount' => $commission->penalty_amount,
            'adjustment_amount' => $commission->adjustment_amount,
            'total_amount' => $commission->total_amount,
            'currency' => $commission->currency,
        ];
    }
}
