<?php

namespace App\Livewire\Admin\Users;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Create extends Component
{
    // فیلدهای پایه کاربر
    public $name = '';
    public $email = '';
    public $mobile = '';
    public $password = '';
    public $password_confirmation = '';

    public $role;

    // فیلدهای تکمیلی تامین‌کننده
    public $company_name = '';
    public $brand_name = '';
    public $national_id = '';
    public $tax_id = '';
    public $phone = '';
    public $province = '';
    public $city = '';
    public $postal_code = '';
    public $address = '';

    public function mount()
    {
        // نقش پیش‌فرض روی "خرده فروش پایه" تنظیم شده است
        $this->role = UserRole::RETAILER_BASIC->value;
    }

    protected function rules()
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'mobile' => array_merge(
                $this->isSystemRole() ? ['nullable'] : ['required'],
                ['string', 'max:20', 'unique:users,mobile'],
            ),
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in(UserRole::values())],
        ];

        // اعمال اعتبارسنجی فیلدهای تامین‌کننده فقط اگر نقش تامین‌کننده انتخاب شده باشد
        if ($this->role === UserRole::SUPPLIER->value) {
            $rules = array_merge($rules, [
                'company_name' => ['required', 'string', 'max:255'],
                'brand_name' => ['nullable', 'string', 'max:255'],
                'national_id' => ['nullable', 'string', 'max:20'],
                'tax_id' => ['nullable', 'string', 'max:50'],
                'phone' => ['nullable', 'string', 'max:20'],
                'province' => ['nullable', 'string', 'max:100'],
                'city' => ['nullable', 'string', 'max:100'],
                'postal_code' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:1000'],
            ]);
        }

        return $rules;
    }

    public function save()
    {
        $this->validate();

        DB::transaction(function () {
            // ایجاد کاربر در جدول users
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'mobile' => trim((string) $this->mobile) !== '' ? trim((string) $this->mobile) : null,
                'password' => Hash::make($this->password),
            ]);
            $user->forceFill([
                'is_verified' => $this->isSystemRole() && blank($user->mobile),
            ])->save();

            // اختصاص نقش
            $user->assignRole($this->role);

            // ایجاد رکورد در جدول suppliers در صورت انتخاب نقش تامین‌کننده
            if ($this->role === UserRole::SUPPLIER->value) {
                Supplier::create([
                    'user_id' => $user->id,
                    'company_name' => $this->company_name,
                    'brand_name' => $this->brand_name,
                    'national_id' => $this->national_id,
                    'tax_id' => $this->tax_id,
                    'phone' => $this->phone,
                    'province' => $this->province,
                    'city' => $this->city,
                    'postal_code' => $this->postal_code,
                    'address' => $this->address,
                    'email' => $this->email,
                    'mobile' => $this->mobile,
                    'is_active' => true,
                ]);
            }
        });

        session()->flash('success', 'کاربر با موفقیت ایجاد شد.');
        return $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.users.create', [
            'isSystemRole' => $this->isSystemRole(),
        ]);
    }

    private function isSystemRole(): bool
    {
        return in_array($this->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::OUTSOURCER->value,
            UserRole::REVIEWER->value,
            UserRole::FINANCE_MANAGER->value,
        ], true);
    }
}
