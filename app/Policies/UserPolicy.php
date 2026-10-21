<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('admin');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole('super_admin') ||
            ($user->hasRole('admin') && $model->id !== $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('admin');
    }

    public function createWithoutMobile(User $user): bool
    {
        return $this->create($user);
    }

    public function verifyOnCreate(User $user): bool
    {
        return $this->create($user);
    }

    public function assignSensitiveRole(User $user, string $role): bool
    {
        return ! in_array($role, ['admin', 'super_admin'], true) || $user->hasRole('super_admin');
    }

    public function update(User $user, User $model): bool
    {
        // سوپر ادمین می‌تواند هر کاربری را ویرایش کند
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // کاربر عادی فقط می‌تواند پروفایل خودش را ویرایش کند
        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('super_admin') && $model->id !== $user->id;
    }

    public function changeRole(User $user, User $model): bool
    {
        return $user->hasRole('super_admin') && $model->id !== $user->id;
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->hasRole('super_admin') && $model->id !== $user->id;
    }

    public function toggleStatus(User $user, User $model): bool
    {
        return $user->hasRole('super_admin') && $model->id !== $user->id;
    }
}
