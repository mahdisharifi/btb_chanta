<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionReviewMode;
use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use App\Exceptions\InvalidProductCompletionTransitionException;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductCompletionTaskService
{
    public function __construct(
        private readonly ProductCompletionScopeDefinitionValidator $scopeValidator,
        private readonly ProductCompletionSnapshotService $snapshotService,
        private readonly ProductCompletionInputSanitizer $sanitizer,
        private readonly ProductCompletionAuditService $auditService,
    ) {
    }

    public function create(
        User $actor,
        Product $product,
        CommissionRule $commissionRule,
        ProductCompletionTaskType $taskType,
        ?array $scope = null,
        ?CarbonInterface $dueAt = null,
        ?ProductCompletionReviewMode $reviewMode = null,
        ?int $revisionLimit = null,
        bool $publishImmediately = false,
        ?int $completionDays = null,
    ): ProductCompletionTask {
        Gate::forUser($actor)->authorize('create', ProductCompletionTask::class);
        if ($publishImmediately) {
            Gate::forUser($actor)->authorize('outsourcing.publish_task');
        }

        $scope = $this->scopeValidator->resolve($taskType, $scope);
        $reviewMode ??= ProductCompletionReviewMode::from(
            (string) config('product_completion.defaults.review_mode', 'reviewer')
        );
        $revisionLimit ??= (int) config('product_completion.defaults.revision_limit', 3);
        $dueAt ??= now()->addDays((int) config('product_completion.defaults.due_days', 3));
        $completionSeconds = $completionDays !== null
            ? $completionDays * 86400
            : max(1, (int) now()->diffInSeconds($dueAt, false));
        if ($completionDays !== null && ($completionDays < 1 || $completionDays > 90)) {
            throw ValidationException::withMessages(['due_at' => 'مدت انجام باید بین یک تا نود روز باشد.']);
        }

        if ($revisionLimit < 0 || $revisionLimit > 100) {
            throw ValidationException::withMessages([
                'revision_limit' => 'سقف اصلاحات باید بین صفر تا صد باشد.',
            ]);
        }

        if ($dueAt->isPast()) {
            throw ValidationException::withMessages([
                'due_at' => 'مهلت انجام تسک باید در آینده باشد.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $product,
            $commissionRule,
            $taskType,
            $scope,
            $reviewMode,
            $revisionLimit,
            $completionSeconds,
            $publishImmediately,
        ): ProductCompletionTask {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $lockedRule = CommissionRule::query()->lockForUpdate()->findOrFail($commissionRule->getKey());
            $correctionRestrictionOverridden = $taskType === ProductCompletionTaskType::CORRECTION
                && ! $lockedProduct->allows_outsourcing_correction;

            if ($correctionRestrictionOverridden
                && ! Gate::forUser($actor)->allows('outsourcing.override_correction_restriction')) {
                throw ValidationException::withMessages([
                    'product_id' => 'ایجاد Task اصلاحی برای این محصول غیرفعال است.',
                ]);
            }

            $this->assertCommissionRuleAvailable($lockedRule, $taskType);

            if (ProductCompletionTask::query()->where('active_product_id', $lockedProduct->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'product_id' => 'برای این محصول یک تسک فعال دیگر وجود دارد.',
                ]);
            }

            $snapshot = $this->snapshotService->build($lockedProduct, $scope);
            $task = ProductCompletionTask::query()->create([
                'product_id' => $lockedProduct->getKey(),
                'active_product_id' => $lockedProduct->getKey(),
                'supplier_id' => $lockedProduct->supplier_id,
                'created_by' => $actor->getKey(),
                'task_type' => $taskType,
                'status' => ProductCompletionTaskStatus::DRAFT,
                'review_mode' => $reviewMode,
                'scope_schema_version' => (int) config('product_completion.schema_version', 1),
                'scope_json' => $scope,
                'initial_snapshot_json' => $snapshot,
                'commission_content_snapshot_json' => $snapshot,
                'current_draft_json' => $this->snapshotService->draftFromSnapshot($snapshot),
                'draft_version' => 0,
                'lock_version' => 0,
                'product_version_at_assignment' => (int) $lockedProduct->content_version,
                'product_updated_at_assignment' => $lockedProduct->updated_at,
                'commission_rule_id' => $lockedRule->getKey(),
                'commission_rule_snapshot_json' => $this->commissionRuleSnapshot($lockedRule),
                'quoted_base_amount' => $lockedRule->base_amount,
                'currency' => $lockedRule->currency,
                'revision_limit' => $revisionLimit,
                'revision_count' => 0,
                'commission_rate_percentage' => '100.00',
                'due_at' => null,
                'completion_duration_seconds' => $completionSeconds,
            ]);

            $this->auditService->record(
                action: 'task_created',
                task: $task,
                actor: $actor,
                metadata: [
                    'task_type' => $taskType->value,
                    'review_mode' => $reviewMode->value,
                    'revision_limit' => $revisionLimit,
                    'completion_duration_seconds' => $completionSeconds,
                    'commission_rate_percentage' => $task->commission_rate_percentage,
                    'correction_restriction_overridden' => $correctionRestrictionOverridden,
                ],
                entity: $task,
            );

            DB::afterCommit(static fn () => \App\Events\TaskCreated::dispatch($task));

            return $publishImmediately ? $this->publish($actor, $task) : $task;
        }, 3);
    }

    public function publish(User $actor, ProductCompletionTask $task): ProductCompletionTask
    {
        Gate::forUser($actor)->authorize('publishTask', $task);

        return DB::transaction(function () use ($actor, $task): ProductCompletionTask {
            $locked = $this->lockTask($task);
            Gate::forUser($actor)->authorize('publishTask', $locked);
            if ($locked->status !== ProductCompletionTaskStatus::DRAFT
                || $locked->published_at !== null || $locked->assigned_to !== null) {
                throw ValidationException::withMessages([
                    'task' => 'فقط پیش‌نویس منتشرنشده و بدون پیمانکار قابل انتشار است.',
                ]);
            }

            return $this->transition($actor, $locked, ProductCompletionTaskStatus::AVAILABLE, [
                'published_at' => now(),
            ], 'task_published', 'publishTask');
        }, 3);
    }

    public function assign(
        User $actor,
        ProductCompletionTask $task,
        User $assignee,
        ?User $reviewer = null,
    ): ProductCompletionTask {
        Gate::forUser($actor)->authorize('assignTask', $task);

        if (! $assignee->can('outsourcing.accept_task')) {
            throw ValidationException::withMessages([
                'assigned_to' => 'کاربر انتخاب‌شده مجوز پذیرش تسک را ندارد.',
            ]);
        }

        if ($task->review_mode === ProductCompletionReviewMode::REVIEWER && $reviewer === null) {
            throw ValidationException::withMessages([
                'reviewer_id' => 'برای این تسک انتخاب بازبین الزامی است.',
            ]);
        }

        if ($reviewer !== null && ! $reviewer->can('outsourcing.review_submission')) {
            throw ValidationException::withMessages([
                'reviewer_id' => 'کاربر انتخاب‌شده مجوز بررسی محتوا را ندارد.',
            ]);
        }

        return DB::transaction(function () use ($actor, $task, $assignee, $reviewer): ProductCompletionTask {
            $locked = $this->lockTask($task);
            Gate::forUser($actor)->authorize('assignTask', $locked);

            $previousStatus = $locked->status;
            $nextStatus = ProductCompletionTaskStatus::ASSIGNED;

            if (! $previousStatus->canTransitionTo($nextStatus) && $previousStatus !== $nextStatus) {
                throw InvalidProductCompletionTransitionException::between($previousStatus, $nextStatus);
            }

            $locked->fill([
                'active_product_id' => $locked->product_id,
                'assigned_by' => $actor->getKey(),
                'assigned_to' => $assignee->getKey(),
                'reviewer_id' => $reviewer?->getKey(),
                'status' => $nextStatus,
                'assigned_at' => now(),
                'assignment_expires_at' => now()->addHours(
                    (int) config('product_completion.defaults.assignment_expiration_hours', 24)
                ),
                'accepted_at' => null,
                'due_at' => $locked->completion_duration_seconds !== null ? null : $locked->due_at,
                'started_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->auditStatusChange($actor, $locked, $previousStatus, 'task_assigned', [
                'assigned_to' => $assignee->getKey(),
                'reviewer_id' => $reviewer?->getKey(),
            ]);

            return $locked;
        }, 3);
    }

    public function claimAvailable(User $actor, ProductCompletionTask $task): ProductCompletionTask
    {
        Gate::forUser($actor)->authorize('claimAvailableTask', $task);

        return DB::transaction(function () use ($actor, $task): ProductCompletionTask {
            $locked = $this->lockTask($task);
            Gate::forUser($actor)->authorize('claimAvailableTask', $locked);

            $previousStatus = $locked->status;
            $this->assertTransition($previousStatus, ProductCompletionTaskStatus::ACCEPTED);
            $acceptedAt = now();
            $locked->forceFill([
                'active_product_id' => $locked->product_id,
                'assigned_by' => $actor->getKey(),
                'assigned_to' => $actor->getKey(),
                'status' => ProductCompletionTaskStatus::ACCEPTED,
                'assigned_at' => now(),
                'assignment_expires_at' => null,
                'accepted_at' => $acceptedAt,
                'due_at' => $locked->completion_duration_seconds !== null
                    ? $acceptedAt->copy()->addSeconds($locked->completion_duration_seconds) : $locked->due_at,
                'started_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->auditStatusChange($actor, $locked, $previousStatus, 'task_claimed', [
                'assigned_to' => $actor->getKey(),
            ]);

            DB::afterCommit(static fn () => \App\Events\TaskAccepted::dispatch($locked));

            return $locked;
        }, 3);
    }

    public function accept(User $actor, ProductCompletionTask $task): ProductCompletionTask
    {
        Gate::forUser($actor)->authorize('acceptTask', $task);

        return $this->transition($actor, $task, ProductCompletionTaskStatus::ACCEPTED, [
            'accepted_at' => now(),
        ], 'task_accepted', 'acceptTask');
    }

    public function start(User $actor, ProductCompletionTask $task): ProductCompletionTask
    {
        Gate::forUser($actor)->authorize('startTask', $task);

        return $this->transition($actor, $task, ProductCompletionTaskStatus::IN_PROGRESS, [
            'started_at' => now(),
        ], 'task_started', 'startTask');
    }

    public function resumeRevision(User $actor, ProductCompletionTask $task): ProductCompletionTask
    {
        Gate::forUser($actor)->authorize('resumeTask', $task);

        return DB::transaction(function () use ($actor, $task): ProductCompletionTask {
            $locked = $this->lockTask($task);
            Gate::forUser($actor)->authorize('resumeTask', $locked);

            if (! $locked->revisions()->where('open_task_id', $locked->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'revision' => 'درخواست اصلاح بازی برای این تسک وجود ندارد.',
                ]);
            }

            $previousStatus = $locked->status;
            $this->assertTransition($previousStatus, ProductCompletionTaskStatus::IN_PROGRESS);
            $locked->forceFill([
                'status' => ProductCompletionTaskStatus::IN_PROGRESS,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->auditStatusChange($actor, $locked, $previousStatus, 'revision_work_started');

            return $locked;
        }, 3);
    }

    public function cancel(User $actor, ProductCompletionTask $task, string $reason): ProductCompletionTask
    {
        Gate::forUser($actor)->authorize('cancelTask', $task);
        $reason = $this->sanitizer->plainText($reason, 2000, true);

        if ($reason === null) {
            throw ValidationException::withMessages(['reason' => 'دلیل لغو الزامی است.']);
        }

        return DB::transaction(function () use ($actor, $task, $reason): ProductCompletionTask {
            $locked = $this->lockTask($task);
            Gate::forUser($actor)->authorize('cancelTask', $locked);
            $previousStatus = $locked->status;
            $this->assertTransition($previousStatus, ProductCompletionTaskStatus::CANCELLED);

            $openRevision = ProductCompletionRevision::query()
                ->where('open_task_id', $locked->getKey())
                ->lockForUpdate()
                ->first();

            if ($openRevision !== null) {
                $openRevision->forceFill([
                    'open_task_id' => null,
                    'status' => ProductCompletionRevisionStatus::CANCELLED,
                    'resolved_at' => now(),
                ])->save();
            }

            $locked->forceFill([
                'status' => ProductCompletionTaskStatus::CANCELLED,
                'active_product_id' => null,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->auditStatusChange($actor, $locked, $previousStatus, 'task_cancelled', [
                'cancelled_revision_id' => $openRevision?->getKey(),
                'reason' => $reason,
            ]);

            return $locked;
        }, 3);
    }

    public function expire(ProductCompletionTask $task): ProductCompletionTask
    {
        return DB::transaction(function () use ($task): ProductCompletionTask {
            $locked = $this->lockTask($task);

            if (! in_array($locked->status, [
                ProductCompletionTaskStatus::AVAILABLE,
                ProductCompletionTaskStatus::ASSIGNED,
                ProductCompletionTaskStatus::ACCEPTED,
                ProductCompletionTaskStatus::IN_PROGRESS,
            ], true)) {
                return $locked;
            }

            $isExpired = ($locked->due_at !== null && $locked->due_at->isPast())
                || ($locked->assignment_expires_at !== null
                    && $locked->accepted_at === null
                    && $locked->assignment_expires_at->isPast());

            if (! $isExpired) {
                return $locked;
            }

            $previousStatus = $locked->status;
            $this->assertTransition($previousStatus, ProductCompletionTaskStatus::EXPIRED);
            $locked->fill([
                'status' => ProductCompletionTaskStatus::EXPIRED,
                'active_product_id' => null,
                'expired_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->auditStatusChange(null, $locked, $previousStatus, 'task_expired');

            return $locked;
        }, 3);
    }

    public function extendDeadline(
        User $actor,
        ProductCompletionTask $task,
        CarbonInterface $dueAt,
    ): ProductCompletionTask {
        Gate::forUser($actor)->authorize('extendDeadline', $task);

        if ($dueAt->isPast()) {
            throw ValidationException::withMessages(['due_at' => 'مهلت جدید باید در آینده باشد.']);
        }

        return DB::transaction(function () use ($actor, $task, $dueAt): ProductCompletionTask {
            $locked = $this->lockTask($task);
            Gate::forUser($actor)->authorize('extendDeadline', $locked);
            $before = $locked->due_at?->toAtomString();
            $locked->forceFill([
                'due_at' => $locked->accepted_at === null && $locked->completion_duration_seconds !== null ? null : $dueAt,
                'completion_duration_seconds' => $locked->accepted_at === null && $locked->completion_duration_seconds !== null
                    ? max(1, (int) now()->diffInSeconds($dueAt, false)) : $locked->completion_duration_seconds,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->auditService->record(
                action: 'task_deadline_extended',
                task: $locked,
                actor: $actor,
                metadata: ['before' => $before, 'after' => $locked->due_at?->toAtomString(),
                    'completion_duration_seconds' => $locked->completion_duration_seconds],
                entity: $locked,
            );

            return $locked;
        }, 3);
    }

    public function extendRevisionLimit(
        User $actor,
        ProductCompletionTask $task,
        int $revisionLimit,
    ): ProductCompletionTask {
        Gate::forUser($actor)->authorize('extendRevisionLimit', $task);

        if ($revisionLimit <= $task->revision_limit || $revisionLimit > 100) {
            throw ValidationException::withMessages([
                'revision_limit' => 'سقف جدید باید بیشتر از سقف فعلی و حداکثر صد باشد.',
            ]);
        }

        return DB::transaction(function () use ($actor, $task, $revisionLimit): ProductCompletionTask {
            $locked = $this->lockTask($task);
            Gate::forUser($actor)->authorize('extendRevisionLimit', $locked);

            if ($revisionLimit <= $locked->revision_limit) {
                throw ValidationException::withMessages([
                    'revision_limit' => 'سقف جدید باید بیشتر از سقف فعلی باشد.',
                ]);
            }

            $before = $locked->revision_limit;
            $locked->forceFill([
                'revision_limit' => $revisionLimit,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->auditService->record(
                action: 'task_revision_limit_extended',
                task: $locked,
                actor: $actor,
                metadata: ['before' => $before, 'after' => $revisionLimit],
                entity: $locked,
            );

            return $locked;
        }, 3);
    }

    private function transition(
        ?User $actor,
        ProductCompletionTask $task,
        ProductCompletionTaskStatus $nextStatus,
        array $attributes,
        string $auditAction,
        ?string $ability = null,
    ): ProductCompletionTask {
        return DB::transaction(function () use (
            $actor,
            $task,
            $nextStatus,
            $attributes,
            $auditAction,
            $ability,
        ): ProductCompletionTask {
            $locked = $this->lockTask($task);

            if ($actor !== null && $ability !== null) {
                Gate::forUser($actor)->authorize($ability, $locked);
            }

            $previousStatus = $locked->status;
            $this->assertTransition($previousStatus, $nextStatus);
            if ($nextStatus === ProductCompletionTaskStatus::ACCEPTED && $locked->completion_duration_seconds !== null) {
                $attributes['accepted_at'] = now();
                $attributes['due_at'] = $attributes['accepted_at']->copy()->addSeconds($locked->completion_duration_seconds);
            }
            $locked->fill($attributes + [
                'status' => $nextStatus,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->auditStatusChange($actor, $locked, $previousStatus, $auditAction);

            return $locked;
        }, 3);
    }

    private function lockTask(ProductCompletionTask $task): ProductCompletionTask
    {
        return ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
    }

    private function assertTransition(
        ProductCompletionTaskStatus $current,
        ProductCompletionTaskStatus $next,
    ): void {
        if (! $current->canTransitionTo($next)) {
            throw InvalidProductCompletionTransitionException::between($current, $next);
        }
    }

    private function assertCommissionRuleAvailable(
        CommissionRule $rule,
        ProductCompletionTaskType $taskType,
    ): void {
        if (! $rule->is_active || $rule->task_type !== $taskType) {
            throw ValidationException::withMessages([
                'commission_rule_id' => 'قانون پورسانت برای این نوع تسک فعال نیست.',
            ]);
        }

        if (($rule->effective_from !== null && $rule->effective_from->isFuture())
            || ($rule->effective_until !== null && $rule->effective_until->isPast())) {
            throw ValidationException::withMessages([
                'commission_rule_id' => 'قانون پورسانت در بازه زمانی جاری معتبر نیست.',
            ]);
        }
    }

    private function commissionRuleSnapshot(CommissionRule $rule): array
    {
        $pricingSchemaVersion = (int) ($rule->pricing_schema_version ?? 1);

        return [
            'pricing_schema_version' => $pricingSchemaVersion,
            'rule_id' => $rule->getKey(),
            'rule_version' => $rule->version,
            'task_type' => $rule->task_type->value,
            'calculation_type' => $rule->calculation_type->value,
            'revision_policy' => $rule->revision_policy->value,
            'revision_percentage' => $rule->revision_percentage ?? '0.00',
            'base_amount' => $rule->base_amount,
            'per_revision_amount' => $rule->per_revision_amount,
            'per_approved_item_amount' => $rule->per_approved_item_amount,
            'max_amount' => $rule->max_amount,
            'currency' => $rule->currency,
            'conditions' => $rule->conditions_json,
            'items' => $pricingSchemaVersion >= 2
                ? $rule->items()
                    ->orderBy('content_type')
                    ->get()
                    ->map(static fn ($item): array => [
                        'content_type' => $item->content_type->value,
                        'unit_amount' => $item->unit_amount,
                        'max_payable_units' => $item->max_payable_units,
                        'is_enabled' => $item->is_enabled,
                    ])
                    ->all()
                : [],
        ];
    }

    private function auditStatusChange(
        ?User $actor,
        ProductCompletionTask $task,
        ProductCompletionTaskStatus $before,
        string $action,
        array $metadata = [],
    ): void {
        $this->auditService->record(
            action: $action,
            task: $task,
            actor: $actor,
            metadata: $metadata,
            before: ['status' => $before->value],
            after: ['status' => $task->status->value],
            fieldName: 'status',
            entity: $task,
        );

        DB::afterCommit(static function () use ($action, $task): void {
            match ($action) {
                'task_assigned' => \App\Events\TaskAssigned::dispatch($task),
                'task_accepted' => \App\Events\TaskAccepted::dispatch($task),
                'task_started' => \App\Events\TaskStarted::dispatch($task),
                default => null,
            };
        });
    }
}
