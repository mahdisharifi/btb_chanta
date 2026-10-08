<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Exceptions\InvalidProductCompletionTransitionException;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ProductCompletionSubmissionService
{
    public function __construct(
        private readonly ProductCompletionDraftValidator $validator,
        private readonly ProductCompletionSnapshotService $snapshotService,
        private readonly ProductCompletionDiffService $diffService,
        private readonly ProductCompletionMediaService $mediaService,
        private readonly ProductCompletionCommissionUnitCounter $commissionUnitCounter,
        private readonly ProductCompletionAuditService $auditService,
    ) {
    }

    public function submit(
        User $actor,
        ProductCompletionTask $task,
        int $expectedDraftVersion,
        string $idempotencyToken,
    ): ProductCompletionSubmission {
        Gate::forUser($actor)->authorize('outsourcing.submit_task');

        if (trim($idempotencyToken) === '') {
            throw ValidationException::withMessages([
                'idempotency_token' => 'شناسه یکتای ارسال الزامی است.',
            ]);
        }

        $idempotencyKey = hash('sha256', trim($idempotencyToken));
        $existingSubmission = ProductCompletionSubmission::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('task_id', $task->getKey())
            ->where('submitted_by', $actor->getKey())
            ->first();

        if ($existingSubmission !== null) {
            return $existingSubmission;
        }

        $rateLimitKey = "product-completion:submission:{$actor->getKey()}:{$task->getKey()}";
        $maxAttempts = (int) config('product_completion.rate_limits.submissions_per_minute', 10);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            throw ValidationException::withMessages([
                'submission' => 'تعداد ارسال‌ها بیش از حد مجاز است؛ یک دقیقه بعد دوباره تلاش کنید.',
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);
        Gate::forUser($actor)->authorize('submitTask', $task);

        return DB::transaction(function () use (
            $actor,
            $task,
            $expectedDraftVersion,
            $idempotencyKey,
        ): ProductCompletionSubmission {
            $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());

            $existing = ProductCompletionSubmission::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ($existing->task_id === $lockedTask->getKey()
                    && $existing->submitted_by === $actor->getKey()) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'idempotency_token' => 'شناسه ارسال قبلاً استفاده شده است.',
                ]);
            }

            Gate::forUser($actor)->authorize('submitTask', $lockedTask);

            if ($lockedTask->draft_version !== $expectedDraftVersion) {
                throw ValidationException::withMessages([
                    'draft_version' => 'نسخه پیش‌نویس تغییر کرده است؛ ابتدا آخرین نسخه را دریافت کنید.',
                ]);
            }

            $draft = $lockedTask->current_draft_json ?? [];
            $validatedDraft = $this->validator
                ->validate($lockedTask, $draft, $actor, true)
                ->toArray();
            $previousSubmission = $lockedTask->last_submission_id === null
                ? null
                : ProductCompletionSubmission::query()->lockForUpdate()->find($lockedTask->last_submission_id);
            $before = $previousSubmission?->payload_json
                ?? $this->snapshotService->draftFromSnapshot($lockedTask->initial_snapshot_json);
            $versionNo = ((int) ProductCompletionSubmission::query()
                ->where('task_id', $lockedTask->getKey())
                ->max('version_no')) + 1;
            $nextTaskStatus = $versionNo === 1
                ? ProductCompletionTaskStatus::SUBMITTED
                : ProductCompletionTaskStatus::RESUBMITTED;

            if (! $lockedTask->status->canTransitionTo($nextTaskStatus)) {
                throw InvalidProductCompletionTransitionException::between(
                    $lockedTask->status,
                    $nextTaskStatus
                );
            }

            $submission = ProductCompletionSubmission::query()->create([
                'task_id' => $lockedTask->getKey(),
                'submitted_by' => $actor->getKey(),
                'version_no' => $versionNo,
                'draft_version' => $lockedTask->draft_version,
                'payload_json' => $validatedDraft,
                'diff_json' => $this->diffService->diff($before, $validatedDraft),
                'media_manifest_json' => $validatedDraft['gallery'] ?? null,
                'payload_hash' => $this->diffService->hash($validatedDraft),
                'base_product_version' => $lockedTask->product_version_at_assignment,
                'idempotency_key' => $idempotencyKey,
                'status' => ProductCompletionSubmissionStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            $this->mediaService->freezeManifest(
                $submission,
                $validatedDraft['gallery'] ?? null,
            );

            if ($previousSubmission !== null) {
                $previousSubmission->forceFill([
                    'status' => ProductCompletionSubmissionStatus::SUPERSEDED,
                ])->save();
            }

            $openRevision = ProductCompletionRevision::query()
                ->where('open_task_id', $lockedTask->getKey())
                ->lockForUpdate()
                ->first();

            if ($openRevision !== null) {
                $pricingSchemaVersion = (int) data_get(
                    $lockedTask->commission_rule_snapshot_json,
                    'pricing_schema_version',
                    1,
                );
                $revisionOrigin = $previousSubmission?->getKey() === $openRevision->submission_id
                    ? $previousSubmission
                    : ProductCompletionSubmission::query()
                        ->lockForUpdate()
                        ->findOrFail($openRevision->submission_id);
                $changedUnits = $pricingSchemaVersion >= 2
                    ? $this->commissionUnitCounter->compare(
                        $revisionOrigin->payload_json ?? [],
                        $submission->payload_json ?? [],
                    )
                    : null;

                $openRevision->forceFill([
                    'open_task_id' => null,
                    'status' => ProductCompletionRevisionStatus::RESOLVED,
                    'resolved_by_submission_id' => $submission->getKey(),
                    'pricing_schema_version' => $pricingSchemaVersion,
                    'changed_units_json' => $changedUnits,
                    'changed_unit_count' => $changedUnits === null
                        ? 0
                        : array_sum(array_map('count', $changedUnits)),
                    'resolved_at' => now(),
                ])->save();
            }

            $lockedTask->forceFill([
                'status' => $nextTaskStatus,
                'last_submission_id' => $submission->getKey(),
                'submitted_at' => now(),
                'lock_version' => $lockedTask->lock_version + 1,
            ])->save();

            $this->auditService->record(
                action: $versionNo === 1 ? 'submission_created' : 'submission_resubmitted',
                task: $lockedTask,
                actor: $actor,
                metadata: [
                    'submission_id' => $submission->getKey(),
                    'version_no' => $versionNo,
                    'draft_version' => $lockedTask->draft_version,
                    'payload_hash' => $submission->payload_hash,
                ],
                entity: $submission,
            );

            DB::afterCommit(static fn () => \App\Events\SubmissionCreated::dispatch($submission));

            return $submission;
        }, 3);
    }
}
