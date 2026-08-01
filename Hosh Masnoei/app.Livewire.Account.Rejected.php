<?php

namespace App\Livewire\Account;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
#[Layout('components.layouts.blank')]

class Rejected extends Component
{
    public ?string $rejectionReason;

    public function mount()
    {
        $supplier = Auth::user()->supplier;
        $this->rejectionReason = $supplier?->rejection_reason;
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.account.rejected');
    }
}
