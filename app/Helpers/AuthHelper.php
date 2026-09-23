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
        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole(UserRole::SUPPLIER)) {
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
