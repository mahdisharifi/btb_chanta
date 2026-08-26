# قوانین همکاری پروژه B2B Marketplace

## تکنولوژی‌های پروژه
- **Framework:** Laravel 12.5
- **Frontend:** Livewire + Alpine.js
- **Date/Time:** Morilog/Jalali (نمونه: `jdate(now())->format('Y-m-d')`)
- **Icons:** Heroicons

## نقش من در پروژه
- ✅ همیشه حقیقت را بگو، بدون تلاش برای تایید کاربر
- ✅ بررسی کدهای آپلود شده و واقعی (مگر اینکه کاربر خودش کد را پیست کرده باشد)
- ✅ عدم ایجاد وابستگی به خارج از سرور
- ✅ عدم حذف یا تغییر بی‌دلیل منطق‌ها (متغیرها، حلقه‌ها، و...)

## قوانین Git و Version Control

### دستورات Git فقط با اجازه کاربر
- ❌ **هیچ دستور Git نباید بدون اجازه صریح کاربر اجرا شود**
- ✅ فقط وقتی کاربر صریحاً بگه "commit کن" یا "push کن"
- ✅ پیش از commit، از کاربر اجازه بگیر

### فلسفه Commit (Atomic Commits)
**چرا بعد از تکمیل commit می‌کنیم؟**
1. **تست و اطمینان:** تغییرات باید کامل تست شده باشند
2. **جلوگیری از Broken Commits:** هر commit باید کار کند
3. **Rollback Safety:** اگر مشکلی پیش آمد، به commit قبلی برگردیم
4. **Clean History:** تاریخچه Git داستان توسعه است، نه debugging

### روند صحیح Commit
```
1. تغییرات را انجام بده
2. کد را تست کن (php artisan serve, route:list, etc.)
3. اگر خطا دارد، اصلاح کن و دوباره تست کن
4. وقتی همه چیز کار می‌کند → از کاربر اجازه بگیر
5. با تایید کاربر: git add, git commit
6. با تایید جداگانه کاربر: git push
```

### قوانین Commit
- ✅ هر commit باید **یک تغییر منطقی کامل** باشد
- ✅ commit message باید **واضح و توضیحی** باشد
- ✅ قبل از commit حتماً **تست کن**
- ❌ commit نکن اگر کد خطا دارد
- ❌ commit نکن اگر feature ناتمام است

### تنظیم پروکسی برای Git
Git به صورت پیش‌فرض از پروکسی ویندوز استفاده نمی‌کند. برای اطمینان از اتصال:

**تنظیم پروکسی از تنظیمات ویندوز:**
```powershell
# دریافت پروکسی فعال ویندوز
$proxy = (Get-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Internet Settings').ProxyServer

# اعمال روی Git
git config --global http.proxy "http://$proxy"
git config --global https.proxy "http://$proxy"
```

**یا تنظیم دستی:**
```powershell
git config --global http.proxy "http://22.123.141.225:8080"
git config --global https.proxy "http://22.123.141.225:8080"
```

**بررسی پروکسی فعلی:**
```powershell
git config --global --get http.proxy
git config --global --get https.proxy
```

**حذف پروکسی (اگر مشکلی پیش آمد):**
```powershell
git config --global --unset http.proxy
git config --global --unset https.proxy
```

**نکته:** اگر پروکسی سیستم تغییر کرد، باید Git را هم آپدیت کنید.

### نمونه Workflow
```bash
# ❌ اشتباه:
- هر خط کد → commit
- قبل از تست → commit
- کار نصفه → commit

# ✅ درست:
- Feature کامل شد
- تست شد و کار می‌کند
- از کاربر اجازه گرفتیم
- حالا commit می‌کنیم
```

## کارشناسان مشاور در توسعه
هر تغییر باید با تایید کارشناسان زیر باشد:

1. ✅ گرافیک
2. ✅ برندینگ
3. ✅ رابط کاربری / فرانت‌اند
4. ✅ رسپانسیو
5. ✅ سئو و رنکینگ سایت
6. ✅ کاربر وب موبایل
7. ✅ میزبانی و استقرار برنامه (هاست اشتراکی)
8. ✅ احراز هویت و سطوح دسترسی
9. ✅ ای‌پی‌آی‌ها و منطق بک‌انند
10. ✅ پایگاه داده و ذخیره‌سازی
11. ✅ امنیت و امنیت در سطح API
12. ✅ کش و بازسازی کش - شبکه توزیع محتوا (CDN)
13. ✅ کاربر همزمان
14. ✅ توسعه برنامه موبایل
15. ✅ Rate Limiting (محدودسازی نرخ درخواست‌ها)
16. ✅ توزیع بار و مقیاس‌پذیری
17. ✅ ردیابی خطاها و ثبت وقایع/لاگ‌ها
18. ✅ پایداری سیستم و بازیابی اطلاعات
19. ✅ search.google.com (Google Search Console)

## تنظیم قالب‌ها (Layouts)

### ادمین
```php
#[Layout('components.layouts.admin')]
```

### کنترل‌های وب
```blade
@extends('layouts.app')
```

### نمایش کاتالوگ
```php
#[Layout('components.layouts.blank')]
```

## استانداردهای کدنویسی

### امنیت در همه صفحات (الزامی)

**این بخش برای تمام صفحات جدید و قدیمی الزامی است:**

#### 1. Validation چندلایه
```php
public function rules(): array
{
    return [
        'field' => ['required', 'string', 'max:255', 'regex:/^[allowed_chars]+$/'],
        'price' => ['numeric', 'min:0', 'max:999999999999'],
        'status' => ['in:active,inactive'], // فقط مقادیر مجاز
    ];
}

public function messages(): array
{
    return [
        'field.required' => 'این فیلد الزامی است.',
        'field.regex' => 'فرمت ورودی نامعتبر است.',
        // ...
    ];
}
```

#### 2. Authorization (بررسی دسترسی)
```php
// قبل از هر عملیات CRUD
$userId = Auth::id();
$supplierId = Auth::user()->supplier->id;

// بررسی مالکیت
if ($resource->user_id !== $userId) {
    abort(403, 'شما مجاز به دسترسی به این محتوا نیستید.');
}
```

#### 3. Input Sanitization
```php
private function sanitizeInput(?string $input): ?string
{
    if ($input === null) return null;
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// استفاده
'name' => $this->sanitizeInput($this->name)
```

#### 4. Type Casting (تبدیل صریح)
```php
'user_id' => (int)$userId,
'price' => (float)$price,
'is_active' => (bool)$isActive,
'count' => $count ? (int)$count : null,
```

#### 5. جلوگیری از اسکرول موس در فیلدهای عددی
```blade
<input type="number" onwheel="this.blur()" />
```

#### 6. حفظ Referential Integrity
```php
// قبل از حذف، بررسی استفاده در جاهای دیگر
if ($model->relatedRecords()->exists()) {
    return $this->error('این رکورد در استفاده است و قابل حذف نیست.');
}
```

#### 7. Transaction Safety
```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    // عملیات‌های مرتبط
    Model::create([...]);
    AnotherModel::update([...]);
});
```

#### چک‌لیست امنیتی (قبل از تکمیل هر صفحه)
- [ ] Validation با regex, min, max, in
- [ ] Authorization برای همه CRUD operations  
- [ ] Sanitization برای string inputs
- [ ] Type Casting برای همه متغیرها
- [ ] `onwheel="this.blur()"` برای input[type=number]
- [ ] بررسی روابط قبل از delete
- [ ] استفاده از DB::transaction برای عملیات چندمرحله‌ای
- [ ] Toast notification برای موفقیت/خطا
- [ ] Messages فارسی برای validation errors

**مستندات بیشتر:** `.kiro/docs/price-matrix-security.md`

---

### تاریخ و زمان
```php
// استفاده از Jalali
jdate(now())->format('Y-m-d')

// یا با Jalalian
use Morilog\Jalali\Jalalian;
Jalalian::now()->format('Y-m-d')
```

### آیکون‌ها
```blade
// استفاده از Heroicons
<x-heroicon-o-user />
<x-heroicon-s-home />
```

## نکات مهم

1. **عدم وابستگی خارجی:** همه سرویس‌ها باید در سرور داخلی باشند
2. **حفظ منطق:** هیچ کدی بدون دلیل حذف یا تغییر ندهد
3. **مشورت کارشناسی:** تغییرات بزرگ با تایید کارشناسان مربوطه
4. **بررسی کد واقعی:** همیشه کدهای واقعی پروژه را بررسی کن (مگر اینکه کاربر کد پیست کرده باشد)

---

**تاریخ ایجاد:** 2026-08-02  
**وضعیت:** فعال
