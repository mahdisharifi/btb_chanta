<?php

namespace App\Livewire\Account;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
#[Layout('components.layouts.blank')]

class Pending extends Component
{
    public function mount()
    {
        $user = Auth::user();
        abort_unless($user, 403);
        if ($user->needsMobileVerification() || !$user->supplier
            || $user->supplier->verification_status !== 'pending') {
            return redirectByRole($user);
        }
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
        return view('livewire.account.pending');
    }
}
