<?php

namespace App\Livewire\Admin\Suppliers;

use App\Models\User;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

#[Layout('components.layouts.admin')]
class Edit extends Component
{
    use WithFileUploads;

    public Supplier $supplier;

    // فیلدهای کاربر
    public $user_id;

    // فیلدهای تامین‌کننده
    public $company_name;
    public $brand_name;
    public $national_id;
    public $tax_id;
    public $phone;
    public $province;
    public $city;
    public $address;
    public $is_active;
    public $is_verified;

    // فیلدهای لوگو
    public $logo; // برای آپلود فایل جدید
    public $current_logo; // برای نمایش لوگوی فعلی

    public function mount(Supplier $supplier)
    {
        $this->supplier = $supplier;

        $this->user_id = $supplier->user_id;
        $this->company_name = $supplier->company_name;
        $this->brand_name = $supplier->brand_name;
        $this->national_id = $supplier->national_id;
        $this->tax_id = $supplier->tax_id;
        $this->phone = $supplier->phone;
        $this->province = $supplier->province;
        $this->city = $supplier->city;
        $this->address = $supplier->address;
        $this->is_active = (bool) $supplier->is_active;
        $this->is_verified = (bool) $supplier->is_verified;
        $this->current_logo = $supplier->logo_path;
    }

    protected function rules()
    {
        return [
            // کاربر باید وجود داشته باشد و در جدول suppliers یکتا باشد (به جز خود این تامین‌کننده)
            'user_id' => 'required|exists:users,id|unique:suppliers,user_id,' . $this->supplier->id,
            'company_name' => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:50',
            'tax_id' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'province' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'logo' => 'nullable|image|max:2048',
        ];
    }

    protected function messages()
    {
        return [
            'user_id.required' => 'لطفاً یک کاربر را انتخاب کنید.',
            'user_id.unique' => 'کاربر انتخاب شده در حال حاضر یک پروفایل تامین‌کننده دیگر دارد.',
        ];
    }

    public function update()
    {
        $validated = $this->validate();

        // مدیریت آپلود لوگوی جدید
        if ($this->logo) {
            // حذف لوگوی قبلی در صورت وجود
            if ($this->supplier->logo_path) {
                Storage::disk('public')->delete($this->supplier->logo_path);
            }
            $validated['logo_path'] = $this->logo->store('suppliers/logos', 'public');
        }

        // تنظیم خودکار تاریخ تایید
        if ($this->is_verified && !$this->supplier->is_verified) {
            $validated['verified_at'] = now();
        } elseif (!$this->is_verified) {
            $validated['verified_at'] = null;
        }

        // جلوگیری از ثبت آبجکت فایل در دیتابیس
        unset($validated['logo']);

        $this->supplier->update($validated);

        // اطمینان از اینکه کاربر تخصیص داده شده، نقش تامین‌کننده را دارد
        $user = User::find($this->user_id);
        if ($user && !$user->hasRole('supplier')) {
            $user->assignRole('supplier');
        }

        session()->flash('success', 'تامین‌کننده با موفقیت ویرایش شد.');

        return $this->redirectRoute('admin.suppliers.index', navigate: true);
    }

    public function render()
    {
        // دریافت کاربرانی که تامین‌کننده نیستند + کاربر فعلی همین تامین‌کننده
        $users = User::whereDoesntHave('supplier')
            ->orWhere('id', $this->supplier->user_id)
            ->get();

        return view('livewire.admin.suppliers.edit', [
            'users' => $users
        ]);
    }
}
