# تحلیل سیستم نمایش قیمت در صفحات Web

**تاریخ بررسی:** 2026-09-14  
**وضعیت فعلی:** ✅ سیستم وجود دارد اما **غیریکپارچه** و **غیرمتمرکز**

---

## 📊 وضعیت فعلی سیستم

### 1. نقش‌های کاربری (UserRole Enum)
```php
// app/Enums/UserRole.php
- super_admin        → دسترسی کامل
- supplier           → تامین‌کننده
- regular_supplier   → تامین‌کننده عادی
- retailer_premium   → کاسب ویژه
- retailer_basic     → کاسب عادی
- end_user           → کاربر عادی
- guest              → مهمان (بدون لاگین)
```

### 2. منطق نمایش قیمت (Price Visibility Logic)

#### 🔍 مکان‌های مختلف تعریف شده:

#### الف) ProductController::resolveVisibleDataByRole()
**مسیر:** `app/Http/Controllers/Web/ProductController.php`

```php
private function resolveVisibleDataByRole(Product $product, string $userRole): array
{
    $data = [
        'showSupplierInfo'    => false,
        'showWholesalePrices' => false,
        'showRetailPrice'     => true,
        'canSetRetailPrice'   => false,
        // ...
    ];

    switch ($userRole) {
        case 'retailer_premium':
            $data['showSupplierInfo']    = true;
            $data['showWholesalePrices'] = true;
            $data['canSetRetailPrice']   = true;
            break;

        case 'retailer_basic':
            $data['canSetRetailPrice'] = true;
            // قیمت عمده نمایش داده نمی‌شود
            break;

        case 'supplier':
        case 'super_admin':
            $data['showSupplierInfo']    = true;
            $data['showWholesalePrices'] = true;
            break;

        case 'end_user':
        case 'guest':
        default:
            // فقط قیمت خرده‌فروشی نمایش داده می‌شود
            break;
    }

    return $data;
}
```

#### ب) Component: product/card.blade.php
**مسیر:** `resources/views/components/product/card.blade.php`

```php
$canSeeSupplier = in_array($userRole, ['super_admin', 'supplier', 'retailer_premium'], true);
$canSeeWholesalePrice = in_array($userRole, ['super_admin', 'supplier', 'retailer_premium'], true);
```

#### ج) Component: product-card-inline.blade.php
**مسیر:** `resources/views/components/product-card-inline.blade.php`

```php
$canSeePrices    = in_array($userRole, ['super_admin', 'supplier', 'retailer_premium', 'retailer_basic']);
$canSeeWholesale = in_array($userRole, ['super_admin', 'supplier', 'retailer_premium']);
$canSeeSupplier  = in_array($userRole, ['super_admin', 'supplier', 'retailer_premium']);
```

#### د) صفحه جزئیات محصول: show.blade.php
**مسیر:** `resources/views/pages/products/show.blade.php`

```php
@if($showWholesalePrices && ...)
    // نمایش قیمت‌های عمده و ماتریس تامین‌کنندگان
@endif

@if($canViewSupplierInfo)
    // نمایش اطلاعات تماس تامین‌کننده
@endif
```

---

## ⚠️ مشکلات سیستم فعلی

### 1. **عدم تمرکز (Decentralized Logic)**
- منطق نمایش قیمت در **4 مکان مختلف** تکرار شده است
- هر component یا controller به صورت جداگانه چک می‌کند
- خطر **inconsistency** بین بخش‌های مختلف سایت

### 2. **Hard-coded Role Checks**
```php
// ❌ این منطق در 4 فایل مختلف تکرار شده
in_array($userRole, ['super_admin', 'supplier', 'retailer_premium'])
```
- اگر نقش جدیدی اضافه شود، باید **همه فایل‌ها** ویرایش شوند
- احتمال فراموشی و ایجاد bug بالا است

### 3. **عدم قابلیت مدیریت از پنل ادمین**
- ❌ جدول یا تنظیماتی برای مدیریت دسترسی‌ها وجود **ندارد**
- ❌ ادمین نمی‌تواند بدون تغییر کد، دسترسی‌ها را تغییر دهد
- ❌ نمی‌توان برای محصولات خاص، محدودیت خاص تعریف کرد

### 4. **عدم انعطاف‌پذیری**
- نمی‌توان **فقط قیمت‌های خاص** (مثلاً فقط نقدی) را نمایش داد
- نمی‌توان **محدودیت جزئی** (per product, per category) تعریف کرد
- نمی‌توان برای **end_user** قیمت نمایش داد (حتی اگر بخواهیم)

---

## 🎯 ماتریس دسترسی فعلی

| نقش کاربری        | قیمت خرده‌فروشی | قیمت عمده | اطلاعات تامین‌کننده | ثبت قیمت خرده |
|-------------------|------------------|-----------|---------------------|---------------|
| **guest**         | ✅               | ❌        | ❌                  | ❌            |
| **end_user**      | ✅               | ❌        | ❌                  | ❌            |
| **retailer_basic**| ✅               | ❌        | ❌                  | ✅            |
| **retailer_premium** | ✅            | ✅        | ✅                  | ✅            |
| **supplier**      | ✅               | ✅        | ✅                  | ❌            |
| **regular_supplier** | ✅            | ❓ (نامشخص) | ❓                 | ❌            |
| **super_admin**   | ✅               | ✅        | ✅                  | ✅            |

**نکته:** `regular_supplier` در منطق فعلی مشخص نیست چه دسترسی‌هایی دارد!

---

## 📂 فایل‌های مرتبط

### Controllers
- `app/Http/Controllers/Web/ProductController.php`
  - `resolveUserRole()` - line 1019
  - `resolveVisibleDataByRole()` - line 1049
  - `userCanSeePrice()` - line 745

### Views/Components
- `resources/views/components/product/card.blade.php` - کارت محصول (Grid/List)
- `resources/views/components/product-card-inline.blade.php` - کارت خطی
- `resources/views/pages/products/show.blade.php` - صفحه جزئیات محصول

### Enums
- `app/Enums/UserRole.php` - نقش‌های کاربری

### Policies (موجود اما بررسی نشده)
- `app/Policies/RetailPricePolicy.php`
- `app/Policies/ProductPolicy.php`

---

## 🔧 نیازمندی‌ها برای سیستم یکپارچه

### 1. **جدول تنظیمات دسترسی قیمت**
```sql
CREATE TABLE price_visibility_settings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    role VARCHAR(50) NOT NULL,              -- نقش کاربری
    can_see_retail_price BOOLEAN DEFAULT TRUE,
    can_see_wholesale_price BOOLEAN DEFAULT FALSE,
    can_see_supplier_info BOOLEAN DEFAULT FALSE,
    can_filter_by_price BOOLEAN DEFAULT FALSE,
    restricted_price_types JSON NULL,        -- محدودیت برای انواع خاص قیمت
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_role (role)
);
```

### 2. **محدودیت‌های جزئی (Optional - Phase 2)**
```sql
CREATE TABLE price_visibility_rules (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    entity_type ENUM('product', 'category', 'supplier'),
    entity_id BIGINT NOT NULL,
    role VARCHAR(50) NOT NULL,
    can_see_wholesale BOOLEAN DEFAULT FALSE,
    can_see_supplier_info BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_role (role)
);
```

### 3. **Service Class متمرکز**
```php
// app/Services/PriceVisibilityService.php
class PriceVisibilityService
{
    public function canSeeRetailPrice(?User $user): bool { }
    public function canSeeWholesalePrice(?User $user): bool { }
    public function canSeeSupplierInfo(?User $user): bool { }
    public function canSeePrice(?User $user, Product $product = null): bool { }
    public function getVisiblePriceTypes(?User $user, Product $product = null): array { }
    public function filterPricesByVisibility(Collection $prices, ?User $user): Collection { }
}
```

### 4. **Blade Directive سفارشی**
```php
// app/Providers/AppServiceProvider.php
Blade::directive('canSeeWholesalePrice', function () {
    return "<?php if (app(PriceVisibilityService::class)->canSeeWholesalePrice(auth()->user())): ?>";
});

// در Blade:
@canSeeWholesalePrice
    <!-- نمایش قیمت عمده -->
@endcanSeeWholesalePrice
```

### 5. **پنل مدیریت در Admin**
- صفحه: `/admin/settings/price-visibility`
- امکانات:
  - ✅ تنظیم دسترسی‌ها برای هر نقش
  - ✅ مدیریت محدودیت‌های جزئی (اختیاری)
  - ✅ پیش‌نمایش تغییرات
  - ✅ لاگ تغییرات

---

## 📋 پلان پیاده‌سازی (Roadmap)

### Phase 1: پایه‌سازی (اولویت بالا) ⭐⭐⭐
1. ✅ ایجاد migration برای `price_visibility_settings`
2. ✅ ایجاد Model و Seeder
3. ✅ ایجاد `PriceVisibilityService`
4. ✅ تست و اطمینان از عملکرد صحیح

### Phase 2: یکپارچه‌سازی کنترلر و View (اولویت بالا) ⭐⭐⭐
1. ✅ بازنویسی `ProductController::resolveVisibleDataByRole()` با Service جدید
2. ✅ حذف hard-coded checks از Components
3. ✅ استفاده از Service در همه جا
4. ✅ تست رگرسیون کامل

### Phase 3: پنل ادمین (اولویت متوسط) ⭐⭐
1. ✅ ایجاد Livewire Component برای مدیریت
2. ✅ صفحه تنظیمات در پنل ادمین
3. ✅ UI/UX برای تنظیم دسترسی‌ها

### Phase 4: محدودیت‌های جزئی (اولویت پایین - اختیاری) ⭐
1. ⏸️ ایجاد `price_visibility_rules` (در صورت نیاز)
2. ⏸️ منطق اولویت‌بندی قوانین
3. ⏸️ UI برای مدیریت قوانین جزئی

---

## 🚨 نکات مهم امنیتی

### 1. **Authorization در API**
```php
// ❌ فعلاً بررسی نشده - نیاز به بررسی
app/Http/Controllers/Api/V1/ProductApiController.php
```

### 2. **Caching تنظیمات**
```php
// برای بهبود performance، تنظیمات را cache کنیم
Cache::remember("price_visibility_{$role}", 3600, function() {
    return PriceVisibilitySetting::where('role', $role)->first();
});
```

### 3. **Event Logging**
```php
// ثبت تغییرات دسترسی در لاگ
Event::dispatch(new PriceVisibilityChanged($role, $oldSettings, $newSettings));
```

---

## ✅ توصیه‌های نهایی

### 1. **شروع از Phase 1 و Phase 2**
این دو فاز حیاتی هستند و باید در اولویت باشند.

### 2. **یک منبع واقعیت (Single Source of Truth)**
همه چک‌های دسترسی باید از `PriceVisibilityService` بیایند.

### 3. **Backward Compatibility**
در هنگام تغییر، مطمئن شوید که رفتار فعلی سایت تغییر نکند.

### 4. **Testing Strategy**
- Unit Tests برای `PriceVisibilityService`
- Integration Tests برای Controllers
- Browser Tests برای صفحات کاربری

### 5. **Documentation**
- مستندات API
- راهنمای کاربری برای ادمین
- دیاگرام جریان تصمیم‌گیری

---

## 📞 سوالات برای تصمیم‌گیری

1. **آیا نیاز به محدودیت‌های جزئی (per product/category) داریم؟**
   - اگر بله → Phase 4 را هم پیاده کنیم
   - اگر خیر → فقط Phase 1-3 کافی است

2. **آیا می‌خواهیم `end_user` بتواند قیمت ببیند؟**
   - فعلاً: خیر
   - اگر در آینده بله → سیستم جدید آماده است

3. **آیا نیاز به تاریخچه تغییرات دسترسی‌ها داریم؟**
   - برای audit log مفید است
   - اضافه کردن `price_visibility_changes` table

4. **آیا برای API هم همین منطق اعمال شود؟**
   - ⚠️ حتماً باید بررسی شود
   - احتمال security hole در API

---

**پایان گزارش**  
**آماده برای شروع پیاده‌سازی:** ✅
