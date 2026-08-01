<?php

namespace App\Livewire\Admin\Suppliers;

use App\Models\User;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

#[Layout('components.layouts.admin')]
class Create extends Component
{
    use WithFileUploads;

    // متغیر تعیین کننده وضعیت (کاربر جدید یا موجود)
    public $is_new_user = false;

    // --- فیلدهای کاربر موجود ---
    public $user_id;
    public $search = '';

    // --- فیلدهای کاربر جدید ---
    public $name;
    public $email;
    public $mobile; // شماره موبایل جهت ورود/عضویت کاربر
    public $password;
    public $password_confirmation;

    // --- فیلدهای اختصاصی تامین‌کننده ---
    public $company_name;
    public $brand_name;
    public $national_id;
    public $tax_id;
    public $phone;
    public $supplier_mobile; // شماره موبایل کاری و مجزای تامین‌کننده جهت ثبت در جدول suppliers
    public $province;
    public $city;
    public $address;
    public $is_active = false;
    public $is_verified = false;
    public $logo;

    // اعتبارسنجی داینامیک بر اساس وضعیت انتخاب شده
    protected function rules()
    {
        $rules = [
            'company_name' => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:50',
            'tax_id' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'supplier_mobile' => 'nullable|string|max:20', // اعتبارسنجی موبایل اختصاصی تامین‌کننده
            'province' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'logo' => 'nullable|image|max:2048',
        ];

        if ($this->is_new_user) {
            // اعتبارسنجی برای اکانت کاربر جدید
            $rules['name'] = 'required|string|max:255';
            $rules['email'] = 'nullable|string|email|max:255|unique:users,email';
            $rules['mobile'] = 'required|string|max:20|unique:users,mobile';
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            // اعتبارسنجی برای کاربر موجود
            $rules['user_id'] = 'required|exists:users,id|unique:suppliers,user_id';
        }

        return $rules;
    }

    // متون خطای سفارشی
    protected function messages()
    {
        return [
            'user_id.required' => 'لطفاً یک کاربر را انتخاب کنید.',
            'user_id.unique' => 'این کاربر در حال حاضر یک پروفایل تامین‌کننده دارد.',
        ];
    }

    public function save()
    {
        $this->validate();

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('suppliers/logos', 'public');
        }

        DB::transaction(function () use ($logoPath) {
            $finalUserId = $this->user_id;

            if ($this->is_new_user) {
                // ایجاد کاربر جدید (موبایل کاربر صرفاً برای لاگین استفاده می‌شود)
                $user = User::create([
                    'name' => $this->name,
                    'email' => $this->email,
                    'mobile' => $this->mobile,
                    'password' => Hash::make($this->password),
                ]);

                // تخصیص نقش به کاربر جدید
                $user->assignRole('supplier');

                $finalUserId = $user->id;
            } else {
                // بررسی و تخصیص نقش برای کاربر موجود
                $user = User::find($finalUserId);
                if ($user && !$user->hasRole('supplier')) {
                    $user->assignRole('supplier');
                }
            }

            // ایجاد پروفایل تامین‌کننده (با موبایل اختصاصی کاری و تجاری خود تامین‌کننده)
            Supplier::create([
                'user_id' => $finalUserId,
                'company_name' => $this->company_name,
                'brand_name' => $this->brand_name,
                'national_id' => $this->national_id,
                'tax_id' => $this->tax_id,
                'phone' => $this->phone,
                'mobile' => $this->supplier_mobile, // کاملا مستقل از شماره موبایل کاربر سیستم
                'province' => $this->province,
                'city' => $this->city,
                'address' => $this->address,
                'is_active' => $this->is_active,
                'is_verified' => $this->is_verified,
                'verified_at' => $this->is_verified ? now() : null,
                'logo_path' => $logoPath,
            ]);
        });

        session()->flash('success', 'تامین‌کننده با موفقیت ثبت شد.');

        return $this->redirectRoute('admin.suppliers.index', navigate: true);
    }

    public function render()
    {
        $users = [];

        // فقط زمانی که حالت انتخاب کاربر موجود فعال باشد دیتا واکشی می‌شود
        if (!$this->is_new_user) {
            $users = User::whereDoesntHave('supplier')
                ->when($this->search, function ($query) {
                    $query->where(function($q) {
                        $q->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%')
                            ->orWhere('mobile', 'like', '%' . $this->search . '%');
                    });
                })
                ->limit(10)
                ->get();
        }

        return view('livewire.admin.suppliers.create', [
            'users' => $users
        ]);
    }
}
