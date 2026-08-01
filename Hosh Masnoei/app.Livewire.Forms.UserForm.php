<?php

namespace App\Livewire\Forms;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Form;

class UserForm extends Form
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = '';
    public bool $is_active = true;

    public ?User $user = null;

    /**
     * تعریف قوانین اعتبارسنجی
     * - در حالت ایجاد: رمز عبور اجباری، ایمیل یکتا
     * - در حالت ویرایش: رمز عبور اختیاری، ایمیل یکتا با نادیده‌گرفتن کاربر جاری
     */
    public function rules(): array
    {
        $rules = [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', Rule::enum(UserRole::class)],
        ];

        if ($this->user) {
            // حالت ویرایش
            $rules['email'][] = Rule::unique('users', 'email')->ignore($this->user->id);
            $rules['password'] = ['nullable', 'string', 'min:8', 'confirmed'];
        } else {
            // حالت ایجاد
            $rules['email'][] = 'unique:users,email';
        }

        return $rules;
    }

    /**
     * پر کردن فیلدهای فرم با اطلاعات یک کاربر موجود
     * در کامپوننت‌های ویرایش از طریق mount() فراخوانی می‌شود
     */
    public function setUser(User $user): void
    {
        $this->user  = $user;
        $this->name  = $user->name;
        $this->email = $user->email;
        $this->role  = $user->role instanceof UserRole ? $user->role->value : $user->role;
        $this->is_active = (bool) $user->is_active;
    }

    /**
     * ایجاد کاربر جدید
     * - هش کردن رمز عبور
     * - انتساب نقش با Spatie Permission
     */
    public function store(): User
    {
        $validated = $this->validate();

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        $user->assignRole($validated['role']);

        return $user;
    }

    /**
     * به‌روزرسانی کاربر موجود
     * - فقط در صورت وارد شدن رمز جدید، آن را به‌روزرسانی می‌کند
     * - همگام‌سازی نقش با Spatie Permission
     */
    public function update(User $user): void
    {
        $this->user = $user;
        $validated  = $this->validate();

        $data = [
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'is_active' => $validated['is_active'] ?? $this->is_active,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);
        $user->syncRoles([$validated['role']]);
    }
}
