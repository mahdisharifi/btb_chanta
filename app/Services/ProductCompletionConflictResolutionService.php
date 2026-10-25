<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionRevisionClassification;
use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Events\RevisionRequested;
use App\Events\SubmissionApproved;
use App\Exceptions\ProductCompletionRevisionLimitException;
use App\Jobs\FinalizeProductCompletionJob;
use App\Models\Product;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductCompletionConflictResolutionService
{
    public function __construct(
        private readonly ProductCompletionConflictService $conflictService,
        private readonly ProductCompletionSnapshotService $snapshotService,
        private readonly ProductCompletionInputSanitizer $sanitizer,
        private readonly ProductCompletionAuditService $auditService,
    ) {
    }

    public function retry(
        User $actor,
        ProductCompletionTask $task,
        int $expectedLockVersion,
    ): ProductCompletionTask {
        Gate::forUser($actor)->authorize('resolveConflict', $task);

        $result = DB::transaction(function () use ($actor, $task, $expectedLockVersion): array {
            $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            Gate::forUser($actor)->authorize('resolveConflict', $lockedTask);
            $this->assertLockVersion($lockedTask, $expectedLockVersion);

            $product = Product::query()->lockForUpdate()->findOrFail($lockedTask->product_id);
            $submission = ProductCompletionSubmission::query()
                ->lockForUpdate()
                ->findOrFail($lockedTask->last_submission_id);
            $this->assertConflictedSubmission($lockedTask, $submission);

            $conflict = $this->conflictService->detect(
                $lockedTask,
                $product,
                $submission->publicationPayload(),
            );

            if ($conflict->hasConflicts()) {
                $lockedTask->forceFill([
                    'conflict_json' => $conflict->conflicts,
                    'last_error' => 'تداخل محتوایی همچنان برقرار است.',
                    'lock_version' => $lockedTask->lock_version + 1,
                ])->save();
                $this->auditService->record(
                    action: 'product_completion_conflict_rechecked',
                    task: $lockedTask,
                    actor: $actor,
                    metadata: [
                        'resolved' => false,
                        'submission_id' => $submission->getKey(),
                        'fields' => array_keys($conflict->conflicts),
                        'current_product_version' => $conflict->currentProductVersion,
                    ],
                    entity: $submission,
                );

                return ['task' => $lockedTask, 'conflicts' => $conflict->conflicts];
            }

            if ($lockedTask->getRawOriginal('commission_content_snapshot_json') === null) {
                $lockedTask->commission_content_snapshot_json = $lockedTask->initial_snapshot_json;
            }
            app(ProductCompletionCommissionCalculator::class)->calculate($lockedTask, $submission);

            $submission->forceFill([
                'status' => ProductCompletionSubmissionStatus::APPROVED,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();
            $lockedTask->forceFill([
                'status' => ProductCompletionTaskStatus::APPROVED,
                'reviewer_id' => $lockedTask->reviewer_id ?? $actor->getKey(),
                'approved_at' => $lockedTask->approved_at ?? now(),
                'conflict_json' => null,
                'last_error' => null,
                'lock_version' => $lockedTask->lock_version + 1,
            ])->save();
            $this->auditService->record(
                action: 'product_completion_conflict_resolved',
                task: $lockedTask,
                actor: $actor,
                metadata: [
                    'resolution' => 'retry_after_external_resolution',
                    'submission_id' => $submission->getKey(),
                    'current_product_version' => $conflict->currentProductVersion,
                ],
                before: ['status' => ProductCompletionTaskStatus::CONFLICTED->value],
                after: ['status' => ProductCompletionTaskStatus::APPROVED->value],
                entity: $submission,
            );

            DB::afterCommit(static function () use ($submission, $lockedTask): void {
                SubmissionApproved::dispatch($submission);
                FinalizeProductCompletionJob::dispatch($lockedTask->getKey());
            });

            return ['task' => $lockedTask, 'conflicts' => []];
        }, 3);

        if ($result['conflicts'] !== []) {
            throw ValidationException::withMessages([
                'conflict' => 'تداخل هنوز برقرار است. تغییر هم‌زمان محصول را تعیین تکلیف کنید یا تسک را برای اصلاح بازگردانید.',
            ]);
        }

        return $result['task'];
    }

    public function requestRevision(
        User $actor,
        ProductCompletionTask $task,
        int $expectedLockVersion,
        string $reason,
    ): ProductCompletionRevision {
        Gate::forUser($actor)->authorize('resolveConflict', $task);
        app(ProductCompletionStorageGuard::class)->assertTransactional();
        $reason = $this->sanitizer->plainText($reason, 2000, true);

        if ($reason === null) {
            throw ValidationException::withMessages([
                'conflictResolutionReason' => 'توضیح موردنیاز برای اصلاح الزامی است.',
            ]);
        }

        return DB::transaction(function () use ($actor, $task, $expectedLockVersion, $reason): ProductCompletionRevision {
            $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            Gate::forUser($actor)->authorize('resolveConflict', $lockedTask);
            $this->assertLockVersion($lockedTask, $expectedLockVersion);

            $submission = ProductCompletionSubmission::query()
                ->lockForUpdate()
                ->findOrFail($lockedTask->last_submission_id);
            $this->assertConflictedSubmission($lockedTask, $submission);

            if ($lockedTask->revision_count >= $lockedTask->revision_limit) {
                throw new ProductCompletionRevisionLimitException();
            }

            if (ProductCompletionRevision::query()->where('open_task_id', $lockedTask->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'conflict' => 'برای این تسک یک درخواست اصلاح باز وجود دارد.',
                ]);
            }

            $requestedFields = $this->editableConflictFields($lockedTask);

            if ($requestedFields === []) {
                throw ValidationException::withMessages([
                    'conflict' => 'این تداخل مربوط به ساختار محصول یا تأمین‌کننده است و توسط پیمانکار قابل اصلاح نیست.',
                ]);
            }

            $product = Product::query()->lockForUpdate()->findOrFail($lockedTask->product_id);
            $publicationPayload = $submission->publicationPayload();
            $conflict = $this->conflictService->detect($lockedTask, $product, $publicationPayload);
            $draft = $conflict->mergedPayload;
            foreach ($conflict->conflicts as $field => $details) {
                if (array_key_exists($field, $publicationPayload)) {
                    $draft[$field] = $publicationPayload[$field];
                }
            }

            $revisionNo = $lockedTask->revision_count + 1;
            $duration = ProductCompletionRevisionService::correctionDuration($lockedTask);
            $revision = ProductCompletionRevision::query()->create([
                'task_id' => $lockedTask->getKey(),
                'open_task_id' => $lockedTask->getKey(),
                'submission_id' => $submission->getKey(),
                'requested_by' => $actor->getKey(),
                'requested_for' => $lockedTask->assigned_to,
                'revision_no' => $revisionNo,
                'classification' => ProductCompletionRevisionClassification::SYSTEM_CONFLICT,
                'status' => ProductCompletionRevisionStatus::OPEN,
                'is_commissionable' => false,
                'reason' => $reason,
                'requested_fields_json' => $requestedFields,
                'requested_at' => now(),
            ]);

            $submission->forceFill([
                'status' => ProductCompletionSubmissionStatus::CHANGES_REQUESTED,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_note' => $reason,
            ])->save();
            if ($lockedTask->getRawOriginal('commission_content_snapshot_json') === null) {
                $lockedTask->commission_content_snapshot_json = $lockedTask->initial_snapshot_json;
            }
            $lockedTask->forceFill([
                'status' => ProductCompletionTaskStatus::CHANGES_REQUESTED,
                'revision_count' => $revisionNo,
                'completion_duration_seconds' => $duration,
                'due_at' => now()->addSeconds($duration),
                'initial_snapshot_json' => $this->snapshotService->build($product, $lockedTask->scope_json),
                'product_version_at_assignment' => $product->content_version,
                'product_updated_at_assignment' => $product->updated_at,
                'current_draft_json' => $draft,
                'draft_version' => $lockedTask->draft_version + 1,
                'approved_at' => null,
                'content_applied_at' => null,
                'applied_product_version' => null,
                'media_published_at' => null,
                'conflict_json' => null,
                'last_error' => null,
                'lock_version' => $lockedTask->lock_version + 1,
            ])->save();
            $this->auditService->record(
                action: 'product_completion_conflict_returned_for_revision',
                task: $lockedTask,
                actor: $actor,
                metadata: [
                    'revision_id' => $revision->getKey(),
                    'submission_id' => $submission->getKey(),
                    'requested_fields' => $requestedFields,
                    'reason' => $reason,
                ],
                before: ['status' => ProductCompletionTaskStatus::CONFLICTED->value],
                after: ['status' => ProductCompletionTaskStatus::CHANGES_REQUESTED->value],
                entity: $revision,
            );

            DB::afterCommit(static fn () => RevisionRequested::dispatch($revision));

            return $revision;
        }, 3);
    }

    public function retryFinalization(
        User $actor,
        ProductCompletionTask $task,
        int $expectedLockVersion,
    ): ProductCompletionTask {
        Gate::forUser($actor)->authorize('retryFinalization', $task);

        return DB::transaction(function () use ($actor, $task, $expectedLockVersion): ProductCompletionTask {
            $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            Gate::forUser($actor)->authorize('retryFinalization', $lockedTask);
            $this->assertLockVersion($lockedTask, $expectedLockVersion);

            $lockedTask->forceFill([
                'last_error' => null,
                'lock_version' => $lockedTask->lock_version + 1,
            ])->save();
            $this->auditService->record(
                action: 'product_completion_finalization_retry_requested',
                task: $lockedTask,
                actor: $actor,
                metadata: [
                    'content_applied' => $lockedTask->content_applied_at !== null,
                    'media_published' => $lockedTask->media_published_at !== null,
                ],
                entity: $lockedTask,
            );

            DB::afterCommit(static fn () => FinalizeProductCompletionJob::dispatch($lockedTask->getKey()));

            return $lockedTask;
        }, 3);
    }

    private function assertLockVersion(ProductCompletionTask $task, int $expectedLockVersion): void
    {
        if ($task->lock_version !== $expectedLockVersion) {
            throw ValidationException::withMessages([
                'conflict' => 'وضعیت تسک تغییر کرده است؛ صفحه را تازه‌سازی کرده و دوباره تلاش کنید.',
            ]);
        }
    }

    private function assertConflictedSubmission(
        ProductCompletionTask $task,
        ProductCompletionSubmission $submission,
    ): void {
        if ($task->status !== ProductCompletionTaskStatus::CONFLICTED
            || $submission->getKey() !== $task->last_submission_id
            || $submission->status !== ProductCompletionSubmissionStatus::CONFLICTED) {
            throw ValidationException::withMessages([
                'conflict' => 'این تداخل قبلاً تعیین تکلیف شده یا نسخه ارسالی تغییر کرده است.',
            ]);
        }
    }

    /** @return array<int, string> */
    private function editableConflictFields(ProductCompletionTask $task): array
    {
        return collect(array_keys($task->conflict_json ?? []))
            ->filter(function (string $field) use ($task): bool {
                $scope = data_get($task->scope_json, $field, false);

                return $scope === true || is_array($scope);
            })
            ->values()
            ->all();
    }
}
