<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CommissionRule;
use App\Models\User;

class CommissionRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('outsourcing.manage_commission_rules');
    }

    public function view(User $user, CommissionRule $rule): bool
    {
        return $user->can('outsourcing.manage_commission_rules');
    }

    public function create(User $user): bool
    {
        return $user->can('outsourcing.manage_commission_rules');
    }

    public function update(User $user, CommissionRule $rule): bool
    {
        return $user->can('outsourcing.manage_commission_rules');
    }

    public function delete(User $user, CommissionRule $rule): bool
    {
        return false;
    }
}
