<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionMediaStatus;
use App\Enums\ProductCompletionRevisionStatus;
use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionRevision;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductCompletionSubmissionWorkflow
{
    public function __construct(private readonly ProductCompletionAuditService $auditService) {}

    public function deadline(ProductCompletionSubmission $submission): ?\Illuminate\Support\Carbon
    {
        return $submission->submitted_at?->copy()->addMinutes(max(0, (int) config('product_completion.submission.withdrawal_minutes', 60)));
    }

    public function canWithdraw(User $actor, ProductCompletionTask $task, ProductCompletionSubmission $submission): bool
    {
        return Gate::forUser($actor)->allows('withdrawSubmission', $task)
            && $task->last_submission_id === $submission->getKey()
            && $submission->submitted_by === $actor->getKey()
            && $submission->status === ProductCompletionSubmissionStatus::SUBMITTED
            && $submission->review_started_at === null
            && $submission->reviewed_at === null
            && $this->deadline($submission)?->isFuture();
    }

    public function beginReview(User $actor, ProductCompletionTask $task, ?int $expectedSubmissionId = null): ProductCompletionTask
    {
        return DB::transaction(function () use ($actor, $task, $expectedSubmissionId): ProductCompletionTask {
            $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            Gate::forUser($actor)->authorize('viewTask', $lockedTask);
            if (! $lockedTask->status->canBeReviewed()) {
                throw ValidationException::withMessages(['submission' => 'این تسک دیگر نسخه‌ای در صف بررسی ندارد. فهرست را تازه‌سازی کنید.']);
            }
            Gate::forUser($actor)->authorize('reviewSubmission', $lockedTask);
            if ($expectedSubmissionId !== null && $lockedTask->last_submission_id !== $expectedSubmissionId) {
                throw ValidationException::withMessages(['submission' => 'نسخهٔ ارسالی تغییر کرده است. فهرست تسک‌ها را تازه‌سازی کنید.']);
            }
            $submission = ProductCompletionSubmission::query()->lockForUpdate()->findOrFail($lockedTask->last_submission_id);
            if (! in_array($submission->status, [ProductCompletionSubmissionStatus::SUBMITTED, ProductCompletionSubmissionStatus::UNDER_REVIEW], true)) {
                throw ValidationException::withMessages(['submission' => 'این نسخه دیگر قابل بررسی نیست. فهرست تسک‌ها را تازه‌سازی کنید.']);
            }
            if ($submission->status === ProductCompletionSubmissionStatus::UNDER_REVIEW) {
                return $lockedTask;
            }
            $submission->forceFill([
                'status' => ProductCompletionSubmissionStatus::UNDER_REVIEW,
                'review_started_by' => $actor->getKey(),
                'review_started_at' => now(),
            ])->save();
            $lockedTask->forceFill([
                'reviewer_id' => $lockedTask->reviewer_id ?? $actor->getKey(),
                'lock_version' => $lockedTask->lock_version + 1,
            ])->save();
            $this->auditService->record(action: 'submission_review_started', task: $lockedTask, actor: $actor,
                metadata: ['submission_id' => $submission->getKey()], entity: $submission);

            return $lockedTask;
        }, 3);
    }

    public function withdraw(User $actor, ProductCompletionTask $task, int $expectedSubmissionId): ProductCompletionTask
    {
        return DB::transaction(function () use ($actor, $task, $expectedSubmissionId): ProductCompletionTask {
            $lockedTask = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            Gate::forUser($actor)->authorize('outsourcing.submit_task');
            abort_unless($lockedTask->assigned_to === $actor->getKey(), 403);
            if (! $lockedTask->status->canBeReviewed()) {
                throw ValidationException::withMessages(['submission' => 'وضعیت تسک تغییر کرده و لغو این ارسال دیگر ممکن نیست.']);
            }
            Gate::forUser($actor)->authorize('withdrawSubmission', $lockedTask);
            if ($lockedTask->last_submission_id !== $expectedSubmissionId) {
                throw ValidationException::withMessages(['submission' => 'نسخهٔ ارسالی تغییر کرده است. صفحه را تازه‌سازی کنید.']);
            }
            $submission = ProductCompletionSubmission::query()->lockForUpdate()->findOrFail($expectedSubmissionId);
            if (! $this->canWithdraw($actor, $lockedTask, $submission)) {
                throw ValidationException::withMessages(['submission' => 'لغو ارسال ممکن نیست؛ مهلت یک‌ساعته تمام شده یا بازبین بررسی را شروع کرده است.']);
            }
            $context = $submission->submission_context_json;
            $previous = is_array($context) && array_key_exists('previous_submission_id', $context)
                ? ($context['previous_submission_id'] === null ? null : ProductCompletionSubmission::query()->lockForUpdate()->findOrFail($context['previous_submission_id']))
                : ProductCompletionSubmission::query()->where('task_id', $lockedTask->getKey())
                    ->where('version_no', '<', $submission->version_no)
                    ->where('status', '!=', ProductCompletionSubmissionStatus::WITHDRAWN->value)
                    ->orderByDesc('version_no')->lockForUpdate()->first();
            if ($previous !== null) {
                $previous->forceFill(['status' => $context['previous_submission_status'] ?? ProductCompletionSubmissionStatus::CHANGES_REQUESTED->value])->save();
            }
            $revision = ProductCompletionRevision::query()->where('task_id', $lockedTask->getKey())
                ->where('resolved_by_submission_id', $submission->getKey())->lockForUpdate()->first();
            if ($revision !== null) {
                $revision->forceFill([
                    'open_task_id' => $lockedTask->getKey(),
                    'status' => ProductCompletionRevisionStatus::OPEN,
                    'resolved_by_submission_id' => null,
                    'resolved_at' => null,
                    'changed_units_json' => null,
                    'changed_unit_count' => 0,
                ])->save();
            }
            $submission->forceFill(['status' => ProductCompletionSubmissionStatus::WITHDRAWN, 'withdrawn_at' => now()])->save();
            $mediaIds = $submission->mediaItems()->whereNotNull('media_id')->pluck('media_id');
            ProductCompletionMedia::query()->where('task_id', $lockedTask->getKey())->whereIn('id', $mediaIds)
                ->where('status', ProductCompletionMediaStatus::UNDER_REVIEW)
                ->update(['status' => ProductCompletionMediaStatus::TEMPORARY]);
            $lockedTask->forceFill([
                'status' => ProductCompletionTaskStatus::IN_PROGRESS,
                'last_submission_id' => $previous?->getKey(),
                'submitted_at' => $context['task_submitted_at'] ?? $previous?->submitted_at,
                'current_draft_json' => $submission->payload_json,
                'draft_version' => $lockedTask->draft_version + 1,
                'lock_version' => $lockedTask->lock_version + 1,
            ])->save();
            $this->auditService->record(action: 'submission_withdrawn', task: $lockedTask, actor: $actor,
                metadata: ['submission_id' => $submission->getKey(), 'previous_submission_id' => $previous?->getKey(),
                    'reopened_revision_id' => $revision?->getKey()], entity: $submission);

            return $lockedTask;
        }, 3);
    }
}
