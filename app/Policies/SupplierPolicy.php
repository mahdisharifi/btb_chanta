<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Enums\UserRole;

class SupplierPolicy
{
    /**
     * Admin can view all suppliers.
     * Premium retailers can view verified suppliers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::RETAILER_PREMIUM->value,
        ]);
    }

    /**
     * CRITICAL: Only Premium retailers and admin can view supplier info.
     * Basic retailers and End Users MUST NOT see supplier details.
     */
    public function view(User $user, Supplier $supplier): bool
    {
        // Admin — always
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        // The supplier's own user — always
        if ($user->hasRole(UserRole::SUPPLIER->value) && $supplier->user_id === $user->id) {
            return true;
        }

        // Premium retailer — only verified & active suppliers
        if ($user->hasRole(UserRole::RETAILER_PREMIUM->value)) {
            return $supplier->is_verified && $supplier->is_active;
        }

        // Basic retailers, End Users — NEVER
        return false;
    }

    /**
     * Only admin can create new supplier profiles.
     * Suppliers create their own during registration.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Admin can edit any supplier.
     * Supplier can edit their own profile.
     */
    public function update(User $user, Supplier $supplier): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasAnyRole([UserRole::SUPPLIER->value, 'regular_supplier'])) {
            return $supplier->user_id === $user->id;
        }

        return false;
    }

    public function updateSlug(User $user, Supplier $supplier): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Only admin can delete suppliers.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Only admin can verify/unverify suppliers.
     */
    public function verify(User $user, Supplier $supplier): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * CRITICAL: View supplier contact info (phone, email, address).
     * Only Premium retailers and admin.
     */
    public function viewContactInfo(User $user, Supplier $supplier): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(UserRole::SUPPLIER->value) && $supplier->user_id === $user->id) {
            return true;
        }

        if ($user->hasRole(UserRole::RETAILER_PREMIUM->value)) {
            return $supplier->is_verified && $supplier->is_active;
        }

        return false;
    }

    /**
     * View supplier's product list.
     */
    public function viewProducts(User $user, Supplier $supplier): bool
    {
        // Admin — always
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        // Own supplier
        if ($user->hasRole(UserRole::SUPPLIER->value) && $supplier->user_id === $user->id) {
            return true;
        }

        // Premium retailers — verified suppliers only
        if ($user->hasRole(UserRole::RETAILER_PREMIUM->value)) {
            return $supplier->is_verified && $supplier->is_active;
        }

        return false;
    }
}
