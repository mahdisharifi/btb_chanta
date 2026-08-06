<?php

namespace App\Livewire\Admin\Suppliers;

use App\Livewire\User\RegisterSupplier;
use App\Models\Supplier;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.admin')]
class Edit extends RegisterSupplier
{
    public function mount(Supplier $supplier)
    {
        // تنظیم supplier و editMode
        $this->supplier = $supplier;
        $this->editMode = true;

        // فراخوانی mount والد برای بارگذاری استان‌ها و شبکه‌های اجتماعی
        parent::mount();

        // بارگذاری اطلاعات supplier
        $this->loadSupplierData();
    }

    public function register()
    {
        // Override برای ریدایرکت به لیست تامین‌کنندگان ادمین
        $this->validate();

        try {
            $data = [
                'company_name' => $this->company_name,
                'company_name_en' => $this->company_name_en,
                'brand_name' => $this->brand_name,
                'slug' => $this->slug ?: \Illuminate\Support\Str::slug($this->company_name),
                'description' => $this->description,
                'phone' => $this->phone,
                'mobile' => $this->mobile,
                'email' => $this->email,
                'website' => $this->website,
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'province_id' => $this->province_id,
                'city_id' => $this->city_id,
                'national_id' => $this->national_id,
                'tax_id' => $this->tax_id,
            ];

            // ذخیره لوگو
            if ($this->logo) {
                if ($this->existingLogo) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($this->existingLogo);
                }
                $data['logo_path'] = $this->logo->store('suppliers/logos', 'public');
            }

            // ذخیره مدارک احراز هویت
            if ($this->national_card_file) {
                if ($this->existingNationalCard) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($this->existingNationalCard);
                }
                $data['national_card_file'] = $this->national_card_file->store('suppliers/documents', 'public');
            }

            if ($this->business_license_file) {
                if ($this->existingBusinessLicense) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($this->existingBusinessLicense);
                }
                $data['business_license_file'] = $this->business_license_file->store('suppliers/documents', 'public');
            }

            // ذخیره شبکه‌های اجتماعی
            $data['social_networks'] = array_filter($this->social_networks);

            // ادمین می‌تونه همه فیلدها رو ویرایش کنه (بدون قفل)
            $this->supplier->update($data);

            session()->flash('success', 'اطلاعات تامین‌کننده با موفقیت به‌روزرسانی شد.');

            return $this->redirectRoute('admin.suppliers.index', navigate: true);

        } catch (\Exception $e) {
            session()->flash('error', 'خطا در به‌روزرسانی اطلاعات: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // استفاده از همان view کامپوننت RegisterSupplier
        return view('livewire.user.register-supplier');
    }
}
