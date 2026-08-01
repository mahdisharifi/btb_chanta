<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Enums\UserRole;


class ProductPolicy
{
    /**
     * Any authenticated user can view active products.
     * Guests are handled by the controller.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Anyone can view an active product.
     * Draft/Pending products: only admin or the owning supplier.
     */
    public function view(?User $user, Product $product): bool
    {
        // Active products are visible to everyone
        if ($product->is_active) {
            return true;
        }

        // Not logged in — cannot view non-active products
        if (!$user) {
            return false;
        }

        // Admin can view everything
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        // Supplier can view their own products (any status)
        if ($user->hasRole(UserRole::SUPPLIER->value)) {
            return $product->supplier?->user_id === $user->id;
        }

        return false;
    }

    /**
     * Only admin and suppliers can create products.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::SUPPLIER->value,
        ]);
    }

    /**
     * Admin can edit any product.
     * Supplier can edit their own products only.
     */
    public function update(User $user, Product $product): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(UserRole::SUPPLIER->value)) {
            return $product->supplier?->user_id === $user->id;
        }

        return false;
    }

    /**
     * Only admin can delete products.
     * Suppliers can soft-delete their own draft products.
     */
    public function delete(User $user, Product $product): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(UserRole::SUPPLIER->value)) {
            return $product->supplier?->user_id === $user->id;
        }

        return false;
    }

    /**
     * Only admin can restore soft-deleted products.
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * Only admin can permanently delete.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * CRITICAL: Only Premium retailers and admin can view supplier (wholesale) prices.
     * Basic retailers and End Users MUST NOT see these.
     */
    public function viewSupplierPrices(User $user, Product $product): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::SUPPLIER->value,
            UserRole::RETAILER_PREMIUM->value,
        ]);
    }

    /**
     * Only suppliers (for their own products) and admin can manage supplier prices.
     */
    public function manageSupplierPrices(User $user, Product $product): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(UserRole::SUPPLIER->value)) {
            return $product->supplier?->user_id === $user->id;
        }

        return false;
    }

    /**
     * Retailers (Premium + Basic) can set retail prices.
     */
    public function setRetailPrice(User $user, Product $product): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::RETAILER_PREMIUM->value,
            UserRole::RETAILER_BASIC->value,
        ]);
    }

    /**
     * Only admin and the product's supplier can manage the gallery.
     */
    public function manageGallery(User $user, Product $product): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(UserRole::SUPPLIER->value)) {
            return $product->supplier?->user_id === $user->id;
        }

        return false;
    }

    /**
     * Admin can approve/reject pending products.
     */
    public function approve(Product $product)
    {
        $this->authorize('approve', $product);

        $product->update([
            'status' => ProductStatus::ACTIVE,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return redirect()->route('admin.products.index')
            ->with('success', 'محصول با موفقیت تایید شد.');
    }

    public function reject(Product $product)
    {
        $this->authorize('reject', $product);

        $product->update([
            'status' => ProductStatus::INACTIVE,
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
        ]);

        return redirect()->route('admin.products.index')
            ->with('success', 'محصول رد شد.');
    }

    public function bulkAction(Request $request)
    {
        $this->authorize('bulkAction', Product::class);

        // بقیه کد...
    }

}
