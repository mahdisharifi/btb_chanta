<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProductCompletionReviewMode;
use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionTask;
use App\Models\User;

class ProductCompletionTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'outsourcing.view_tasks',
            'outsourcing.view_all_tasks',
            'outsourcing.view_supplier_tasks',
        ]);
    }

    public function view(User $user, ProductCompletionTask $task): bool
    {
        return $this->viewTask($user, $task);
    }

    public function viewTask(User $user, ProductCompletionTask $task): bool
    {
        if ($user->can('outsourcing.view_all_tasks')) {
            return true;
        }

        if ($user->can('outsourcing.view_tasks') && $task->assigned_to === $user->getKey()) {
            return true;
        }

        if ($user->can('outsourcing.view_tasks') && $task->reviewer_id === $user->getKey()) {
            return true;
        }

        if ($this->isOpenReviewerQueueTask($user, $task)) {
            return true;
        }

        return $user->can('outsourcing.view_supplier_tasks')
            && $this->belongsToUsersSupplier($user, $task);
    }

    public function viewProductDraft(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.view_product_draft') && $this->viewTask($user, $task);
    }

    public function claimAvailableTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.accept_task')
            && $task->status === ProductCompletionTaskStatus::AVAILABLE
            && $task->assigned_to === null
            && ($task->due_at === null || $task->due_at->isFuture());
    }

    public function acceptTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.accept_task')
            && $task->status === ProductCompletionTaskStatus::ASSIGNED
            && $task->assigned_to === $user->getKey()
            && ($task->assignment_expires_at === null || $task->assignment_expires_at->isFuture())
            && ($task->due_at === null || $task->due_at->isFuture());
    }

    public function startTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.start_task')
            && $task->status === ProductCompletionTaskStatus::ACCEPTED
            && $task->assigned_to === $user->getKey()
            && ($task->due_at === null || $task->due_at->isFuture());
    }

    public function resumeTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.start_task')
            && $task->status === ProductCompletionTaskStatus::CHANGES_REQUESTED
            && $task->assigned_to === $user->getKey()
            && ($task->due_at === null || $task->due_at->isFuture());
    }

    public function publishTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.publish_task')
            && $task->status === ProductCompletionTaskStatus::DRAFT;
    }

    public function extendDeadline(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.extend_deadline') && ! $task->status->isTerminal();
    }

    public function extendRevisionLimit(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.extend_revision_limit') && ! $task->status->isTerminal();
    }

    public function expireTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.expire_task') && ! $task->status->isTerminal();
    }

    public function updateDraft(User $user, ProductCompletionTask $task): bool
    {
        return $task->status->canEditDraft()
            && $task->assigned_to === $user->getKey()
            && ($task->due_at === null || $task->due_at->isFuture());
    }

    public function updateContent(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.update_content')
            && $this->updateDraft($user, $task)
            && $this->scopeAllows($task, 'title', 'short_description', 'full_description', 'specifications');
    }

    public function updateAttributes(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.update_attributes')
            && $this->updateDraft($user, $task)
            && $this->scopeAllows($task, 'attributes');
    }

    public function manageMedia(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.manage_media')
            && $this->updateDraft($user, $task)
            && $this->scopeAllows($task, 'gallery');
    }

    public function manageSeo(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.manage_seo')
            && $this->updateDraft($user, $task)
            && $this->scopeAllows($task, 'seo');
    }

    public function submitTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.submit_task')
            && $this->updateDraft($user, $task);
    }

    public function requestRevision(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.request_revision')
            && $task->status->canBeReviewed()
            && $this->canReview($user, $task);
    }

    public function reviewSubmission(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.review_submission')
            && $task->status->canBeReviewed()
            && $this->canReview($user, $task);
    }

    public function approveSubmission(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.approve_submission')
            && $this->reviewSubmission($user, $task);
    }

    public function rejectSubmission(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.reject_submission')
            && $this->reviewSubmission($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->can('outsourcing.create_task');
    }

    public function assignTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.assign_task')
            && in_array($task->status, [
                ProductCompletionTaskStatus::DRAFT,
                ProductCompletionTaskStatus::AVAILABLE,
                ProductCompletionTaskStatus::ASSIGNED,
            ], true);
    }

    public function manageScope(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.manage_scope')
            && in_array($task->status, [
                ProductCompletionTaskStatus::DRAFT,
                ProductCompletionTaskStatus::AVAILABLE,
                ProductCompletionTaskStatus::ASSIGNED,
            ], true);
    }

    public function resolveConflict(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.resolve_conflict')
            && $user->can('outsourcing.view_all_tasks')
            && $task->status === ProductCompletionTaskStatus::CONFLICTED;
    }

    public function retryFinalization(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.resolve_conflict')
            && $user->can('outsourcing.view_all_tasks')
            && $task->status === ProductCompletionTaskStatus::APPROVED
            && ($task->content_applied_at === null || $task->media_published_at === null);
    }

    public function cancelTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.cancel_task') && ! $task->status->isTerminal();
    }

    private function canReview(User $user, ProductCompletionTask $task): bool
    {
        $isReviewer = $task->reviewer_id !== null && $task->reviewer_id === $user->getKey();
        $canTakeOpenReview = $this->isOpenReviewerQueueTask($user, $task);
        $isSupplier = $this->belongsToUsersSupplier($user, $task);

        return match ($task->review_mode) {
            ProductCompletionReviewMode::REVIEWER => $isReviewer || $canTakeOpenReview,
            ProductCompletionReviewMode::SUPPLIER => $isSupplier,
            ProductCompletionReviewMode::EITHER => $isReviewer || $canTakeOpenReview || $isSupplier,
        };
    }

    private function isOpenReviewerQueueTask(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('outsourcing.review_submission')
            && $task->reviewer_id === null
            && $task->assigned_to !== null
            && in_array($task->review_mode, [
                ProductCompletionReviewMode::REVIEWER,
                ProductCompletionReviewMode::EITHER,
            ], true)
            && in_array($task->status, [
                ProductCompletionTaskStatus::ACCEPTED,
                ProductCompletionTaskStatus::IN_PROGRESS,
                ProductCompletionTaskStatus::SUBMITTED,
                ProductCompletionTaskStatus::RESUBMITTED,
            ], true);
    }

    private function belongsToUsersSupplier(User $user, ProductCompletionTask $task): bool
    {
        if ($task->supplier_id === null) {
            return false;
        }

        return $user->supplier()->whereKey($task->supplier_id)->exists();
    }

    private function scopeAllows(ProductCompletionTask $task, string ...$keys): bool
    {
        foreach ($keys as $key) {
            $value = data_get($task->scope_json, $key, false);

            if ($value === true || is_array($value)) {
                return true;
            }
        }

        return false;
    }
}
