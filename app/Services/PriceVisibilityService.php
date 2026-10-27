<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\PriceVisibilitySetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * سرویس یکپارچه برای مدیریت نمایش قیمت‌ها
 * 
 * این سرویس تنها منبع حقیقت (Single Source of Truth) برای تصمیم‌گیری
 * درباره نمایش قیمت‌ها در تمام بخش‌های سیستم است.
 */
class PriceVisibilityService
{
    /**
     * دریافت نقش کاربر فعلی
     */
    public function getUserRole(?User $user = null): string
    {
        if (!$user) {
            return 'guest';
        }

        // ترتیب اولویت نقش‌ها
        if ($user->hasRole('super_admin')) {
            return 'super_admin';
        }

        if ($user->hasRole('supplier')) {
            return 'supplier';
        }

        if ($user->hasRole('regular_supplier')) {
            return 'regular_supplier';
        }

        if ($user->hasRole('retailer_premium')) {
            return 'retailer_premium';
        }

        if ($user->hasRole('retailer_basic')) {
            return 'retailer_basic';
        }

        return 'end_user';
    }

    /**
     * دریافت تنظیمات نمایش قیمت برای کاربر
     */
    public function getSettings(?User $user = null): ?PriceVisibilitySetting
    {
        $role = $this->getUserRole($user);
        return PriceVisibilitySetting::getForRole($role);
    }

    /**
     * آیا کاربر می‌تواند قیمت خرده‌فروشی ببیند؟
     */
    public function canSeeRetailPrice(?User $user = null, ?Product $product = null): bool
    {
        $settings = $this->getSettings($user);

        if (!$settings) {
            // اگر تنظیماتی نباشد، به صورت پیش‌فرض قیمت خرده‌فروشی نمایش داده می‌شود
            return true;
        }

        return $settings->canSeeRetailPrice();
    }

    /**
     * آیا کاربر می‌تواند قیمت عمده ببیند؟
     */
    public function canSeeWholesalePrice(?User $user = null, ?Product $product = null): bool
    {
        $settings = $this->getSettings($user);

        if (!$settings) {
            // اگر تنظیماتی نباشد، قیمت عمده نمایش داده نمی‌شود
            return false;
        }

        return $settings->canSeeWholesalePrice();
    }

    /**
     * آیا کاربر می‌تواند اطلاعات تامین‌کننده ببیند؟
     */
    public function canSeeSupplierInfo(?User $user = null, ?Product $product = null): bool
    {
        $settings = $this->getSettings($user);

        if (!$settings) {
            return false;
        }

        return $settings->canSeeSupplierInfo();
    }

    /**
     * آیا کاربر می‌تواند از فیلتر قیمتی استفاده کند؟
     */
    public function canFilterByPrice(?User $user = null): bool
    {
        $settings = $this->getSettings($user);

        if (!$settings) {
            return false;
        }

        return $settings->canFilterByPrice();
    }

    /**
     * آیا کاربر می‌تواند قیمت خرده‌فروشی ثبت کند؟
     */
    public function canSetRetailPrice(?User $user = null): bool
    {
        $settings = $this->getSettings($user);

        if (!$settings) {
            return false;
        }

        return $settings->canSetRetailPrice();
    }

    /**
     * آیا کاربر می‌تواند هر نوع قیمتی ببیند؟
     * (بررسی کلی - اگر حداقل یکی از قیمت‌ها قابل مشاهده باشد)
     */
    public function canSeeAnyPrice(?User $user = null, ?Product $product = null): bool
    {
        return $this->canSeeRetailPrice($user, $product) 
            || $this->canSeeWholesalePrice($user, $product);
    }

    /**
     * آیا نوع قیمت خاص برای کاربر مجاز است؟
     * 
     * @param string $priceTypeCode کد نوع قیمت (مثلاً 'cash', 'check')
     */
    public function isPriceTypeAllowed(string $priceTypeCode, ?User $user = null): bool
    {
        $settings = $this->getSettings($user);

        if (!$settings) {
            // اگر تنظیماتی نباشد، به صورت پیش‌فرض همه مجاز است
            return true;
        }

        return $settings->isPriceTypeAllowed($priceTypeCode);
    }

    /**
     * فیلتر کردن قیمت‌ها بر اساس دسترسی کاربر
     * 
     * @param Collection $prices مجموعه قیمت‌ها (ProductPrice)
     * @param User|null $user کاربر (null = guest)
     * @return Collection قیمت‌های فیلتر شده
     */
    public function filterPricesByVisibility(Collection $prices, ?User $user = null): Collection
    {
        $settings = $this->getSettings($user);

        if (!$settings) {
            // اگر تنظیماتی نباشد، همه قیمت‌ها برگردانده می‌شود
            return $prices;
        }

        // فیلتر بر اساس نوع قیمت‌های مجاز
        $allowedTypes = $settings->getAllowedPriceTypes();

        if ($allowedTypes === null) {
            // null = همه مجاز
            return $prices;
        }

        // فیلتر کردن
        return $prices->filter(function ($price) use ($allowedTypes) {
            $priceTypeCode = $price->priceType?->code;
            return $priceTypeCode !== null && in_array($priceTypeCode, $allowedTypes, true);
        });
    }

    public function getCardPrices(Product $product, ?User $user = null, ?\App\Models\Supplier $supplier = null): array
    {
        $retail = null;
        if ($this->canSeeRetailPrice($user, $product) && $product->relationLoaded('retailPrices')) {
            $retail = $product->getRelation('retailPrices')->where('is_published', true)->sortBy('retail_price')->first();
        }
        $prices = collect();
        $supplierIds = [];
        if ($this->canSeeWholesalePrice($user, $product) && $product->relationLoaded('productSuppliers')) {
            $today = now()->toDateString();
            foreach ($product->getRelation('productSuppliers') as $pivot) {
                $pivotSupplier = $pivot->relationLoaded('supplier') ? $pivot->getRelation('supplier') : null;
                if (!$pivot->is_active || !$pivotSupplier?->is_active || !$pivot->relationLoaded('prices')
                    || ($supplier && (int) $pivot->supplier_id !== (int) $supplier->id)) continue;
                $valid = $pivot->getRelation('prices')->filter(function ($price) use ($today) {
                    return $price->is_active
                        && (!$price->valid_from || \Carbon\Carbon::parse($price->valid_from)->toDateString() <= $today)
                        && (!$price->valid_until || \Carbon\Carbon::parse($price->valid_until)->toDateString() >= $today);
                });
                $settings = $this->getSettings($user);
                $allowed = $settings?->getAllowedPriceTypes();
                if ($allowed !== null) {
                    $valid = $valid->filter(fn ($price) => $price->relationLoaded('priceType') && $price->getRelation('priceType')
                        && in_array($price->getRelation('priceType')->code, $allowed, true));
                }
                if ($valid->isNotEmpty()) {
                    $prices = $prices->merge($valid);
                    $supplierIds[(int) $pivot->supplier_id] = true;
                }
            }
        }
        return ['retail' => $retail, 'wholesale' => $prices, 'suppliers_count' => count($supplierIds)];
    }

    /**
     * دریافت داده‌های قابل مشاهده برای یک محصول و کاربر
     * (جایگزین ProductController::resolveVisibleDataByRole)
     * 
     * @return array
     */
    public function getVisibleDataForProduct(Product $product, ?User $user = null): array
    {
        $role = $this->getUserRole($user);
        $settings = $this->getSettings($user);

        $data = [
            'userRole'              => $role,
            'showRetailPrice'       => $this->canSeeRetailPrice($user, $product),
            'showWholesalePrice'    => $this->canSeeWholesalePrice($user, $product),
            'showSupplierInfo'      => $this->canSeeSupplierInfo($user, $product),
            'canFilterByPrice'      => $this->canFilterByPrice($user),
            'canSetRetailPrice'     => $this->canSetRetailPrice($user),
            'allowedPriceTypes'     => $settings?->getAllowedPriceTypes(),
        ];

        return $data;
    }

    /**
     * دریافت تمام دسترسی‌های کاربر به صورت خلاصه
     * (برای استفاده در API یا debugging)
     */
    public function getUserPermissionsSummary(?User $user = null): array
    {
        $role = $this->getUserRole($user);
        $settings = $this->getSettings($user);

        return [
            'role' => $role,
            'permissions' => [
                'can_see_retail_price' => $this->canSeeRetailPrice($user),
                'can_see_wholesale_price' => $this->canSeeWholesalePrice($user),
                'can_see_supplier_info' => $this->canSeeSupplierInfo($user),
                'can_filter_by_price' => $this->canFilterByPrice($user),
                'can_set_retail_price' => $this->canSetRetailPrice($user),
            ],
            'allowed_price_types' => $settings?->getAllowedPriceTypes() ?? 'all',
            'settings_active' => $settings?->is_active ?? false,
        ];
    }

    /**
     * بررسی اینکه آیا کاربر نقش خاصی دارد که به قیمت دسترسی داشته باشد
     * (برای استفاده در middleware یا authorization)
     */
    public function hasAccessToPrice(?User $user = null): bool
    {
        return $this->canSeeAnyPrice($user);
    }

    /**
     * دریافت پیام مناسب برای کاربرانی که دسترسی ندارند
     */
    public function getAccessDeniedMessage(?User $user = null): string
    {
        $role = $this->getUserRole($user);

        if ($role === 'guest') {
            return 'برای مشاهده قیمت‌ها لطفاً وارد حساب کاربری خود شوید.';
        }

        if ($role === 'end_user') {
            return 'برای دسترسی به قیمت‌های عمده، لطفاً اشتراک خود را ارتقا دهید.';
        }

        return 'شما دسترسی لازم برای مشاهده این اطلاعات را ندارید.';
    }

    /**
     * پاک کردن Cache تمام تنظیمات
     * (برای استفاده بعد از تغییر تنظیمات در پنل ادمین)
     */
    public function clearCache(): void
    {
        PriceVisibilitySetting::clearAllCache();
    }
}
