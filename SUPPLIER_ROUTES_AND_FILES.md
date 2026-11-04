# مسیرها و فایل‌های مرتبط با تأمین‌کننده

این یادداشت مسیرهای ثبت‌نام و تکمیل پروفایل تأمین‌کننده، صفحهٔ افزودن محصول، مسیرهای پنل، APIهای مرتبط و فایل‌های کد پیدا‌شده را ثبت می‌کند.

## نکتهٔ اصلی: افزودن محصول به‌صورت پیش‌نویس

| مورد | مقدار |
|---|---|
| متد و مسیر | `GET /supplier/products/create` |
| نام route | `supplier.products.create` |
| کنترل دسترسی | `web`, `auth`, نقش `supplier` یا `regular_supplier` یا `admin` یا `super_admin`، و `check.approval` |
| کامپوننت Livewire | `App\Livewire\Supplier\Products\Create` |
| فایل کامپوننت | [`app/Livewire/Supplier/Products/Create.php`](app/Livewire/Supplier/Products/Create.php) |
| فایل نمای فرم | [`resources/views/livewire/supplier/products/create.blade.php`](resources/views/livewire/supplier/products/create.blade.php) |

با ارسال فرم، محصول با وضعیت `draft` ساخته می‌شود. متن فرم هم ذخیره به‌عنوان پیش‌نویس را نشان می‌دهد. در دو نمای صفحهٔ خانه که بررسی شدند، ارجاع مستقیمی به `supplier.products.create` پیدا نشد: [`resources/views/pages/home.blade.php`](resources/views/pages/home.blade.php) و [`resources/views/pages/home/index.blade.php`](resources/views/pages/home/index.blade.php).

## ثبت‌نام و تکمیل اطلاعات

`/register` مسیر عمومی ثبت‌نام است؛ در این مسیر، route جداگانه‌ای به شکل `/supplier/register` تعریف نشده است.

| متد | مسیر | نام route | کاربرد |
|---|---|---|---|
| `GET` | `/register` | `register` | نمایش فرم ثبت‌نام عمومی |
| `POST` | `/register` | `register.store` | ثبت‌نام عمومی کاربر |
| `GET` | `/register/role-selection` | `user.role-selection` | انتخاب نقش پس از ثبت‌نام |
| `GET` | `/register/supplier/complete` | `user.register.supplier.complete` | تکمیل اطلاعات تأمین‌کننده |
| `GET` | `/profile/edit` | `supplier.profile.edit` | ویرایش پروفایل تأمین‌کننده |

Routeهای تکمیل پروفایل و انتخاب نقش در گروه `auth` و `verified` تعریف شده‌اند. فایل تعریف: [`routes/auth.php`](routes/auth.php). کامپوننت تکمیل پروفایل: [`app/Livewire/User/RegisterSupplier.php`](app/Livewire/User/RegisterSupplier.php).

## مسیرهای پنل تأمین‌کننده

تمام مسیرهای این بخش `GET` هستند و از پیشوند `/supplier` و نام route با پیشوند `supplier.` استفاده می‌کنند. گروه route نیازمند ورود، نقش مجاز تأمین‌کننده/ادمین و تأیید حساب است.

| مسیر | نام route |
|---|---|
| `/supplier/dashboard` | `supplier.dashboard` |
| `/supplier/products` | `supplier.products.index` |
| `/supplier/products/create` | `supplier.products.create` |
| `/supplier/products/{product:id}/edit` | `supplier.products.edit` |
| `/supplier/products/{product:id}/edit-price` | `supplier.products.edit-price` |
| `/supplier/settings/check-periods` | `supplier.settings.check-periods` |
| `/supplier/settings/price-rounding` | `supplier.settings.price-rounding` |
| `/supplier/settings/tier-discounts` | `supplier.settings.tier-discounts` |
| `/supplier/settings/category-tier-discounts` | `supplier.settings.category-tier-discounts` |
| `/supplier/product-selection` | `supplier.product-selection` |
| `/supplier/pricing` | `supplier.pricing` |
| `/supplier/my-products` | `supplier.my-products` |
| `/supplier/reports` | `supplier.reports` |
| `/supplier/fast-pricing` | `supplier.fast-pricing` |
| `/supplier/fast-pricing/import` | `supplier.fast-pricing.import` |
| `/supplier/fast-pricing/percentage-update` | `supplier.fast-pricing.percentage-update` |
| `/supplier/fast-pricing/review/{batchId}` | `supplier.fast-pricing.review` |
| `/supplier/catalog/index` | `supplier.catalog.index` |
| `/supplier/catalogs` | `supplier.catalog.show` |
| `/supplier/catalog/preview/{catalog}` | `supplier.catalog.preview` |
| `/supplier/catalog/{catalog}/custom-cover` | `supplier.catalog.custom-cover` |
| `/supplier/features/{featureId}/buy` | `supplier.features.buy` |
| `/supplier/feature/{feature}/buy` | `supplier.feature.buy` |

تعریف‌ها: [`routes/supplier.php`](routes/supplier.php). این فایل به برنامه اضافه شده است: [`bootstrap/app.php`](bootstrap/app.php).

## مسیرهای عمومی مرتبط با تأمین‌کننده

این‌ها صفحات عمومی فهرست یا نمایش تأمین‌کنندگان هستند و با پنل تأمین‌کننده فرق دارند:

| متد | مسیر | نام route |
|---|---|---|
| `GET` | `/products/filters/suppliers` | `products.filtered-suppliers` |
| `GET` | `/suppliers` | `suppliers.index` |
| `GET` | `/suppliers/profile/{supplier}` | `suppliers.show.legacy` |
| `GET` | `/suppliers/{slug}` | `suppliers.show` |

همچنین صفحهٔ خانه با `GET /` و نام `home` تعریف شده است. این routeها در [`routes/web.php`](routes/web.php) هستند. فایل‌های handler پیدا‌شده: [`app/Http/Controllers/Web/SupplierPublicController.php`](app/Http/Controllers/Web/SupplierPublicController.php) و [`app/Livewire/Web/SupplierProducts.php`](app/Livewire/Web/SupplierProducts.php).

## API تأمین‌کننده

پیشوند API برابر `/api/v1` است. مسیرهای محصولات به `auth:sanctum` و نقش `supplier` نیاز دارند.

| متد | مسیر | نام route |
|---|---|---|
| `POST` | `/api/v1/auth/register` | `api.v1.auth.register` |
| `GET` | `/api/v1/my-products` | `api.v1.my-products.index` |
| `POST` | `/api/v1/my-products` | `api.v1.my-products.store` |
| `PUT` | `/api/v1/my-products/{product}` | `api.v1.my-products.update` |

تعریف‌ها: [`routes/api.php`](routes/api.php). Handler محصولات: [`app/Http/Controllers/Api/V1/ProductApiController.php`](app/Http/Controllers/Api/V1/ProductApiController.php). ثبت‌نام API عمومی است و route مخصوص تأمین‌کننده محسوب نمی‌شود.

## مسیر مجزای تکمیل محصول برون‌سپاری

| متد | مسیر | نام route | دسترسی |
|---|---|---|---|
| `GET` | `/supplier/product-completion-requests` | `supplier.product-completion-requests` | ورود، تأیید ایمیل و مجوز `outsourcing.view_supplier_tasks` |

این صفحه مربوط به درخواست‌های تکمیل محصول برون‌سپاری است و همان فرم افزودن محصول در `/supplier/products/create` نیست. تعریف: [`routes/outsourcing.php`](routes/outsourcing.php). کامپوننت: [`app/Livewire/Supplier/ProductCompletionRequests.php`](app/Livewire/Supplier/ProductCompletionRequests.php).

## فایل‌های مرتبط دیگر

- [`app/Enums/ProductStatus.php`](app/Enums/ProductStatus.php) — enum وضعیت محصول؛ فرم ایجاد محصول از `ProductStatus::DRAFT` استفاده می‌کند.
- [`routes/auth.php`](routes/auth.php) — ثبت‌نام، انتخاب نقش و تکمیل اطلاعات تأمین‌کننده.
- [`routes/supplier.php`](routes/supplier.php) — routeهای پنل تأمین‌کننده.
- [`routes/web.php`](routes/web.php) — صفحهٔ خانه و صفحات عمومی تأمین‌کنندگان.
- [`routes/api.php`](routes/api.php) — API نسخهٔ ۱، از جمله محصولات تأمین‌کننده.
- [`routes/outsourcing.php`](routes/outsourcing.php) — صفحهٔ درخواست‌های تکمیل محصول برون‌سپاری.
- [`bootstrap/app.php`](bootstrap/app.php) — ثبت فایل routeهای تأمین‌کننده.
