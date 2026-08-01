<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Show extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->authorize('view', $user);  // ✅ بررسی مجوز مشاهده

        $this->user = $user;
    }

    public function render()
    {
        return view('livewire.admin.users.show', [
            'roleLabel' => $this->user->getRoleLabel(),
            'roleBadgeColor' => $this->getRoleBadgeColor(),
        ])->title('User Details: ' . $this->user->name);
    }

    /**
     * تعیین رنگ Badge بر اساس نقش کاربر.
     */
    protected function getRoleBadgeColor(): string
    {
        return match (true) {
            $this->user->isAdmin()           => 'danger',
            $this->user->isSupplier()        => 'warning',
            $this->user->isRetailerPremium() => 'info',
            $this->user->isRetailerBasic()   => 'secondary',
            $this->user->isEndUser()         => 'dark',
            default                          => 'light',
        };
    }
}
