<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PriceVisibilitySetting extends Model
{
    protected $fillable = [
        'role',
        'can_see_retail_price',
        'can_see_wholesale_price',
        'can_see_supplier_info',
        'can_filter_by_price',
        'can_set_retail_price',
        'restricted_price_types',
        'description',
        'is_active',
    ];

    protected $casts = [
        'can_see_retail_price' => 'boolean',
        'can_see_wholesale_price' => 'boolean',
        'can_see_supplier_info' => 'boolean',
        'can_filter_by_price' => 'boolean',
        'can_set_retail_price' => 'boolean',
        'restricted_price_types' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * دریافت تنظیمات برای یک نقش خاص (با Cache)
     */
    public static function getForRole(string $role): ?self
    {
        return Cache::remember(
            "price_visibility_settings_{$role}",
            now()->addHours(24),
            fn() => self::where('role', $role)
                ->where('is_active', true)
                ->first()
        );
    }

    /**
     * دریافت تنظیمات برای یک UserRole enum
     */
    public static function getForUserRole(UserRole $userRole): ?self
    {
        return self::getForRole($userRole->value);
    }

    /**
     * پاک کردن Cache برای این نقش
     */
    public function clearCache(): void
    {
        Cache::forget("price_visibility_settings_{$this->role}");
    }

    /**
     * پاک کردن Cache برای همه نقش‌ها
     */
    public static function clearAllCache(): void
    {
        foreach (UserRole::cases() as $role) {
            Cache::forget("price_visibility_settings_{$role->value}");
        }
        
        // برای guest هم
        Cache::forget("price_visibility_settings_guest");
    }

    /**
     * Event listeners
     */
    protected static function booted(): void
    {
        // پاک کردن Cache بعد از save
        static::saved(function (self $setting) {
            $setting->clearCache();
        });

        // پاک کردن Cache بعد از delete
        static::deleted(function (self $setting) {
            $setting->clearCache();
        });
    }

    /**
     * آیا این نقش می‌تواند قیمت خرده‌فروشی ببیند؟
     */
    public function canSeeRetailPrice(): bool
    {
        return $this->is_active && $this->can_see_retail_price;
    }

    /**
     * آیا این نقش می‌تواند قیمت عمده ببیند؟
     */
    public function canSeeWholesalePrice(): bool
    {
        return $this->is_active && $this->can_see_wholesale_price;
    }

    /**
     * آیا این نقش می‌تواند اطلاعات تامین‌کننده ببیند؟
     */
    public function canSeeSupplierInfo(): bool
    {
        return $this->is_active && $this->can_see_supplier_info;
    }

    /**
     * آیا این نقش می‌تواند فیلتر قیمتی استفاده کند؟
     */
    public function canFilterByPrice(): bool
    {
        return $this->is_active && $this->can_filter_by_price;
    }

    /**
     * آیا این نقش می‌تواند قیمت خرده‌فروشی ثبت کند؟
     */
    public function canSetRetailPrice(): bool
    {
        return $this->is_active && $this->can_set_retail_price;
    }

    /**
     * آیا نوع قیمت مشخص شده مجاز است؟
     * 
     * @param string $priceTypeCode کد نوع قیمت (مثلاً 'cash', 'check')
     * @return bool
     */
    public function isPriceTypeAllowed(string $priceTypeCode): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // اگر محدودیتی نداشته باشیم، همه مجاز است
        if (empty($this->restricted_price_types)) {
            return true;
        }

        // بررسی در لیست محدودیت‌ها
        return in_array($priceTypeCode, $this->restricted_price_types, true);
    }

    /**
     * دریافت لیست کدهای انواع قیمت مجاز
     * 
     * @return array|null null = همه مجاز، array = فقط موارد مشخص شده
     */
    public function getAllowedPriceTypes(): ?array
    {
        if (!$this->is_active) {
            return [];
        }

        return $this->restricted_price_types;
    }

    /**
     * Scope: فقط تنظیمات فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: برای یک نقش خاص
     */
    public function scopeForRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * دریافت تنظیمات پیش‌فرض برای نقش‌هایی که تنظیمات ندارند
     */
    public static function getDefaultSettings(): array
    {
        return [
            'can_see_retail_price' => true,
            'can_see_wholesale_price' => false,
            'can_see_supplier_info' => false,
            'can_filter_by_price' => false,
            'can_set_retail_price' => false,
            'restricted_price_types' => null,
            'is_active' => true,
        ];
    }

    /**
     * دریافت یا ایجاد تنظیمات برای یک نقش
     */
    public static function getOrCreateForRole(string $role): self
    {
        $setting = self::getForRole($role);
        
        if (!$setting) {
            $setting = self::create(array_merge(
                self::getDefaultSettings(),
                ['role' => $role]
            ));
        }

        return $setting;
    }
}
