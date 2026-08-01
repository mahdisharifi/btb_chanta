<?php
// app/Livewire/User/RoleSelection.php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.blank')]
class RoleSelection extends Component
{
    public function selectSupplier()
    {
        // 🔥 به جای redirect به route ثبت نام، redirect به صفحه تکمیل اطلاعات
        return redirect()->route('user.register.supplier.complete');
    }

    public function selectRetailer()
    {
        return redirect()->route('user.register.retailer.complete');
    }

    public function render()
    {
        return view('livewire.user.role-selection');
    }
}
