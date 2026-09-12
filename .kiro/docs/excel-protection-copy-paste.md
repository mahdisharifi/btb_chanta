# مستندات: مشکل Copy-Paste در Excel محافظت‌شده

## 📋 خلاصه مشکل

**مشکل اصلی:**
وقتی یک Sheet در Excel با `setSheet(true)` و password محافظت می‌شود، حتی سلول‌هایی که `PROTECTION_UNPROTECTED` هستند نیز محدودیت‌های زیر را دارند:

- ❌ Copy/Paste غیرفعال است
- ❌ نمی‌توان فرمول‌ها را کپی کرد
- ❌ نمی‌توان قیمت‌ها را به صورت دسته‌جمعی ویرایش کرد

**کد قبلی:**
```php
$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setPassword('B2BMarket2024');

// سلول‌های قیمت باز بودند اما Copy-Paste کار نمی‌کرد
$sheet->getStyle($colLetter . $r)
    ->getProtection()
    ->setLocked(Protection::PROTECTION_UNPROTECTED);
```

---

## ✅ راه‌حل پیاده‌سازی شده (Solution 1)

### فعال‌سازی مجوزهای Sheet Protection

با تنظیم مجوزهای خاص در PhpSpreadsheet، Copy-Paste امکان‌پذیر می‌شود:

```php
// محافظت Sheet
$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setPassword('B2BMarket2024');

// ✅ فعال‌سازی مجوزهای لازم برای Copy-Paste
$sheet->getProtection()->setSelectLockedCells(true);      // انتخاب سلول‌های قفل
$sheet->getProtection()->setSelectUnlockedCells(true);    // انتخاب سلول‌های باز
$sheet->getProtection()->setFormatCells(false);           // جلوگیری از تغییر فرمت
$sheet->getProtection()->setFormatColumns(false);         // جلوگیری از تغییر ستون
$sheet->getProtection()->setFormatRows(false);            // جلوگیری از تغییر سطر
$sheet->getProtection()->setInsertColumns(false);         // جلوگیری از اضافه کردن ستون
$sheet->getProtection()->setInsertRows(false);            // جلوگیری از اضافه کردن سطر
$sheet->getProtection()->setInsertHyperlinks(false);      // جلوگیری از لینک
$sheet->getProtection()->setDeleteColumns(false);         // جلوگیری از حذف ستون
$sheet->getProtection()->setDeleteRows(false);            // جلوگیری از حذف سطر
$sheet->getProtection()->setSort(false);                  // جلوگیری از Sort
$sheet->getProtection()->setAutoFilter(false);            // جلوگیری از Filter
$sheet->getProtection()->setPivotTables(false);           // جلوگیری از Pivot
$sheet->getProtection()->setObjects(true);                // مجوز کار با objects
$sheet->getProtection()->setScenarios(true);              // مجوز کار با scenarios
```

### مزایا
- ✅ Copy-Paste فعال است
- ✅ سلول‌های metadata قفل هستند
- ✅ فرمول‌های خودکار محافظت می‌شوند
- ✅ امنیت بالا با password

### معایب
- ⚠️ ممکن است در برخی نسخه‌های Excel مشکل داشته باشد
- ⚠️ نیاز به تست دقیق در محیط واقعی

---

## 🔄 راه‌حل جایگزین 2: حذف Protection + Validation

اگر راه‌حل 1 کار نکرد، می‌توانید از این استراتژی استفاده کنید:

### کد پیشنهادی

```php
// ❌ حذف محافظت Sheet
// $sheet->getProtection()->setSheet(true);
// $sheet->getProtection()->setPassword('B2BMarket2024');

// ✅ استفاده از رنگ‌بندی برای مشخص کردن سلول‌های غیرقابل ویرایش

// سطر metadata (خاکستری روشن)
for ($i = 1; $i <= $highestColumnIndex; $i++) {
    $colLetter = Coordinate::stringFromColumnIndex($i);
    $sheet->getStyle($colLetter . '2')->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F3F4F6'], // gray-100
        ],
        'font' => ['color' => ['rgb' => '9CA3AF'], 'italic' => true],
    ]);
    
    // اضافه کردن کامنت هشدار
    $sheet->getComment($colLetter . '2')
        ->getText()
        ->createTextRun('⚠️ این سطر را تغییر ندهید. سیستم به این اطلاعات برای تشخیص ستون‌ها نیاز دارد.');
}

// ستون شناسه کالا (خاکستری خیلی کم‌رنگ)
$lastColIndex = $highestColumnIndex;
$lastColLetter = Coordinate::stringFromColumnIndex($lastColIndex);
for ($r = 3; $r <= $rowIndex - 1; $r++) {
    $sheet->getStyle($lastColLetter . $r)->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F9FAFB'], // gray-50
        ],
        'font' => ['size' => 8, 'color' => ['rgb' => '9CA3AF']],
    ]);
}

// ستون تیراژ (فقط نمایش - خاکستری)
for ($r = 3; $r <= $rowIndex - 1; $r++) {
    $sheet->getStyle('C' . $r)->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F3F4F6'], // gray-100
        ],
        'font' => ['color' => ['rgb' => '6B7280']],
    ]);
}

// ستون‌های قابل ویرایش (سفید پس‌زمینه با border سبز)
for ($i = 4; $i < $lastColIndex; $i++) {
    $colLetter = Coordinate::stringFromColumnIndex($i);
    for ($r = 3; $r <= $rowIndex - 1; $r++) {
        // سلول‌های خودکار قبلاً رنگ آبی دارند
        $currentColor = $sheet->getStyle($colLetter . $r)->getFill()->getStartColor()->getRGB();
        
        if ($currentColor !== 'E0E7FF') { // اگر خودکار نیست
            $sheet->getStyle($colLetter . $r)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFFFFF'], // سفید
                ],
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '10B981'], // green-500
                    ],
                ],
            ]);
        }
    }
}
```

### Validation در Import

در متد `processFile()` باید بررسی کنید که metadata تغییر نکرده باشد:

```php
// بعد از خواندن فایل
$metadata = array_shift($rows); // سطر دوم: متادیتا

// ✅ Validation: بررسی اینکه metadata دستکاری نشده
foreach ($priceColumns as $colIndex => $priceInfo) {
    $meta = $metadata[$colIndex] ?? null;
    
    if (!$meta || !preg_match('/T(\d+)\|PT(\d+)\|CP(\d+)/', $meta, $matches)) {
        throw new \Exception("فایل دستکاری شده است. لطفاً از فایل اصلی سیستم استفاده کنید.");
    }
    
    // بررسی تطابق
    if ((int)$matches[1] !== $priceInfo['tier_index'] ||
        (int)$matches[2] !== $priceInfo['price_type_id'] ||
        ((int)$matches[3] === 0 ? null : (int)$matches[3]) !== $priceInfo['check_period_id']) {
        throw new \Exception("ساختار فایل تغییر کرده است. لطفاً فایل جدیدی از سیستم دانلود کنید.");
    }
}

// ✅ Validation: بررسی شناسه کالا
if (!isset($headerMap['product_id'])) {
    throw new \Exception("ستون 'شناسه کالا' حذف شده است. لطفاً از فایل اصلی استفاده کنید.");
}
```

### مزایا
- ✅ Copy-Paste کاملاً آزاد است
- ✅ رنگ‌بندی واضح برای کاربر
- ✅ Validation قوی در Import
- ✅ سازگاری با همه نسخه‌های Excel

### معایب
- ❌ کاربر فنی می‌تواند metadata را تغییر دهد
- ❌ امنیت کمتر نسبت به Protection

---

## 🔄 راه‌حل جایگزین 3: Hybrid (ترکیبی)

ترکیب هر دو روش برای بهترین نتیجه:

```php
// Protection فعال باشد اما با تنظیمات انعطاف‌پذیر
$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setPassword('B2BMarket2024');
$sheet->getProtection()->setSelectLockedCells(true);
$sheet->getProtection()->setSelectUnlockedCells(true);

// + رنگ‌بندی واضح برای سلول‌ها
// + Validation قوی در Import
// + راهنمای کامل برای کاربر
```

---

## 🧪 تست‌های مورد نیاز

### 1. تست Copy-Paste
- [ ] کپی کردن یک سلول قیمت به سلول دیگر
- [ ] کپی کردن یک ستون کامل قیمت
- [ ] کپی کردن از Excel به Notepad و بازگشت
- [ ] Paste Special (فقط مقادیر)
- [ ] Paste با فرمول (نباید کار کند برای سلول‌های قفل)

### 2. تست Protection
- [ ] تلاش برای ویرایش metadata (نباید امکان‌پذیر باشد)
- [ ] تلاش برای ویرایش شناسه کالا (نباید امکان‌پذیر باشد)
- [ ] تلاش برای ویرایش ستون تیراژ (نباید امکان‌پذیر باشد)
- [ ] تلاش برای ویرایش فرمول‌های خودکار (نباید امکان‌پذیر باشد)
- [ ] ویرایش قیمت‌های دستی (باید امکان‌پذیر باشد)

### 3. تست Import
- [ ] آپلود فایل با قیمت‌های کپی‌پیست شده
- [ ] آپلود فایل با metadata دستکاری شده (باید خطا بدهد)
- [ ] آپلود فایل با شناسه تغییر یافته (باید خطا بدهد)

### 4. تست در نسخه‌های مختلف Excel
- [ ] Microsoft Excel 2016+
- [ ] Microsoft Excel Online
- [ ] LibreOffice Calc
- [ ] Google Sheets (Import شده)

---

## 📝 یادداشت‌های مهم

### محدودیت‌های PhpSpreadsheet
PhpSpreadsheet **مستقیماً** property یا متدی برای فعال/غیرفعال کردن Copy-Paste ندارد. این عملیات از طریق مجوزهای کلی‌تر (`selectLockedCells`, `selectUnlockedCells`) کنترل می‌شود.

### سازگاری با Excel
تنظیمات Protection ممکن است در نسخه‌های مختلف Excel متفاوت رفتار کنند:
- **Excel Desktop (2016+):** پشتیبانی کامل
- **Excel Online:** محدودیت‌های بیشتر
- **LibreOffice/OpenOffice:** ممکن است برخی مجوزها نادیده گرفته شوند
- **Google Sheets:** فرمت XLSX را import می‌کند اما Protection ممکن است از بین برود

### توصیه نهایی

**برای پروژه B2B Marketplace:**
- از راه‌حل 1 (Protection با مجوزها) استفاده کنید
- اگر در تست واقعی مشکل داشت، به راه‌حل 2 (رنگ‌بندی + Validation) بروید
- در هر دو حالت، Validation قوی در Import ضروری است

---

## 📚 منابع بیشتر

- [PhpSpreadsheet Documentation - Protection](https://phpspreadsheet.readthedocs.io/en/latest/topics/worksheets/protection/)
- [Microsoft Excel - Protect Worksheet](https://support.microsoft.com/en-us/office/protect-a-worksheet-3179efdb-1285-4d49-a9c3-f4ca36276de6)

---

**تاریخ ایجاد:** 2026-08-27  
**نویسنده:** Kiro AI - Deployment & Hosting Expert  
**پروژه:** B2B Marketplace - Fast Pricing Module
