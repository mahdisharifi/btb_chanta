# طراحی آینده: یکپارچه‌سازی قیمت‌گذاری سریع با ماتریس قیمت

## 1. ساختار فایل Excel قیمت‌گذاری سریع

### فرمت پیشنهادی:

```
| کد محصول | نام محصول | تیراژ | نقدی | چک 30 | چک 60 | چک 90 | چک 120 | گارانتی | نوع گارانتی | مدت گارانتی |
|----------|-----------|-------|------|-------|-------|-------|--------|---------|-------------|-------------|
| PRD-001  | محصول A   | 1-100 | 1000 | 1050  | 1100  | 1150  | 1200   | 500     | limited     | 365         |
| PRD-001  | محصول A   | 101-500| 950 | 1000  | 1050  | 1100  | 1150   | 500     | limited     | 365         |
| PRD-002  | محصول B   | 1-50  | 2000 | 2100  | 2200  | 2300  | 2400   | 1000    | lifetime    |             |
```

### ستون‌های دینامیک چک:
- سیستم باید ستون‌های چک را به صورت پویا از جدول `check_periods` بسازد
- فرمت: `چک {روز}` یا `Check {days}`

---

## 2. فرآیند Import

### A. تشخیص ستون‌ها:
```php
// الگوریتم تشخیص ستون‌های چک
$checkColumns = [];
foreach ($headers as $index => $header) {
    if (preg_match('/چک\s*(\d+)|check\s*(\d+)/ui', $header, $matches)) {
        $days = $matches[1] ?? $matches[2];
        $period = CheckPeriod::where('days', $days)
            ->where(function($q) use ($supplierId) {
                $q->global()->orWhere('supplier_id', $supplierId);
            })
            ->first();
        
        if ($period) {
            $checkColumns[$index] = $period->id;
        }
    }
}
```

### B. نگاشت داده‌ها:
```php
// تبدیل هر سطر Excel به ساختار ماتریس
foreach ($rows as $row) {
    $product = Product::where('sku', $row['کد محصول'])->first();
    
    // تشخیص تیراژ
    $tierIndex = $this->detectTierIndex($product, $row['تیراژ']);
    
    // ذخیره قیمت نقدی
    if (!empty($row['نقدی'])) {
        $this->savePriceMatrix($tierIndex, 'cash', null, $row['نقدی']);
    }
    
    // ذخیره قیمت‌های چکی
    foreach ($checkColumns as $colIndex => $periodId) {
        if (!empty($row[$colIndex])) {
            $this->savePriceMatrix($tierIndex, 'check', $periodId, $row[$colIndex]);
        }
    }
    
    // ذخیره گارانتی
    if (!empty($row['گارانتی'])) {
        $this->savePriceMatrix($tierIndex, 'warranty', null, $row['گارانتی'], [
            'warranty_type' => $row['نوع گارانتی'],
            'warranty_duration' => $row['مدت گارانتی'],
        ]);
    }
}
```

### C. تشخیص تیراژ:
```php
private function detectTierIndex($product, $tierStr)
{
    // فرمت: "1-100" یا "101-500" یا "500+"
    preg_match('/(\d+)-?(\d+)?|\d+\+/', $tierStr, $matches);
    
    $min = (int)($matches[1] ?? 0);
    $max = !empty($matches[2]) ? (int)$matches[2] : null;
    
    // پیدا کردن تیراژ مطابق
    foreach ($product->quantity_tiers as $index => $tier) {
        if ($tier['min_quantity'] == $min) {
            if ($max === null || $tier['max_quantity'] == $max) {
                return $index;
            }
        }
    }
    
    return 0; // تیراژ اول به عنوان پیش‌فرض
}
```

---

## 3. Export ماتریس قیمت به Excel

### ساختار:
```php
public function exportPriceMatrix(Product $product, Supplier $supplier)
{
    $priceTypes = PriceType::active()->ordered()->get();
    $checkPeriods = CheckPeriod::forSupplierOrGlobal($supplier->id)
        ->active()
        ->orderBy('days')
        ->get();
    
    $rows = [];
    
    foreach ($product->quantity_tiers as $index => $tier) {
        $row = [
            'کد محصول' => $product->sku,
            'نام محصول' => $product->title,
            'تیراژ' => $tier['min_quantity'] . '-' . ($tier['max_quantity'] ?? '+'),
        ];
        
        // قیمت نقدی
        $cashPrice = $this->getPrice($supplier->id, $product->id, $index, 'cash', null);
        $row['نقدی'] = $cashPrice ? $cashPrice->price : '';
        
        // قیمت‌های چکی
        foreach ($checkPeriods as $period) {
            $checkPrice = $this->getPrice($supplier->id, $product->id, $index, 'check', $period->id);
            $row["چک {$period->days}"] = $checkPrice ? $checkPrice->price : '';
        }
        
        // گارانتی
        $warrantyPrice = $this->getPrice($supplier->id, $product->id, $index, 'warranty', null);
        $row['گارانتی'] = $warrantyPrice ? $warrantyPrice->price : '';
        $row['نوع گارانتی'] = $warrantyPrice ? $warrantyPrice->warranty_type : '';
        $row['مدت گارانتی'] = $warrantyPrice ? $warrantyPrice->warranty_duration : '';
        
        $rows[] = $row;
    }
    
    return Excel::create($rows);
}
```

---

## 4. فیلتر و جستجو

### A. فیلتر بر اساس محدوده قیمت:
```php
// پیدا کردن محصولات در محدوده قیمت (تیراژ خاص)
ProductPrice::whereHas('productSupplier', function($q) use ($supplierId) {
    $q->where('supplier_id', $supplierId);
})
->where('tier_index', 0) // تیراژ اول
->where('price_type_id', 1) // نقدی
->whereBetween('price', [$minPrice, $maxPrice])
->with(['product', 'productSupplier.supplier'])
->get();
```

### B. مقایسه قیمت‌ها:
```php
// مقایسه قیمت یک محصول بین تامین‌کننده‌ها
$productId = 940;
$tierIndex = 0;

$comparison = ProductPrice::where('product_id', $productId)
    ->where('tier_index', $tierIndex)
    ->where('price_type_id', 1) // نقدی
    ->whereNotNull('price_type_id')
    ->with('productSupplier.supplier:id,company_name')
    ->get()
    ->groupBy('productSupplier.supplier.company_name')
    ->map(function($prices) {
        return [
            'supplier' => $prices->first()->productSupplier->supplier->company_name,
            'cash_price' => $prices->where('price_type_id', 1)->first()->price ?? null,
            'check_30' => $prices->where('check_period_id', 1)->first()->price ?? null,
            'check_60' => $prices->where('check_period_id', 2)->first()->price ?? null,
        ];
    });
```

### C. جستجوی پیشرفته:
```sql
-- پیدا کردن ارزان‌ترین تامین‌کننده برای تیراژ خاص
SELECT 
    s.company_name,
    pp.price,
    pp.tier_index,
    qt.name as tier_name
FROM product_prices pp
JOIN product_supplier ps ON pp.product_supplier_id = ps.id
JOIN suppliers s ON ps.supplier_id = s.id
JOIN products p ON pp.product_id = p.id,
JSON_TABLE(
    p.quantity_tiers,
    '$[*]' COLUMNS(
        idx FOR ORDINALITY,
        name VARCHAR(100) PATH '$.name'
    )
) AS qt
WHERE pp.product_id = 940
  AND pp.tier_index = qt.idx - 1
  AND pp.price_type_id = 1
  AND pp.is_active = 1
ORDER BY pp.price ASC
LIMIT 5;
```

---

## 5. API Endpoints پیشنهادی

```php
// دریافت ماتریس قیمت یک محصول
GET /api/v1/products/{product}/price-matrix?supplier_id={id}

// دریافت قیمت برای تیراژ خاص
GET /api/v1/products/{product}/prices?tier_index=0&price_type=cash

// مقایسه قیمت بین تامین‌کننده‌ها
GET /api/v1/products/{product}/price-comparison?tier_index=0

// دریافت لیست تیراژها
GET /api/v1/products/{product}/tiers

// دریافت دوره‌های چک فعال برای تامین‌کننده
GET /api/v1/suppliers/{supplier}/check-periods
```

---

## 6. Performance Optimization

### A. Caching:
```php
// Cache دوره‌های پیش‌فرض
$globalPeriods = Cache::remember('check_periods:global', 3600, function() {
    return CheckPeriod::global()->active()->orderBy('days')->get();
});

// Cache قیمت‌های محصول
$priceMatrix = Cache::remember("price_matrix:{$productId}:{$supplierId}", 600, function() {
    return $this->loadPriceMatrix($productId, $supplierId);
});
```

### B. Eager Loading:
```php
// بارگذاری بهینه برای لیست محصولات
$products = Product::with([
    'productSuppliers.prices' => function($q) use ($supplierId) {
        $q->where('product_supplier_id', function($subQ) use ($supplierId) {
            $subQ->select('id')
                ->from('product_supplier')
                ->where('supplier_id', $supplierId);
        });
    },
    'productSuppliers.supplier:id,company_name'
])->get();
```

### C. Indexing:
```sql
-- Indexes موجود
INDEX price_lookup_index (product_supplier_id, price_type_id, tier_index)
INDEX active_valid_index (is_active, valid_from, valid_until)
UNIQUE KEY unique_price_matrix (product_supplier_id, price_type_id, tier_index, check_period_id)

-- Indexes پیشنهادی اضافی
CREATE INDEX idx_product_tier_type ON product_prices(product_id, tier_index, price_type_id);
CREATE INDEX idx_price_range ON product_prices(price) WHERE is_active = 1;
```

---

## 7. Validation Rules

```php
// Validation برای Import Excel
[
    'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
    'rows.*.product_sku' => 'required|exists:products,sku',
    'rows.*.tier' => 'required|regex:/^\d+-?\d*\+?$/',
    'rows.*.cash_price' => 'nullable|numeric|min:0',
    'rows.*.warranty_type' => 'nullable|in:test,limited,lifetime',
    'rows.*.warranty_duration' => 'required_if:rows.*.warranty_type,limited|nullable|integer|min:1',
]
```

---

## 8. پیاده‌سازی مرحله‌ای

### Phase 1: ✅ انجام شده
- ✅ ماتریس قیمت UI
- ✅ مدیریت دوره‌های چک
- ✅ ذخیره قیمت‌ها

### Phase 2: در انتظار
- [ ] Import از Excel
- [ ] Export به Excel
- [ ] Template Excel دانلود

### Phase 3: در انتظار
- [ ] API Endpoints
- [ ] صفحه مقایسه قیمت
- [ ] فیلترهای پیشرفته

### Phase 4: در انتظار
- [ ] Dashboard تحلیلی
- [ ] نمودارهای قیمت
- [ ] Alert قیمت رقبا

---

**تاریخ ایجاد:** 2026-08-22  
**وضعیت:** نسخه اولیه - نیاز به بررسی کارشناسان
