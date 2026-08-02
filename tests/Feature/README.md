# تست‌های Feature برای B2B Marketplace

این مجموعه شامل تست‌های جامع برای تمامی route‌های سیستم است.

## ساختار تست‌ها

### 1. GuestPagesTest.php (15 تست)
تست‌های صفحات عمومی که بدون نیاز به احراز هویت قابل دسترسی هستند:
- صفحه اصلی (Home)
- لیست و جزئیات محصولات
- لیست و جزئیات دسته‌بندی‌ها
- جستجو
- صفحات استاتیک (درباره ما، تماس با ما، قوانین، حریم خصوصی)
- Sitemaps (اصلی، محصولات، دسته‌بندی‌ها، صفحات استاتیک)

### 2. AdminPagesTest.php (27 تست)
تست‌های پنل مدیریت با نقش super_admin:
- داشبورد ادمین
- مدیریت کاربران (لیست، ایجاد، ویرایش، کاربران در انتظار)
- مدیریت محصولات (لیست، ایجاد، ویرایش، نمایش)
- مدیریت دسته‌بندی‌ها (لیست، ایجاد، ویرایش)
- مدیریت تامین‌کنندگان (لیست، ایجاد، ویرایش)
- مدیریت سئو
- مدیریت Attributes (لیست، ایجاد، ویرایش)
- مدیریت پلن‌های اشتراک
- مدیریت Flowcharts (لیست، ایجاد، ویرایش)
- ترمینال سیستم
- مدیریت جغرافیا
- مدیریت کاتالوگ‌ها (لیست، ایجاد، ویرایش، پیش‌نمایش)

### 3. SupplierPagesTest.php (18 تست)
تست‌های پنل تامین‌کننده برای نقش‌های supplier و regular_supplier:
- داشبورد تامین‌کننده
- مدیریت محصولات (لیست، ایجاد، ویرایش، ویرایش قیمت)
- انتخاب محصولات
- قیمت‌گذاری و قیمت‌گذاری سریع
- ایمپورت و بررسی قیمت‌گذاری سریع
- مدیریت کاتالوگ (لیست، نمایش، پیش‌نمایش)
- محصولات من
- گزارش‌ها
- خرید Feature

### 4. RetailerPagesTest.php (12 تست)
تست‌های پنل خرده‌فروش برای نقش‌های retailer_premium و retailer_basic:
- داشبورد خرده‌فروش
- مدیریت اشتراک‌ها
- خرید Feature
- دسترسی به اطلاعات تامین‌کننده (فقط premium)
- چک کردن انقضای اشتراک

### 5. UserProfilePagesTest.php (12 تست)
تست‌های صفحات پروفایل و مدیریت حساب کاربری:
- انتخاب نقش برای کاربر end_user
- تکمیل اطلاعات تامین‌کننده
- تکمیل اطلاعات خرده‌فروش
- ویرایش پروفایل تامین‌کننده
- ویرایش پروفایل خرده‌فروش
- صفحات وضعیت حساب (pending، under_review، rejected)
- ویرایش اکانت
- تایید موبایل

### 6. PaymentPagesTest.php (3 تست)
تست‌های مربوط به درگاه پرداخت:
- صفحه verify پرداخت (GET & POST)
- صفحه callback پرداخت

## اجرای تست‌ها

### اجرای تمامی تست‌ها
```bash
php artisan test
# یا
composer test
# یا
./vendor/bin/phpunit
```

### اجرای یک دسته خاص
```bash
php artisan test --filter=GuestPagesTest
php artisan test --filter=AdminPagesTest
php artisan test --filter=SupplierPagesTest
php artisan test --filter=RetailerPagesTest
php artisan test --filter=UserProfilePagesTest
php artisan test --filter=PaymentPagesTest
```

### اجرای یک تست خاص
```bash
php artisan test --filter=test_home_page_is_accessible
```

### نمایش خروجی با جزئیات
```bash
php artisan test --testdox
```

## نیازمندی‌ها

برای اجرای موفقیت‌آمیز تست‌ها نیاز است:

1. **Factory‌های Model**:
   - UserFactory
   - ProductFactory
   - CategoryFactory
   - AttributeFactory
   - CatalogTemplateFactory / CatalogFactory
   - FlowchartFactory
   - FeatureFactory

2. **Database Configuration**:
   - تنظیم database برای testing در `phpunit.xml` یا `.env.testing`
   - اجرای migrations قبل از تست

3. **Seeders** (اختیاری):
   - RoleSeeder برای ایجاد نقش‌های پایه

## نکات مهم

- تمامی تست‌ها از `RefreshDatabase` استفاده می‌کنند که بعد از هر تست، database را reset می‌کند
- تست‌ها شامل بررسی دسترسی‌های مختلف (guest، admin، supplier، retailer) هستند
- برخی تست‌ها وابسته به middleware‌های خاص مانند `check.approval` و `check.subscription` هستند

## جمع کل

**تعداد کل تست‌ها**: 87 تست
- Guest: 15 تست
- Admin: 27 تست  
- Supplier: 18 تست
- Retailer: 12 تست
- User Profile: 12 تست
- Payment: 3 تست

## Todo

- [ ] اطمینان از وجود تمامی Factory‌ها
- [ ] تست کردن با داده‌های واقعی
- [ ] افزودن تست‌های بیشتر برای API endpoints (در صورت وجود)
- [ ] افزودن integration tests برای فرآیندهای کامل کاربری
