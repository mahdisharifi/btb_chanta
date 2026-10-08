<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProductCompletionCommissionStatus;
use App\Models\ProductCompletionCommission;
use App\Models\User;

class ProductCompletionCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'outsourcing.view_own_commissions',
            'outsourcing.view_reviewed_commissions',
            'outsourcing.view_all_commissions',
        ]);
    }

    public function view(User $user, ProductCompletionCommission $commission): bool
    {
        return $this->viewCommission($user, $commission);
    }

    public function viewCommission(User $user, ProductCompletionCommission $commission): bool
    {
        return $user->can('outsourcing.view_all_commissions')
            || ($user->can('outsourcing.view_own_commissions') && $commission->user_id === $user->getKey())
            || ($user->can('outsourcing.view_reviewed_commissions')
                && $this->wasFinallyReviewedBy($user, $commission));
    }

    public function approveCommission(User $user, ProductCompletionCommission $commission): bool
    {
        return $user->can('outsourcing.approve_commission')
            && in_array($commission->status, [
                ProductCompletionCommissionStatus::CALCULATED,
                ProductCompletionCommissionStatus::UNDER_REVIEW,
            ], true);
    }

    public function adjustCommission(User $user, ProductCompletionCommission $commission): bool
    {
        return $user->can('outsourcing.adjust_commission')
            && in_array($commission->status, [
                ProductCompletionCommissionStatus::CALCULATED,
                ProductCompletionCommissionStatus::UNDER_REVIEW,
            ], true)
            && ($user->can('outsourcing.view_all_commissions')
                || ($user->can('outsourcing.view_reviewed_commissions')
                    && $this->wasFinallyReviewedBy($user, $commission)));
    }

    public function markPayable(User $user, ProductCompletionCommission $commission): bool
    {
        return $user->can('outsourcing.approve_commission')
            && $commission->status === ProductCompletionCommissionStatus::APPROVED;
    }

    public function payCommission(User $user, ProductCompletionCommission $commission): bool
    {
        if (! $user->can('outsourcing.pay_commission')) {
            return false;
        }

        if ($commission->status !== ProductCompletionCommissionStatus::PAYABLE) {
            return false;
        }

        return ! config('product_completion.commission.require_distinct_approver_and_payer', true)
            || $commission->approved_by !== $user->getKey();
    }

    private function wasFinallyReviewedBy(User $user, ProductCompletionCommission $commission): bool
    {
        if ($commission->relationLoaded('task')
            && $commission->task !== null
            && $commission->task->relationLoaded('lastSubmission')) {
            return $commission->task->lastSubmission?->reviewed_by === $user->getKey();
        }

        return $commission->task()
            ->whereHas(
                'lastSubmission',
                fn ($submission) => $submission->where('reviewed_by', $user->getKey())
            )
            ->exists();
    }
}
