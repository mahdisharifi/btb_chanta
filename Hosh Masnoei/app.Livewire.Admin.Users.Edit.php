<?php

namespace App\Livewire\Admin\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Edit extends Component
{
    public User $user;

    public $name;
    public $email;
    public $mobile;
    public $role;
    public $is_active = true;
    public $password; // برای ویرایش رمز عبور در صورت نیاز

    public function mount(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->mobile = $user->mobile ?? '';
        $this->is_active = (bool) $user->is_active;

        // دریافت اولین نقش کاربر (در صورت وجود)
        $this->role = $user->roles->first()->name ?? UserRole::USER->value;
    }

    public function rules()
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user->id)],
            'mobile'    => ['nullable', 'string', 'max:20', Rule::unique('users')->ignore($this->user->id)],
            'role'      => ['required', 'string'],
            'is_active' => ['boolean'],
            'password'  => ['nullable', 'string', 'min:8'],
        ];
    }

    public function update()
    {
        $this->validate();

        $data = [
            'name'      => $this->name,
            'email'     => $this->email,
            'mobile'    => $this->mobile,
            'is_active' => $this->is_active,
        ];

        // فقط در صورتی که رمز عبور جدید وارد شده باشد، آن را آپدیت می‌کنیم
        if (!empty($this->password)) {
            $data['password'] = bcrypt($this->password);
        }

        $this->user->update($data);

        // همگام‌سازی نقش با Spatie
        $this->user->syncRoles([$this->role]);

        session()->flash('success', 'اطلاعات کاربر با موفقیت ویرایش شد.');
        return $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.users.edit', [
            'roles' => UserRole::cases(),
        ])->layout('components.layouts.admin');
    }
}
