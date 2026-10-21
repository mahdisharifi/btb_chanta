<?php

namespace App\Livewire\Admin\Suppliers;

use App\Livewire\User\RegisterSupplier;
use App\Models\Supplier;
use App\Services\SupplierSlugService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.admin')]
class Edit extends RegisterSupplier
{
    public function mount()
    {
        // دریافت supplier از route parameter
        $supplierId = request()->route('supplier');
        $this->supplier = Supplier::findOrFail($supplierId);
        Gate::authorize('update', $this->supplier);
        
        // بررسی دسترسی: فقط ادمین یا خود تامین‌کننده
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'admin'])) {
            // اگر تامین‌کننده است، فقط می‌تونه پروفایل خودش رو ویرایش کنه
            if (!$user->supplier || $user->supplier->id !== $this->supplier->id) {
                abort(403, 'شما اجازه دسترسی به این صفحه را ندارید.');
            }
        }
        
        $this->editMode = true;

        // فراخوانی mount والد برای بارگذاری استان‌ها و شبکه‌های اجتماعی
        parent::mount();

        // بارگذاری اطلاعات supplier
        $this->loadSupplierData();
    }

    public function register()
    {
        Gate::authorize('update', $this->supplier);
        $requestedSlug = $this->slug ?: $this->supplier->slug;
        if ($requestedSlug !== $this->supplier->slug) {
            Gate::authorize('updateSlug', $this->supplier);
        }
        // Override برای ریدایرکت به لیست تامین‌کنندگان ادمین
        $this->validate();
        $logoImages = app(\App\Services\SupplierLogoImageService::class);
        $newLogo = null;
        $databaseSaved = false;
        $newDocuments = [];

        try {
            $data = [
                'company_name' => $this->company_name,
                'company_name_en' => $this->company_name_en,
                'brand_name' => $this->brand_name,
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
                $newLogo = $logoImages->processUpload($this->logo);
                $data = array_merge($data, $newLogo);
            }

            // ذخیره مدارک احراز هویت
            if ($this->national_card_file) {
                $newDocuments['national_card_file'] = $data['national_card_file'] = $this->national_card_file->store('suppliers/documents', 'public');
                if (! $data['national_card_file']) {
                    throw new \RuntimeException('Unable to store national card.');
                }
            }

            if ($this->business_license_file) {
                $newDocuments['business_license_file'] = $data['business_license_file'] = $this->business_license_file->store('suppliers/documents', 'public');
                if (! $data['business_license_file']) {
                    throw new \RuntimeException('Unable to store business license.');
                }
            }

            // ذخیره شبکه‌های اجتماعی
            $data['social_networks'] = array_filter($this->social_networks);

            // ادمین می‌تونه همه فیلدها رو ویرایش کنه (بدون قفل)
            $oldLogoPath = $this->supplier->logo_path;
            $oldLogoVersions = $this->supplier->logo_versions;
            $oldDocuments = DB::transaction(function () use ($data, $requestedSlug, $newDocuments): array {
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($this->supplier->getKey());
                Gate::authorize('update', $supplier);
                $oldDocuments = array_filter(array_map(
                    fn (string $column) => $supplier->getRawOriginal($column),
                    array_keys($newDocuments),
                ));
                if ($requestedSlug !== $supplier->slug) {
                    Gate::authorize('updateSlug', $supplier);
                    $supplier = app(SupplierSlugService::class)->change($supplier, $requestedSlug);
                }
                $supplier->update($data);
                $this->supplier = $supplier;

                return array_diff($oldDocuments, array_filter([
                    $supplier->national_card_file,
                    $supplier->business_license_file,
                ]));
            }, 3);
            $databaseSaved = true;
            DB::afterCommit(function () use ($oldDocuments, $newLogo, $oldLogoPath, $oldLogoVersions, $logoImages): void {
                if ($oldDocuments) {
                    Storage::disk('public')->delete($oldDocuments);
                }
                if ($newLogo && $oldLogoPath) {
                    $logoImages->delete($oldLogoPath, $oldLogoVersions);
                }
            });

            session()->flash('success', 'اطلاعات تامین‌کننده با موفقیت به‌روزرسانی شد.');

            return $this->redirectRoute('admin.suppliers.index', navigate: true);

        } catch (\Throwable $e) {
            if (! $databaseSaved) {
                if ($newDocuments) {
                    Storage::disk('public')->delete(array_filter($newDocuments));
                }
                if ($newLogo) {
                    $logoImages->delete($newLogo['logo_path'] ?? null, $newLogo['logo_versions'] ?? null);
                }
            }
            if ($e instanceof ValidationException || $e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }
            session()->flash('error', 'خطا در به‌روزرسانی اطلاعات: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // استفاده از همان view کامپوننت RegisterSupplier
        return view('livewire.user.register-supplier');
    }
}
