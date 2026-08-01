<?php

namespace App\Livewire\Retailer;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\UserSubscription;

#[Layout('components.layouts.retailer')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $isVip = $user->hasActiveVip();

        $activeSubscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest()
            ->first();

        return view('livewire.retailer.dashboard', [
            'isVip' => $isVip,
            'activeSubscription' => $activeSubscription,
        ]);
    }
}
