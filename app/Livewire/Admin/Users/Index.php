<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('مدیریت کاربران')]
class Index extends Component
{
    use WithPagination;

    // متغیر برای جستجو
    public $search = '';

    // در صورت تغییر عبارت جستجو، صفحه‌بندی به صفحه اول برمی‌گردد
    public function updatedSearch()
    {
        $this->resetPage();
    }

    // متد حذف کاربر
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        // بررسی سطح دسترسی برای حذف
        Gate::authorize('delete', $user);

        $user->delete();

        // ارسال پیام موفقیت
        session()->flash('success', 'کاربر با موفقیت حذف شد.');
    }

    public function render()
    {
        // بررسی سطح دسترسی برای مشاهده لیست
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles') // اضافه شدن این خط برای لود کردن نقش‌ها

            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('mobile', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        return view('livewire.admin.users.index', compact('users'));
    }
}
