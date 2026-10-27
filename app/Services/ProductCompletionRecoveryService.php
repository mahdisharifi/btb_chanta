<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductCompletionSubmissionStatus;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionRevisionStatus;
use App\Models\Product;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductCompletionRecoveryService
{
    public function __construct(
        private readonly ProductCompletionAuditService $auditService,
        private readonly ProductCompletionStorageGuard $storageGuard,
    ) {
    }

    public function recover(User $actor, ProductCompletionTask $task, int $expectedLockVersion, string $reason): ProductCompletionTask
    {
        Gate::forUser($actor)->authorize('recoverInterruptedApproval', $task);
        $reason = trim($reason);
        Validator::make(['recoveryReason' => $reason], [
            'recoveryReason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'recoveryReason.required' => 'علت بازگردانی به بازبینی را وارد کنید.',
            'recoveryReason.min' => 'علت بازگردانی باید حداقل ۵ نویسه باشد.',
            'recoveryReason.max' => 'علت بازگردانی نباید بیشتر از ۲۰۰۰ نویسه باشد.',
        ])->validate();
        $this->storageGuard->assertTransactional();

        return DB::transaction(function () use ($actor, $task, $expectedLockVersion, $reason): ProductCompletionTask {
            $locked = ProductCompletionTask::query()->lockForUpdate()->findOrFail($task->getKey());
            Gate::forUser($actor)->authorize('recoverInterruptedApproval', $locked);
            if ($locked->lock_version !== $expectedLockVersion) {
                throw ValidationException::withMessages(['recovery' => 'وضعیت تسک تغییر کرده است؛ صفحه را تازه‌سازی کنید.']);
            }

            $submission = ProductCompletionSubmission::query()->lockForUpdate()->find($locked->last_submission_id);
            $product = Product::query()->lockForUpdate()->find($locked->product_id);
            if (! in_array($locked->status, [ProductCompletionTaskStatus::SUBMITTED, ProductCompletionTaskStatus::RESUBMITTED], true)
                || $product === null || $submission === null || $submission->task_id !== $locked->getKey()
                || $submission->status !== ProductCompletionSubmissionStatus::APPROVED
                || $locked->active_product_id !== $locked->product_id
                || $locked->approved_at !== null || $locked->content_applied_at !== null
                || $locked->media_published_at !== null || $locked->applied_product_version !== null
                || $locked->completed_at !== null || $locked->conflict_json !== null
                || $locked->commission()->exists()
                || $locked->revisions()->where(fn ($revision) => $revision
                    ->whereNotNull('open_task_id')->orWhere('status', ProductCompletionRevisionStatus::OPEN))->exists()
                || $locked->media()->withTrashed()->where(fn ($media) => $media
                    ->whereNotNull('published_at')->orWhereNotNull('published_product_gallery_id')->orWhere('status', 'published'))->exists()
                || $locked->operations()->whereIn('status', ['pending', 'processing'])->exists()
                || $locked->audits()->whereIn('action', ['submission_approved', 'approved_content_applied', 'product_completion_conflict_resolved'])->exists()) {
                throw ValidationException::withMessages(['recovery' => 'این تسک شرایط بازگردانی تأیید ناقص را ندارد؛ سوابق تأیید، انتشار و مالی باید بررسی شوند.']);
            }

            $fields = ['status', 'reviewed_by', 'reviewed_at', 'review_note', 'approval_selection_json', 'review_started_by', 'review_started_at'];
            $before = $submission->only($fields);
            $submission->forceFill([
                'status' => ProductCompletionSubmissionStatus::SUBMITTED,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
                'approval_selection_json' => null,
                'review_started_by' => null,
                'review_started_at' => null,
            ])->save();
            $locked->forceFill(['lock_version' => $locked->lock_version + 1])->save();
            $this->auditService->record(
                action: 'interrupted_approval_recovered', task: $locked, actor: $actor,
                metadata: ['reason' => $reason, 'submission_id' => $submission->getKey()],
                before: $before, after: $submission->only($fields), entity: $submission,
            );

            return $locked;
        }, 3);
    }
}
