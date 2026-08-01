<?php

namespace App\Policies;

use App\Models\RetailPrice;
use App\Models\User;
use App\Enums\UserRole;

class RetailPricePolicy
{
    /**
     * Admin can view all retail prices.
     * Retailers can view their own.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::RETAILER_PREMIUM->value,
            UserRole::RETAILER_BASIC->value,
        ]);
    }

    /**
     * View a specific retail price.
     */
    public function view(User $user, RetailPrice $retailPrice): bool
    {
        // Admin can view all
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        // Retailer can view their own prices
        if ($user->hasAnyRole([UserRole::RETAILER_PREMIUM->value, UserRole::RETAILER_BASIC->value])) {
            return $retailPrice->user_id === $user->id;
        }

        // Published retail prices are visible to end users
        if ($retailPrice->is_published) {
            return true;
        }

        return false;
    }

    /**
     * Only retailers can create retail prices.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::RETAILER_PREMIUM->value,
            UserRole::RETAILER_BASIC->value,
        ]);
    }

    /**
     * Only the owning retailer or admin can update.
     */
    public function update(User $user, RetailPrice $retailPrice): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        return $retailPrice->user_id === $user->id;
    }

    /**
     * Only the owning retailer or admin can delete.
     */
    public function delete(User $user, RetailPrice $retailPrice): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        return $retailPrice->user_id === $user->id;
    }

    /**
     * Only the owning retailer can publish/unpublish their price.
     */
    public function publish(User $user, RetailPrice $retailPrice): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        return $retailPrice->user_id === $user->id;
    }
}
