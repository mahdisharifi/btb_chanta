<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionRevisionClassification;
use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Exceptions\ProductCompletionRevisionLimitException;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductCompletionRevisionService
{
    public function __construct(
        private readonly ProductCompletionInputSanitizer $sanitizer,
        private readonly ProductCompletionAuditService $auditService,
    ) {
    }

    public function request(
        User $actor,
        ProductCompletionSubmission $submission,
        array $requestedFields,
        string $reason,
        ProductCompletionRevisionClassification $classification = ProductCompletionRevisionClassification::CONTRACTOR_ERROR,
    ): ProductCompletionRevision {
        Gate::forUser($actor)->authorize('requestRevision', $submission);

        if ($classification === ProductCompletionRevisionClassification::SUPPLIER_SCOPE_CHANGE) {
            Gate::forUser($actor)->authorize('outsourcing.approve_revision_commission');
        }

        $reason = $this->sanitizer->plainText($reason, 2000, true);
        $requestedFields = array_values(array_unique(array_map('strval', $requestedFields)));

        if ($reason === null) {
            throw ValidationException::withMessages(['reason' => 'دلیل درخواست اصلاح الزامی است.']);
        }

        if ($requestedFields === [] || count($requestedFields) > 50) {
            throw ValidationException::withMessages([
                'requested_fields' => 'حداقل یک و حداکثر پنجاه فیلد باید برای اصلاح انتخاب شود.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $submission,
            $requestedFields,
            $reason,
            $classification,
        ): ProductCompletionRevision {
            $lockedSubmission = ProductCompletionSubmission::query()
                ->lockForUpdate()
                ->findOrFail($submission->getKey());
            $task = ProductCompletionTask::query()
                ->lockForUpdate()
                ->findOrFail($lockedSubmission->task_id);
            $lockedSubmission->setRelation('task', $task);

            Gate::forUser($actor)->authorize('requestRevision', $lockedSubmission);

            if ($classification === ProductCompletionRevisionClassification::SUPPLIER_SCOPE_CHANGE) {
                Gate::forUser($actor)->authorize('outsourcing.approve_revision_commission');
            }

            $this->validateRequestedFields($task, $requestedFields);

            if ($task->revision_count >= $task->revision_limit) {
                throw new ProductCompletionRevisionLimitException();
            }

            if (ProductCompletionRevision::query()->where('open_task_id', $task->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'revision' => 'برای این تسک یک درخواست اصلاح باز وجود دارد.',
                ]);
            }

            $revisionNo = $task->revision_count + 1;
            $revision = ProductCompletionRevision::query()->create([
                'task_id' => $task->getKey(),
                'open_task_id' => $task->getKey(),
                'submission_id' => $lockedSubmission->getKey(),
                'requested_by' => $actor->getKey(),
                'requested_for' => $task->assigned_to,
                'revision_no' => $revisionNo,
                'classification' => $classification,
                'status' => ProductCompletionRevisionStatus::OPEN,
                // Only an approved supplier scope change earns an additional revision commission.
                'is_commissionable' => $classification === ProductCompletionRevisionClassification::SUPPLIER_SCOPE_CHANGE,
                'reason' => $reason,
                'requested_fields_json' => $requestedFields,
                'requested_at' => now(),
            ]);

            $lockedSubmission->forceFill([
                'status' => ProductCompletionSubmissionStatus::CHANGES_REQUESTED,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_note' => $reason,
            ])->save();

            $task->forceFill([
                'reviewer_id' => $task->reviewer_id ?? $actor->getKey(),
                'status' => ProductCompletionTaskStatus::CHANGES_REQUESTED,
                'revision_count' => $revisionNo,
                'lock_version' => $task->lock_version + 1,
            ])->save();

            $this->auditService->record(
                action: 'revision_requested',
                task: $task,
                actor: $actor,
                metadata: [
                    'revision_id' => $revision->getKey(),
                    'revision_no' => $revisionNo,
                    'classification' => $classification->value,
                    'is_commissionable' => $revision->is_commissionable,
                    'requested_fields' => $requestedFields,
                ],
                entity: $revision,
            );

            DB::afterCommit(static fn () => \App\Events\RevisionRequested::dispatch($revision));

            return $revision;
        }, 3);
    }

    private function validateRequestedFields(ProductCompletionTask $task, array $fields): void
    {
        $errors = [];
        $neverAllowed = config('product_completion.never_allowed_fields', []);

        foreach ($fields as $index => $field) {
            if (preg_match('/^[a-z_]+(?:\.[a-z_]+)?$/', $field) !== 1) {
                $errors["requested_fields.{$index}"] = 'نام فیلد درخواستی نامعتبر است.';

                continue;
            }

            $segments = explode('.', $field);
            if (array_intersect($segments, $neverAllowed) !== []) {
                $errors["requested_fields.{$index}"] = 'درخواست اصلاح برای این فیلد ممنوع است.';

                continue;
            }

            $rootScope = data_get($task->scope_json, $segments[0], false);
            $allowed = count($segments) === 1
                ? $rootScope === true || is_array($rootScope)
                : data_get($task->scope_json, $field, false) === true;

            if (! $allowed) {
                $errors["requested_fields.{$index}"] = 'فیلد درخواستی خارج از محدوده تسک است.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
