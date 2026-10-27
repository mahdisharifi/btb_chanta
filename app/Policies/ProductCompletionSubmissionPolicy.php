<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductCompletionSubmission;
use App\Models\User;

class ProductCompletionSubmissionPolicy
{
    public function view(User $user, ProductCompletionSubmission $submission): bool
    {
        return $this->viewSubmission($user, $submission);
    }

    public function viewSubmission(User $user, ProductCompletionSubmission $submission): bool
    {
        return $user->can('outsourcing.view_revision_history')
            && $user->can('viewTask', $submission->task);
    }

    public function requestRevision(User $user, ProductCompletionSubmission $submission): bool
    {
        return $this->isLatest($submission)
            && $user->can('requestRevision', $submission->task);
    }

    public function reviewSubmission(User $user, ProductCompletionSubmission $submission): bool
    {
        return $this->isLatest($submission)
            && $user->can('reviewSubmission', $submission->task);
    }

    public function approveSubmission(User $user, ProductCompletionSubmission $submission): bool
    {
        return $this->isLatest($submission)
            && $user->can('approveSubmission', $submission->task);
    }

    public function rejectSubmission(User $user, ProductCompletionSubmission $submission): bool
    {
        return $this->isLatest($submission)
            && $user->can('rejectSubmission', $submission->task);
    }

    private function isLatest(ProductCompletionSubmission $submission): bool
    {
        return $submission->task->last_submission_id === $submission->getKey();
    }
}
