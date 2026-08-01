<?php
// app/Livewire/Admin/Users/Pending.php

namespace App\Livewire\Admin\Users;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

#[Layout('components.layouts.admin')]
class Pending extends Component
{
    use WithPagination;

    #[Url(history: true, keep: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $roleFilter = '';

    #[Url(history: true)]
    public string $statusFilter = '';

    public array $selectedUsers = [];
    public bool $selectAll = false;

    // ----- Reject modal state -----
    public bool $showRejectModal = false;
    public ?int $rejectUserId = null;
    public string $rejectReason = '';
    public bool $bulkRejectMode = false;

    protected const ROLES = ['regular_supplier', 'retailer_basic'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
        $this->reset('selectedUsers', 'selectAll');
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->reset('selectedUsers', 'selectAll');
    }

    public function updatedSelectAll($value): void
    {
        $this->selectedUsers = $value
            ? $this->getUsersQuery()->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    /**
     * پایه‌ی کوئری: کاربران تامین‌کننده/کسب‌وکار با وضعیت تایید نشده.
     */
    private function baseQuery()
    {
        return User::query()
            ->with(['supplier', 'roles'])
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ROLES))
            ->whereHas('supplier', fn ($q) => $q->where('verification_status', '!=', 'approved'));
    }

    private function getUsersQuery()
    {
        return $this->baseQuery()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhereHas('supplier', function ($sq) {
                            $sq->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('mobile', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->roleFilter, function ($query) {
                $query->whereHas('roles', fn ($q) => $q->where('name', $this->roleFilter));
            })
            ->when($this->statusFilter, function ($query) {
                $query->whereHas('supplier', fn ($q) => $q->where('verification_status', $this->statusFilter));
            })
            ->latest();
    }

    /**
     * شمارش وضعیت‌ها برای کارت‌های آماری.
     */
    #[Computed]
    public function stats(): array
    {
        $count = fn (string $status) => User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ROLES))
            ->whereHas('supplier', fn ($q) => $q->where('verification_status', $status))
            ->count();

        return [
            'pending'      => $count('pending'),
            'under_review' => $count('under_review'),
            'rejected'     => $count('rejected'),
        ];
    }

    // ----------------- Actions -----------------

    /**
     * ارتقای نقش تامین‌کننده عادی به تامین‌کننده کامل.
     * فقط روی کاربرانی اثر دارد که نقش regular_supplier دارند؛
     * نقش‌های دیگر (مثل retailer_basic) دست‌نخورده می‌مانند.
     */
    private function promoteToFullSupplier(User $user): void
    {
        if ($user->hasRole(UserRole::REGULAR_SUPPLIER->value)) {
            $user->removeRole(UserRole::REGULAR_SUPPLIER->value);
            $user->assignRole(UserRole::SUPPLIER->value);
        }
    }

    public function approve(int $userId): void
    {
        $user = User::with(['supplier', 'roles'])->findOrFail($userId);

        if ($user->supplier) {
            $user->supplier->update([
                'verification_status' => 'approved',
                'verified_at'         => now(),
                'verified_by'         => auth()->id(),
                'is_verified'         => true,
                'is_active'           => true,
            ]);

            $this->promoteToFullSupplier($user);

            unset($this->stats);
            session()->flash('message', 'کاربر با موفقیت تایید و به تامین‌کننده ارتقا یافت.');
        } else {
            session()->flash('error', 'این کاربر پروفایل کسب‌وکار ندارد.');
        }
    }

    public function reject(int $userId, ?string $reason = null): void
    {
        $user = User::with('supplier')->findOrFail($userId);

        if ($user->supplier) {
            $user->supplier->update([
                'verification_status' => 'rejected',
                'verified_at'         => now(),
                'verified_by'         => auth()->id(),
                'rejection_reason'    => $reason ?: 'مدارک ناقص یا نامعتبر',
                'is_verified'         => false,
                'is_active'           => false,
            ]);

            unset($this->stats);
            session()->flash('message', 'کاربر رد شد.');
        } else {
            session()->flash('error', 'این کاربر پروفایل کسب‌وکار ندارد.');
        }
    }

    public function bulkApprove(): void
    {
        if (empty($this->selectedUsers)) {
            session()->flash('error', 'هیچ کاربری انتخاب نشده است.');
            return;
        }

        $users = User::with(['supplier', 'roles'])->whereIn('id', $this->selectedUsers)->get();

        foreach ($users as $user) {
            if (! $user->supplier) {
                continue;
            }

            $user->supplier->update([
                'verification_status' => 'approved',
                'verified_at'         => now(),
                'verified_by'         => auth()->id(),
                'is_verified'         => true,
                'is_active'           => true,
            ]);

            $this->promoteToFullSupplier($user);
        }

        $count = $users->whereNotNull('supplier')->count();
        $this->reset('selectedUsers', 'selectAll');
        unset($this->stats);
        session()->flash('message', $count . ' کاربر تایید و به تامین‌کننده ارتقا یافتند.');
    }

    private function bulkReject(string $reason): void
    {
        $users = User::with('supplier')->whereIn('id', $this->selectedUsers)->get();

        foreach ($users as $user) {
            $user->supplier?->update([
                'verification_status' => 'rejected',
                'verified_at'         => now(),
                'verified_by'         => auth()->id(),
                'rejection_reason'    => $reason,
                'is_verified'         => false,
                'is_active'           => false,
            ]);
        }

        $count = $users->whereNotNull('supplier')->count();
        $this->reset('selectedUsers', 'selectAll');
        unset($this->stats);
        session()->flash('message', $count . ' کاربر رد شدند.');
    }

    // ----------------- Reject modal flow -----------------

    public function confirmReject(int $userId): void
    {
        $this->bulkRejectMode = false;
        $this->rejectUserId   = $userId;
        $this->rejectReason   = '';
        $this->showRejectModal = true;
    }

    public function confirmBulkReject(): void
    {
        if (empty($this->selectedUsers)) {
            session()->flash('error', 'هیچ کاربری انتخاب نشده است.');
            return;
        }

        $this->bulkRejectMode = true;
        $this->rejectUserId   = null;
        $this->rejectReason   = '';
        $this->showRejectModal = true;
    }

    public function submitReject(): void
    {
        $this->validate(
            ['rejectReason' => 'required|string|min:3|max:500'],
            [
                'rejectReason.required' => 'وارد کردن دلیل رد الزامی است.',
                'rejectReason.min'      => 'دلیل رد باید حداقل ۳ کاراکتر باشد.',
            ]
        );

        if ($this->bulkRejectMode) {
            $this->bulkReject($this->rejectReason);
        } else {
            $this->reject($this->rejectUserId, $this->rejectReason);
        }

        $this->closeRejectModal();
    }

    public function closeRejectModal(): void
    {
        $this->reset('showRejectModal', 'rejectUserId', 'rejectReason', 'bulkRejectMode');
        $this->resetValidation();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'roleFilter', 'statusFilter');
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.users.pending', [
            'users' => $this->getUsersQuery()->paginate(20),
        ]);
    }
}
