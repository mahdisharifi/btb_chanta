# امنیت سیستم قیمت‌گذاری ماتریسی

## تاریخ: 2026-08-24

## خلاصه
این مستند تمامی اقدامات امنیتی انجام شده برای جلوگیری از حملات امنیتی در سیستم قیمت‌گذاری ماتریسی را توضیح می‌دهد.

---

## 1. SQL Injection Prevention

### رویکرد Laravel
Laravel به صورت پیش‌فرض از **Prepared Statements** و **Parameter Binding** در Eloquent ORM و Query Builder استفاده می‌کند که SQL Injection را خنثی می‌کند.

### مثال ایمن:
```php
CheckPeriod::forSupplier($supplierId)->findOrFail($id);
$this->productSupplier->prices()->create([...]);
```

### لایه‌های اضافی امنیت:
1. **Validation**: همه ورودی‌ها با `rules()` بررسی می‌شوند
2. **Type Casting**: تبدیل صریح به نوع داده مناسب
3. **Sanitization**: پاکسازی رشته‌های متنی

---

## 2. Validation Rules

### EditPrice Component

```php
'sku' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\-_]+$/']
'priceMatrix.*.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999']
'priceMatrix.*.*.warranty_type' => ['nullable', 'string', 'in:test,limited,lifetime']
'priceMatrix.*.*.warranty_duration' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_if:...']
'priceMatrix.*.*.check_periods.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999']
```

**چرا؟**
- `regex`: فقط کاراکترهای مجاز
- `min/max`: محدودیت مقدار
- `in`: فقط مقادیر از پیش تعریف شده
- `required_if`: شرطی بودن فیلدها

### ManageCheckPeriods Component

```php
'name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[\x{0600}-\x{06FF}\s\d]+$/u']
'days' => ['required', 'integer', 'min:1', 'max:365', 'unique:check_periods,days,...']
```

**چرا؟**
- `regex` فارسی: جلوگیری از کاراکترهای مخرب
- `unique`: جلوگیری از تکرار (با بررسی scope)

---

## 3. Authorization Checks

### بررسی مالکیت محصول
```php
// EditPrice::save()
$supplierId = Auth::user()->supplier->id;
if ($this->productSupplier->supplier_id !== $supplierId) {
    abort(403, 'شما مجاز به ویرایش این محصول نیستید.');
}
```

### بررسی مالکیت دوره چک
```php
// ManageCheckPeriods::edit(), delete(), toggleActive()
$period = CheckPeriod::forSupplier($supplierId)->findOrFail($id);
```

**چرا؟**
- تامین‌کننده فقط محصولات و دوره‌های خود را ویرایش کند
- از دستکاری ID های دیگران جلوگیری شود

### بررسی Scope دوره‌ها
```php
// EditPrice::save()
if ($period->scope === 'supplier' && $period->supplier_id !== $supplierId) {
    continue; // این دوره متعلق به تامین‌کننده دیگری است
}
```

**چرا؟**
- دوره‌های `global` برای همه
- دوره‌های `supplier` فقط برای مالک

---

## 4. Input Sanitization

### متد sanitizeInput
```php
private function sanitizeInput(?string $input): ?string
{
    if ($input === null) {
        return null;
    }
    
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}
```

**عملکرد:**
1. `trim()`: حذف فاصله‌های اضافی
2. `strip_tags()`: حذف تگ‌های HTML
3. `htmlspecialchars()`: escape کاراکترهای خاص
4. `ENT_QUOTES`: escape کردن ' و "

**کاربرد:**
```php
'sku' => $this->sanitizeInput($this->sku)
'name' => $sanitizedName = $this->sanitizeInput($this->name)
```

---

## 5. Type Casting

### تبدیل صریح نوع داده
```php
'price_type_id' => (int)$priceTypeId
'tier_index' => (int)$tierIndex
'check_period_id' => (int)$periodId
'price' => (float)$data['price']
'is_active' => (bool)$this->is_active
'warranty_duration' => $warrantyDuration ? (int)$warrantyDuration : null
```

**چرا؟**
- جلوگیری از ورود رشته به جای عدد
- اطمینان از نوع داده صحیح در دیتابیس
- جلوگیری از Type Juggling attacks

---

## 6. XSS Prevention

### در Blade Views
```blade
{{ $variable }} <!-- خودکار escape می‌شود -->
{!! $variable !!} <!-- escape نمی‌شود - خطرناک! -->
```

### در این پروژه
همه خروجی‌ها با `{{ }}` نمایش داده می‌شوند:
```blade
{{ $period->name }}
{{ $priceType->name }}
```

---

## 7. CSRF Protection

### Laravel CSRF Token
Livewire خودکار CSRF token را مدیریت می‌کند:
```blade
@csrf <!-- در فرم‌های معمولی -->
```

در Livewire نیازی به `@csrf` نیست چون خودکار است.

---

## 8. Mass Assignment Protection

### در Models
```php
// Product.php
protected $fillable = ['name', 'sku', ...];

// CheckPeriod.php
protected $fillable = ['name', 'days', 'scope', 'supplier_id', ...];
```

**چرا؟**
- فقط فیلدهای مشخص قابل تغییر
- از دستکاری فیلدهای حساس جلوگیری می‌شود

---

## 9. Transaction Safety

### استفاده از DB Transaction
```php
DB::transaction(function () use ($supplierId) {
    // حذف قیمت‌های قبلی
    $this->productSupplier->prices()->whereNotNull('price_type_id')->delete();
    
    // ذخیره قیمت‌های جدید
    foreach (...) {
        $this->productSupplier->prices()->create([...]);
    }
});
```

**چرا؟**
- اگر خطایی رخ دهد، همه تغییرات Rollback می‌شوند
- جلوگیری از نیمه‌کاره ماندن داده‌ها

---

## 10. Business Logic Protection

### جلوگیری از حذف دوره استفاده شده
```php
if ($period->productPrices()->exists()) {
    $this->dispatch('notify', [
        'message' => 'این دوره در قیمت‌گذاری محصولات استفاده شده و نمی‌تواند حذف شود.',
        'type' => 'error',
    ]);
    return;
}
```

**چرا؟**
- حفظ یکپارچگی داده‌ها
- جلوگیری از Foreign Key Constraint errors

---

## 11. Rate Limiting (آینده)

### پیشنهاد برای آینده
```php
// در routes/supplier.php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/products/{id}/edit-price', EditPrice::class);
});
```

**چرا؟**
- جلوگیری از Brute Force
- جلوگیری از DoS attacks

---

## 12. Logging & Monitoring (آینده)

### پیشنهاد برای آینده
```php
// در EditPrice::save()
Log::info('Price matrix updated', [
    'supplier_id' => $supplierId,
    'product_id' => $this->product->id,
    'user_id' => Auth::id(),
]);
```

**چرا؟**
- ردیابی تغییرات
- تشخیص رفتارهای مشکوک

---

## خلاصه اقدامات

✅ **Validation**: قوانین سخت‌گیرانه برای همه ورودی‌ها  
✅ **Authorization**: بررسی مالکیت در همه عملیات  
✅ **Sanitization**: پاکسازی رشته‌های متنی  
✅ **Type Casting**: تبدیل صریح نوع داده‌ها  
✅ **SQL Injection**: محافظت خودکار Laravel + validation  
✅ **XSS**: escape خودکار Blade  
✅ **CSRF**: محافظت خودکار Livewire  
✅ **Mass Assignment**: محدودیت fillable  
✅ **Transaction**: امنیت داده‌ها با DB::transaction  
✅ **Business Logic**: بررسی روابط قبل از حذف  

---

## نتیجه‌گیری

با ترکیب:
1. **Validation** (ورودی صحیح)
2. **Authorization** (دسترسی صحیح)
3. **Sanitization** (پاکسازی داده)
4. **Type Casting** (نوع صحیح)
5. **Laravel Security Features** (محافظت‌های داخلی)

سیستم قیمت‌گذاری در برابر حملات رایج امنیتی محافظت شده است.

**توجه:** برای آینده می‌توان Rate Limiting و Logging را اضافه کرد.
