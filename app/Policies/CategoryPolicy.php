<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Enums\UserRole;

class CategoryPolicy
{
    /**
     * Everyone can view categories.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Everyone can view a single active category.
     */
    public function view(?User $user, Category $category): bool
    {
        if ($category->is_active) {
            return true;
        }

        // Non-active categories: admin only
        return $user && $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Only admin can create categories.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Only admin can edit categories.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Only admin can delete categories.
     * Cannot delete if category has products.
     */
    public function delete(User $user, Category $category): bool
    {
        if (!$user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return false;
        }

        // Prevent deletion if category has products
        if ($category->hasProducts()) {
            return false;
        }

        return true;
    }

    /**
     * Only admin can restore soft-deleted categories.
     */
    public function restore(User $user, Category $category): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Only admin can force delete.
     */
    public function forceDelete(User $user, Category $category): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Only admin can reorder categories.
     */
    public function reorder(User $user): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }
}
