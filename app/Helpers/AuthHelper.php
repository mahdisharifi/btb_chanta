<?php

use App\Enums\UserRole;
use App\Models\User;

if (! function_exists('redirectByRole')) {
    /**
     * ریدایرکت بر اساس نقش کاربر
     * 
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    function redirectByRole(User $user)
    {
        if ($user->needsMobileVerification()) {
            return redirect()->route('verify.mobile');
        }

        if ($user->hasAnyRole(['supplier', 'regular_supplier', 'retailer_basic', 'retailer_premium'])
            && $user->supplier) {
            $route = match ($user->supplier->verification_status) {
                'pending' => 'account.pending',
                'under_review' => 'account.under-review',
                'rejected' => 'account.rejected',
                default => null,
            };
            if ($route) {
                return redirect()->route($route);
            }
        }

        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasAnyRole([UserRole::SUPPLIER, UserRole::REGULAR_SUPPLIER])) {
            return redirect()->route('supplier.dashboard');
        }

        if ($user->hasRole([UserRole::RETAILER_PREMIUM, UserRole::RETAILER_BASIC])) {
            return redirect()->route('retailer.dashboard');
        }
        
        if ($user->hasRole(UserRole::END_USER)) {
            return redirect()->route('user.role-selection');
        }
        
        return redirect()->route('home');
    }
}
