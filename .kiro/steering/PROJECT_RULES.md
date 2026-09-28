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

### Encoding فایل‌ها (الزامی)
**همه فایل‌های پروژه باید با UTF-8 بدون BOM ذخیره شوند:**

#### چرا BOM مشکل‌ساز است؟
- **BOM (Byte Order Mark)** یک کاراکتر نامرئی `EF BB BF` در ابتدای فایل است
- در Blade templates به صورت `&#xFEFF;` در HTML رندر می‌شود
- باعث مشکلات Layout، فاصله‌های اضافی، و خرابی Grid می‌شود
- در PHP headers می‌تواند خطای "headers already sent" ایجاد کند

#### بررسی و رفع BOM
**بررسی وجود BOM:**
```powershell
Get-Content "path/to/file.blade.php" -Encoding Byte | Select-Object -First 3 | ForEach-Object { "{0:X2}" -f $_ }
# اگر خروجی EF BB BF باشد → فایل BOM دارد
```

**حذف BOM از فایل:**
```powershell
$content = Get-Content "path/to/file.blade.php" -Raw
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText("$PWD/path/to/file.blade.php", $content, $utf8NoBom)
```

**تنظیم Editor:**
- **VS Code:** فایل را باز کن → در نوار پایین روی "UTF-8 with BOM" کلیک کن → "Save with Encoding" → "UTF-8" را انتخاب کن
- **PhpStorm:** Settings → Editor → File Encodings → تیک "Transparent native-to-ascii conversion" را بردار
- **Notepad++:** Encoding → "Encode in UTF-8 without BOM"

#### چک‌لیست Encoding
- [ ] تمام فایل‌های `.blade.php` باید UTF-8 بدون BOM باشند
- [ ] تمام فایل‌های `.php` باید UTF-8 بدون BOM باشند
- [ ] تمام فایل‌های `.js`, `.css`, `.vue` باید UTF-8 بدون BOM باشند
- [ ] بعد از ایجاد/ویرایش فایل‌های Blade حتماً بررسی کن
- [ ] بعد از رفع BOM حتماً `php artisan view:clear` اجرا کن

**قانون طلایی:** قبل از commit، فایل‌های تغییر یافته را برای BOM بررسی کن!

## راهنمای کاربر در صفحات (User Guide)

### فلسفه راهنمای کاربر
هر صفحه باید دارای راهنمای کامل و ساده باشد تا کاربر بدون نیاز به پشتیبانی بتواند از آن استفاده کند.

### استانداردهای راهنما

#### 1. ساختار راهنما
**راهنمای یک‌سطحی (توصیه شده):**
- یک راهنمای جامع کلی که تمام صفحه را توضیح دهد
- قابل باز/بسته شدن (collapsible) برای جلوگیری از شلوغی
- بخش‌بندی با عناوین واضح

```blade
<!-- نمونه ساختار راهنما -->
<div x-data="{ showGuide: false }" class="mb-6">
    <button @click="showGuide = !showGuide" 
            class="flex items-center gap-2 text-blue-600 hover:text-blue-700">
        <x-heroicon-o-information-circle class="w-5 h-5" />
        <span x-text="showGuide ? 'بستن راهنما' : 'راهنمای استفاده'"></span>
    </button>
    
    <div x-show="showGuide" 
         x-collapse
         class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <!-- محتوای راهنما -->
    </div>
</div>
```

**راهنمای دوسطحی (فقط در صورت ضرورت):**
- راهنمای کلی صفحه (بالای صفحه)
- راهنماهای جزئی هر بخش (tooltip یا info icon)
- **نکته:** فقط در صورتی استفاده شود که performance مشکلی ایجاد نکند

#### 2. محتوای راهنما
راهنما باید شامل موارد زیر باشد:

**الف. هدف صفحه:**
```
این صفحه برای ... استفاده می‌شود.
```

**ب. نحوه کار با صفحه:**
- توضیح هر فیلد/بخش به زبان ساده
- مثال‌های عملی
- فرمول محاسبات (اگر وجود دارد)

**ج. نکات مهم:**
- هشدارها و محدودیت‌ها
- رفتار پیش‌فرض سیستم
- تأثیر تنظیمات روی سایر بخش‌ها

**د. مثال محاسبه:**
```
مثال: اگر قیمت نقدی 100,000 تومان باشد و درصد افزایش 5% تعیین کنید:
قیمت چک = 100,000 + (100,000 × 5%) = 105,000 تومان
```

#### 3. طراحی UI راهنما
**رنگ‌بندی:**
- راهنمای عادی: `bg-blue-50 border-blue-200 text-blue-900`
- هشدار: `bg-yellow-50 border-yellow-200 text-yellow-900`
- خطر: `bg-red-50 border-red-200 text-red-900`
- موفقیت: `bg-green-50 border-green-200 text-green-900`

**آیکون:**
```blade
<x-heroicon-o-information-circle class="w-5 h-5" /> <!-- راهنما -->
<x-heroicon-o-exclamation-triangle class="w-5 h-5" /> <!-- هشدار -->
<x-heroicon-o-light-bulb class="w-5 h-5" /> <!-- نکته -->
```

**Typography:**
- عنوان راهنما: `text-base font-semibold`
- متن راهنما: `text-sm leading-relaxed`
- نکات: `text-xs italic`

#### 4. مثال کامل راهنما
**صفحه مدیریت دوره‌های چک:**

```blade
<div x-data="{ showGuide: false }" class="mb-6">
    <button @click="showGuide = !showGuide" 
            class="flex items-center gap-2 text-blue-600 hover:text-blue-700 transition">
        <x-heroicon-o-information-circle class="w-5 h-5" />
        <span x-text="showGuide ? 'بستن راهنما' : 'راهنمای استفاده از صفحه'"></span>
    </button>
    
    <div x-show="showGuide" x-collapse class="mt-4 p-5 bg-blue-50 rounded-lg border border-blue-200">
        <h3 class="text-base font-semibold text-blue-900 mb-3 flex items-center gap-2">
            <x-heroicon-o-information-circle class="w-5 h-5" />
            راهنمای مدیریت دوره‌های چک
        </h3>
        
        <div class="space-y-4 text-sm text-blue-900 leading-relaxed">
            <!-- هدف صفحه -->
            <div>
                <p class="font-medium mb-1">این صفحه برای چیست؟</p>
                <p>در این صفحه می‌توانید دوره‌های پرداخت چک را مدیریت کنید و برای هر دوره، درصد افزایش قیمت نسبت به قیمت نقدی تعیین نمایید.</p>
            </div>
            
            <!-- نحوه کار -->
            <div>
                <p class="font-medium mb-2">نحوه کار با صفحه:</p>
                <ul class="list-disc list-inside space-y-1 mr-4">
                    <li><strong>دوره‌های پیش‌فرض سیستم:</strong> به صورت پیش‌فرض غیرفعال هستند. برای فعال‌سازی، دکمه فعال/غیرفعال را کلیک کرده و درصد افزایش را وارد کنید.</li>
                    <li><strong>افزودن دوره جدید:</strong> تعداد روز، نام، و درصد افزایش را وارد کنید. اگر دوره‌ای با همان تعداد روز وجود داشته باشد، به لیست شما اضافه می‌شود.</li>
                    <li><strong>درصد افزایش:</strong> این درصد بر قیمت نقدی هر تیراژ اعمال می‌شود.</li>
                </ul>
            </div>
            
            <!-- فرمول محاسبه -->
            <div class="bg-white p-3 rounded border border-blue-300">
                <p class="font-medium mb-1">فرمول محاسبه:</p>
                <code class="text-xs block mb-2">قیمت چک = قیمت نقدی + (قیمت نقدی × درصد افزایش)</code>
                <p class="text-xs"><strong>مثال:</strong> اگر قیمت نقدی 100,000 تومان و درصد 5% باشد → قیمت چک = 105,000 تومان</p>
            </div>
            
            <!-- هشدار درصد صفر -->
            <div class="bg-yellow-50 border border-yellow-300 p-3 rounded flex items-start gap-2">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                <div class="text-xs">
                    <p class="font-medium">توجه:</p>
                    <p>درصد صفر (0%) مجاز است، اما در این حالت قیمت چک برابر قیمت نقدی خواهد بود.</p>
                </div>
            </div>
            
            <!-- نکته تیراژها -->
            <div class="bg-blue-100 border border-blue-300 p-3 rounded flex items-start gap-2">
                <x-heroicon-o-light-bulb class="w-5 h-5 text-blue-700 flex-shrink-0 mt-0.5" />
                <div class="text-xs">
                    <p class="font-medium">نکته مهم:</p>
                    <p>این درصد افزایش بر روی قیمت نقدی <strong>هر تیراژ</strong> اعمال می‌شود. یعنی اگر محصولی 5 تیراژ داشته باشد، برای هر تیراژ قیمت چک جداگانه محاسبه می‌شود.</p>
                </div>
            </div>
        </div>
    </div>
</div>
```

#### 5. چک‌لیست راهنمای کاربر
قبل از تکمیل هر صفحه:
- [ ] راهنمای کلی صفحه اضافه شده است
- [ ] هدف صفحه به زبان ساده توضیح داده شده
- [ ] نحوه استفاده از هر بخش شرح داده شده
- [ ] مثال‌های عملی آورده شده (اگر نیاز باشد)
- [ ] فرمول‌های محاسباتی (اگر وجود دارد) نمایش داده شده
- [ ] هشدارها و نکات مهم برجسته شده‌اند
- [ ] راهنما قابل باز/بسته شدن است (collapsible)
- [ ] طراحی راهنما با UI صفحه هماهنگ است

#### 6. راهنمای جزئی (Optional)
فقط در صورتی که صفحه بسیار پیچیده باشد:

```blade
<!-- نمونه tooltip برای فیلد خاص -->
<div class="relative group">
    <label>نام فیلد</label>
    <x-heroicon-o-question-mark-circle 
        class="w-4 h-4 text-gray-400 inline-block cursor-help" />
    
    <div class="hidden group-hover:block absolute z-10 w-64 p-2 text-xs 
                bg-gray-800 text-white rounded shadow-lg">
        توضیح مختصر درباره این فیلد
    </div>
</div>
```

### نمونه صفحات با راهنمای کامل
- ✅ `/supplier/settings/check-periods` - مدیریت دوره‌های چک
- [ ] سایر صفحات به تدریج اضافه می‌شوند

---

## نکات مهم

1. **عدم وابستگی خارجی:** همه سرویس‌ها باید در سرور داخلی باشند
2. **حفظ منطق:** هیچ کدی بدون دلیل حذف یا تغییر ندهد
3. **مشورت کارشناسی:** تغییرات بزرگ با تایید کارشناسان مربوطه
4. **بررسی کد واقعی:** همیشه کدهای واقعی پروژه را بررسی کن (مگر اینکه کاربر کد پیست کرده باشد)

---

**تاریخ ایجاد:** 2026-08-02  
**آخرین بروزرسانی:** 2026-09-26  
**وضعیت:** فعال
