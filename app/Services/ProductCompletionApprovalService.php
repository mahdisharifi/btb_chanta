<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ProductCompletionConflictResult;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Exceptions\ProductCompletionConflictException;
use App\Models\Product;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductCompletionApprovalService
{
    public function __construct(
        private readonly ProductCompletionConflictService $conflictService,
        private readonly ProductCompletionPublisher $publisher,
        private readonly ProductCompletionInputSanitizer $sanitizer,
        private readonly ProductCompletionAuditService $auditService,
        private readonly ProductCompletionCommissionCalculator $commissionCalculator,
    ) {
    }

    public function approve(
        User $actor,
        ProductCompletionSubmission $submission,
        ?string $reviewNote = null,
        bool $approveFullRate = false,
        ?array $approvedFields = null,
    ): ProductCompletionTask {
        Gate::forUser($actor)->authorize('approveSubmission', $submission);
        $reviewNote = $this->sanitizer->plainText($reviewNote, 2000, true);

        if ($approveFullRate) {
            Gate::forUser($actor)->authorize('outsourcing.approve_full_correction_rate');
        }

        if ($approveFullRate && $reviewNote === null) {
            throw ValidationException::withMessages([
                'reviewNote' => 'برای تأیید نرخ کامل، ثبت دلیل در یادداشت بررسی الزامی است.',
            ]);
        }

        $result = DB::transaction(function () use ($actor, $submission, $reviewNote, $approveFullRate, $approvedFields): array {
            $task = ProductCompletionTask::query()
                ->lockForUpdate()
                ->findOrFail($submission->task_id);
            $lockedSubmission = ProductCompletionSubmission::query()
                ->lockForUpdate()->findOrFail($submission->getKey());
            $product = Product::query()->lockForUpdate()->findOrFail($task->product_id);
            $lockedSubmission->setRelation('task', $task);

            Gate::forUser($actor)->authorize('approveSubmission', $lockedSubmission);
            $this->assertLatestSubmission($task, $lockedSubmission);

            $selection = $approvedFields === null ? null : array_values(array_unique(array_map('strval', $approvedFields)));
            if ($selection !== null && $selection === []) {
                throw ValidationException::withMessages(['approvedFields' => 'حداقل یک مورد باید برای اعمال انتخاب شود.']);
            }
            if ($selection !== null) {
                $lockedSubmission->approval_selection_json = ['mode' => 'selective', 'approved_fields' => $selection];
            }

            if ($approveFullRate) {
                Gate::forUser($actor)->authorize('outsourcing.approve_full_correction_rate');
            }

            $hasPersistedFullRateApproval = $task->full_rate_approved_by !== null
                && $task->full_rate_approved_at !== null
                && filled($task->full_rate_approval_reason);

            if ($approveFullRate) {
                $task->forceFill([
                    'commission_rate_percentage' => '100.00',
                    'full_rate_approved_by' => $actor->getKey(),
                    'full_rate_approved_at' => now(),
                    'full_rate_approval_reason' => $reviewNote,
                ]);
            }

            $conflict = $this->conflictService->detect($task, $product, $lockedSubmission->payload_json);

            if ($conflict->hasConflicts()) {
                $this->persistConflict(
                    $actor,
                    $task,
                    $lockedSubmission,
                    $conflict,
                    $reviewNote,
                );

                return ['task' => $task, 'conflicts' => $conflict->conflicts];
            }

            $lockedSubmission->forceFill([
                'status' => ProductCompletionSubmissionStatus::APPROVED,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
                'approval_selection_json' => $lockedSubmission->approval_selection_json,
            ])->save();
            $lockedSubmission->setRelation('task', $task);
            $lockedSubmission->approval_selection_json = $lockedSubmission->approval_selection_json;
            $task->setRelation('lastSubmission', $lockedSubmission);
            $task->forceFill([
                'reviewer_id' => $task->reviewer_id ?? $actor->getKey(),
                'status' => ProductCompletionTaskStatus::APPROVED,
                'commission_content_snapshot_json' => $task->commission_content_snapshot_json ?? $task->initial_snapshot_json,
                'commission_rate_percentage' => $this->calculateApprovedRate($task, $lockedSubmission, $selection),
                'full_rate_approved_by' => ($approveFullRate || $hasPersistedFullRateApproval)
                    ? ($task->full_rate_approved_by ?? $actor->getKey())
                    : null,
                'full_rate_approved_at' => ($approveFullRate || $hasPersistedFullRateApproval)
                    ? ($task->full_rate_approved_at ?? now())
                    : null,
                'full_rate_approval_reason' => ($approveFullRate || $hasPersistedFullRateApproval)
                    ? ($task->full_rate_approval_reason ?? $reviewNote)
                    : null,
                'approved_at' => now(),
                'conflict_json' => null,
                'lock_version' => $task->lock_version + 1,
            ])->save();

            $this->auditService->record(
                action: 'submission_approved',
                task: $task,
                actor: $actor,
                metadata: [
                    'submission_id' => $lockedSubmission->getKey(),
                    'version_no' => $lockedSubmission->version_no,
                    'product_version' => $product->content_version,
                    'commission_rate_percentage' => $task->commission_rate_percentage,
                    'full_rate_approved' => $task->full_rate_approved_by !== null,
                    'full_rate_reason' => $task->full_rate_approved_by !== null
                        ? $task->full_rate_approval_reason
                        : null,
                ],
                entity: $lockedSubmission,
            );

            DB::afterCommit(static function () use ($lockedSubmission, $task): void {
                \App\Events\SubmissionApproved::dispatch($lockedSubmission);
                \App\Jobs\FinalizeProductCompletionJob::dispatch($task->getKey());
            });

            return ['task' => $task, 'conflicts' => []];
        }, 3);

        if ($result['conflicts'] !== []) {
            throw new ProductCompletionConflictException($result['conflicts']);
        }

        return $result['task'];
    }

    private function calculateApprovedRate(ProductCompletionTask $task, ProductCompletionSubmission $submission, ?array $selection): string
    {
        if ($selection === null) {
            return $this->commissionCalculator->calculate($task, $submission)['breakdown']['task_rate_percentage'];
        }

        $filtered = clone $submission;
        $filtered->payload_json = $this->publisher->filterForApproval($submission->payload_json ?? [], $selection);

        return $this->commissionCalculator->calculate($task, $filtered)['breakdown']['task_rate_percentage'];
    }

    /**
     * Apply approved textual/attribute content. Media is finalized separately.
     */
    public function applyApprovedContent(
        ProductCompletionTask $task,
        ?User $actor = null,
    ): Product {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('outsourcing.approve_submission');
            Gate::forUser($actor)->authorize('viewTask', $task);
        }

        $result = DB::transaction(function () use ($task, $actor): array {
            $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            $product = Product::query()->lockForUpdate()->findOrFail($lockedTask->product_id);

            if ($lockedTask->content_applied_at !== null) {
                return ['product' => $product, 'conflicts' => []];
            }

            if ($lockedTask->status !== ProductCompletionTaskStatus::APPROVED) {
                throw ValidationException::withMessages([
                    'task' => 'فقط محتوای تسک تأییدشده قابل اعمال است.',
                ]);
            }

            $submission = ProductCompletionSubmission::query()
                ->lockForUpdate()
                ->findOrFail($lockedTask->last_submission_id);

            if ($submission->status !== ProductCompletionSubmissionStatus::APPROVED) {
                throw ValidationException::withMessages([
                    'submission' => 'نسخه نهایی هنوز تأیید نشده است.',
                ]);
            }

            $conflict = $this->conflictService->detect($lockedTask, $product, $submission->payload_json);

            if ($conflict->hasConflicts()) {
                $this->persistConflict($actor, $lockedTask, $submission, $conflict);

                return ['product' => $product, 'conflicts' => $conflict->conflicts];
            }

            $selection = data_get($submission->approval_selection_json, 'approved_fields');
            $product = $this->publisher->apply(
                task: $lockedTask,
                product: $product,
                payload: $conflict->mergedPayload,
                actor: $actor,
                approvedFields: is_array($selection) ? $selection : null,
            );
            $lockedTask->forceFill([
                'content_applied_at' => now(),
                'applied_product_version' => $product->content_version,
                'lock_version' => $lockedTask->lock_version + 1,
            ])->save();

            $this->auditService->record(
                action: 'approved_content_applied',
                task: $lockedTask,
                actor: $actor,
                metadata: [
                    'submission_id' => $submission->getKey(),
                    'applied_product_version' => $product->content_version,
                ],
                entity: $product,
            );

            return ['product' => $product, 'conflicts' => []];
        }, 3);

        if ($result['conflicts'] !== []) {
            throw new ProductCompletionConflictException($result['conflicts']);
        }

        return $result['product'];
    }

    public function reject(
        User $actor,
        ProductCompletionSubmission $submission,
        string $reason,
    ): ProductCompletionTask {
        Gate::forUser($actor)->authorize('rejectSubmission', $submission);
        $reason = $this->sanitizer->plainText($reason, 2000, true);

        if ($reason === null) {
            throw ValidationException::withMessages(['reason' => 'دلیل رد نسخه الزامی است.']);
        }

        return DB::transaction(function () use ($actor, $submission, $reason): ProductCompletionTask {
            $lockedSubmission = ProductCompletionSubmission::query()
                ->lockForUpdate()
                ->findOrFail($submission->getKey());
            $task = ProductCompletionTask::query()
                ->lockForUpdate()
                ->findOrFail($lockedSubmission->task_id);
            $lockedSubmission->setRelation('task', $task);

            Gate::forUser($actor)->authorize('rejectSubmission', $lockedSubmission);
            $this->assertLatestSubmission($task, $lockedSubmission);

            $lockedSubmission->forceFill([
                'status' => ProductCompletionSubmissionStatus::REJECTED,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_note' => $reason,
            ])->save();
            $task->forceFill([
                'status' => ProductCompletionTaskStatus::CANCELLED,
                'active_product_id' => null,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'lock_version' => $task->lock_version + 1,
            ])->save();

            $this->auditService->record(
                action: 'submission_rejected',
                task: $task,
                actor: $actor,
                metadata: ['submission_id' => $lockedSubmission->getKey()],
                entity: $lockedSubmission,
            );

            DB::afterCommit(static fn () => \App\Events\SubmissionRejected::dispatch($lockedSubmission));

            return $task;
        }, 3);
    }

    private function assertLatestSubmission(
        ProductCompletionTask $task,
        ProductCompletionSubmission $submission,
    ): void {
        if ($task->last_submission_id !== $submission->getKey()) {
            throw ValidationException::withMessages([
                'submission' => 'فقط آخرین نسخه ارسالی قابل بررسی است.',
            ]);
        }
    }

    private function persistConflict(
        ?User $actor,
        ProductCompletionTask $task,
        ProductCompletionSubmission $submission,
        ProductCompletionConflictResult $conflict,
        ?string $reviewNote = null,
    ): void {
        $submission->forceFill([
            'status' => ProductCompletionSubmissionStatus::CONFLICTED,
            'reviewed_by' => $actor?->getKey(),
            'reviewed_at' => now(),
            'review_note' => $reviewNote ?? $submission->review_note,
        ])->save();
        $task->forceFill([
            'reviewer_id' => $task->reviewer_id ?? $actor?->getKey(),
            'status' => ProductCompletionTaskStatus::CONFLICTED,
            'conflict_json' => $conflict->conflicts,
            'lock_version' => $task->lock_version + 1,
        ])->save();
        $this->auditService->record(
            action: 'product_completion_conflict_detected',
            task: $task,
            actor: $actor,
            metadata: [
                'submission_id' => $submission->getKey(),
                'fields' => array_keys($conflict->conflicts),
                'current_product_version' => $conflict->currentProductVersion,
                'commission_rate_percentage' => $task->commission_rate_percentage,
                'full_rate_approved' => $task->full_rate_approved_by !== null,
                'full_rate_reason' => $task->full_rate_approved_by !== null
                    ? $task->full_rate_approval_reason
                    : null,
            ],
            entity: $submission,
        );
    }
}
