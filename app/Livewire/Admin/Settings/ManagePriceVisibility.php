<?php

namespace App\Livewire\Admin\Settings;

use App\Models\PriceVisibilitySetting;
use App\Enums\UserRole;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.admin')]
#[Title('مدیریت دسترسی نمایش قیمت')]
class ManagePriceVisibility extends Component
{
    // تنظیمات همه نقش‌ها
    public $settings = [];
    
    // نقش در حال ویرایش
    public $editingRole = null;
    public $editForm = [];
    
    // حالت نمایش
    public $showEditModal = false;
    
    /**
     * Mount component
     */
    public function mount(): void
    {
        $this->loadSettings();
    }

    /**
     * بارگذاری تنظیمات از دیتابیس
     */
    public function loadSettings(): void
    {
        $this->settings = PriceVisibilitySetting::orderBy('role')->get()->keyBy('role')->toArray();
        
        // اگر نقشی تنظیمات نداشت، تنظیمات پیش‌فرض را اضافه کن
        foreach (UserRole::cases() as $role) {
            if (!isset($this->settings[$role->value])) {
                $this->settings[$role->value] = array_merge(
                    PriceVisibilitySetting::getDefaultSettings(),
                    [
                        'role' => $role->value,
                        'description' => null,
                    ]
                );
            }
        }
        
        // اضافه کردن guest
        if (!isset($this->settings['guest'])) {
            $this->settings['guest'] = array_merge(
                PriceVisibilitySetting::getDefaultSettings(),
                [
                    'role' => 'guest',
                    'can_see_wholesale_price' => false,
                    'can_see_supplier_info' => false,
                    'description' => 'کاربر مهمان (لاگین نکرده)',
                ]
            );
        }
    }

    /**
     * باز کردن مودال ویرایش
     */
    public function edit(string $role): void
    {
        $this->editingRole = $role;
        $this->editForm = $this->settings[$role] ?? PriceVisibilitySetting::getDefaultSettings();
        $this->showEditModal = true;
    }

    /**
     * ذخیره تغییرات
     */
    public function save(): void
    {
        // Validation
        $this->validate([
            'editForm.can_see_retail_price' => 'required|boolean',
            'editForm.can_see_wholesale_price' => 'required|boolean',
            'editForm.can_see_supplier_info' => 'required|boolean',
            'editForm.can_filter_by_price' => 'required|boolean',
            'editForm.can_set_retail_price' => 'required|boolean',
            'editForm.is_active' => 'required|boolean',
            'editForm.description' => 'nullable|string|max:500',
        ], [
            'editForm.description.max' => 'توضیحات نباید بیشتر از 500 کاراکتر باشد.',
        ]);

        try {
            // ذخیره یا بروزرسانی
            $setting = PriceVisibilitySetting::updateOrCreate(
                ['role' => $this->editingRole],
                [
                    'can_see_retail_price' => (bool)$this->editForm['can_see_retail_price'],
                    'can_see_wholesale_price' => (bool)$this->editForm['can_see_wholesale_price'],
                    'can_see_supplier_info' => (bool)$this->editForm['can_see_supplier_info'],
                    'can_filter_by_price' => (bool)$this->editForm['can_filter_by_price'],
                    'can_set_retail_price' => (bool)$this->editForm['can_set_retail_price'],
                    'restricted_price_types' => $this->editForm['restricted_price_types'] ?? null,
                    'description' => $this->editForm['description'] ?? null,
                    'is_active' => (bool)$this->editForm['is_active'],
                ]
            );

            // پاک کردن کش
            $setting->clearCache();

            // بروزرسانی state
            $this->loadSettings();
            $this->closeModal();

            // پیام موفقیت
            $this->dispatch('toast', 
                type: 'success',
                message: 'تنظیمات با موفقیت ذخیره شد'
            );

        } catch (\Exception $e) {
            $this->dispatch('toast', 
                type: 'error',
                message: 'خطا در ذخیره تنظیمات: ' . $e->getMessage()
            );
        }
    }

    /**
     * تغییر وضعیت فعال/غیرفعال به صورت سریع
     */
    public function toggleActive(string $role): void
    {
        try {
            $setting = PriceVisibilitySetting::where('role', $role)->first();
            
            if (!$setting) {
                // اگر تنظیماتی نبود، ابتدا ایجاد کن
                $setting = PriceVisibilitySetting::create(array_merge(
                    PriceVisibilitySetting::getDefaultSettings(),
                    ['role' => $role, 'is_active' => false]
                ));
            }

            $setting->is_active = !$setting->is_active;
            $setting->save();

            $this->loadSettings();

            $this->dispatch('toast', 
                type: 'success',
                message: $setting->is_active ? 'تنظیمات فعال شد' : 'تنظیمات غیرفعال شد'
            );

        } catch (\Exception $e) {
            $this->dispatch('toast', 
                type: 'error',
                message: 'خطا در تغییر وضعیت: ' . $e->getMessage()
            );
        }
    }

    /**
     * بستن مودال
     */
    public function closeModal(): void
    {
        $this->showEditModal = false;
        $this->editingRole = null;
        $this->editForm = [];
        $this->resetValidation();
    }

    /**
     * دریافت لیبل فارسی برای نقش
     */
    public function getRoleLabel(string $role): string
    {
        if ($role === 'guest') {
            return 'مهمان (لاگین نکرده)';
        }

        try {
            $userRole = UserRole::from($role);
            return $userRole->label();
        } catch (\ValueError $e) {
            return $role;
        }
    }

    /**
     * دریافت رنگ badge برای نقش
     */
    public function getRoleColor(string $role): string
    {
        if ($role === 'guest') {
            return 'gray';
        }

        try {
            $userRole = UserRole::from($role);
            return $userRole->color();
        } catch (\ValueError $e) {
            return 'gray';
        }
    }

    /**
     * پاک کردن تمام کش‌ها
     */
    public function clearAllCache(): void
    {
        PriceVisibilitySetting::clearAllCache();
        
        $this->dispatch('toast', 
            type: 'success',
            message: 'تمام کش‌ها پاک شدند'
        );
    }

    /**
     * بازگشت به تنظیمات پیش‌فرض برای یک نقش
     */
    public function resetToDefault(string $role): void
    {
        try {
            $setting = PriceVisibilitySetting::where('role', $role)->first();
            
            if ($setting) {
                $setting->delete();
            }

            $this->loadSettings();

            $this->dispatch('toast', 
                type: 'success',
                message: 'تنظیمات به حالت پیش‌فرض بازگشت'
            );

        } catch (\Exception $e) {
            $this->dispatch('toast', 
                type: 'error',
                message: 'خطا در بازگشت به پیش‌فرض: ' . $e->getMessage()
            );
        }
    }

    /**
     * Render component
     */
    public function render()
    {
        return view('livewire.admin.settings.manage-price-visibility', [
            'allRoles' => $this->getAllRoles(),
        ]);
    }

    /**
     * دریافت تمام نقش‌ها
     */
    private function getAllRoles(): array
    {
        $roles = ['guest'];
        
        foreach (UserRole::cases() as $role) {
            $roles[] = $role->value;
        }

        return $roles;
    }
}
