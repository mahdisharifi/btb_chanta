<?php

namespace App\Livewire\Account;

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('components.layouts.blank')]

class UnderReview extends Component
{
    public ?string $statusMessage = null;

    public function mount(): void
    {
        // اگر کاربر در این وضعیت نیست، به مسیر مناسب هدایت شود
        $supplier = Auth::user()?->supplier;

        if (! $supplier) {
            $this->redirect('/', navigate: true);
            return;
        }

        if ($supplier->verification_status !== 'under_review') {
            // اجازه بده middleware مقصد درست را تعیین کند
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.account.under-review');
    }
}
