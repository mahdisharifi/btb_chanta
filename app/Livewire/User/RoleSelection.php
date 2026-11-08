<?php
// app/Livewire/User/RoleSelection.php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.blank')]
class RoleSelection extends Component
{
    public function selectSupplier()
    {
        // 🔥 به جای redirect به route ثبت نام، redirect به صفحه تکمیل اطلاعات
        return $this->selectBusiness('user.register.supplier.complete');
    }

    public function selectRetailer()
    {
        return $this->selectBusiness('user.register.retailer.complete');
    }

    protected function selectBusiness(string $route)
    {
        $user = Auth::user();
        abort_unless($user, 403);
        if ($user->needsMobileVerification()) {
            return redirect()->route('verify.mobile');
        }
        if (!$user->hasRole(UserRole::END_USER)) {
            return redirectByRole($user);
        }

        return redirect()->route($route);
    }

    public function render()
    {
        return view('livewire.user.role-selection');
    }
}
