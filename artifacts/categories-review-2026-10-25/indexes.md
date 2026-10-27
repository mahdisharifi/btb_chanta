# بازبینی مستقل ایندکس‌ها

تاریخ: ۲۰۲۶-۱۰-۲۵. محدوده: queryهای index/show در `app/Http/Controllers/Web/CategoryController.php` و جدول‌های وابسته. فقط‌خواندنی؛ هیچ migration، DDL، تغییر داده یا حذف ایندکس اجرا نشد.

## نتیجه

برای افزودن ایندکس جدید به `/categories` شواهدی به دست نیامد. مشکل N+1 گزارش‌شده با افزودن ایندکس حل نمی‌شود. یک مورد کم‌اولویتِ ایندکس تکراری تأیید شد.

### I1 — P3 / کم: ایندکس غیرunique روی همان ستونِ دارای unique دوباره ساخته شده است

- محل: `database/migrations/2026_05_23_222616_add_highly_recommended_indexes_to_tables.php:19` تا `:21` برای `products_sku_idx` و `:26` تا `:28` برای `categories_slug_idx`.
- شاهد اولیه: migration ساخت دسته در `database/migrations/2026_02_15_105810_create_categories_table.php:18` از قبل slug را unique می‌کند؛ migration ساخت محصول در `database/migrations/2026_02_15_105812_create_products_table.php:21` نیز sku را unique می‌کند. شرط `Schema::hasIndex` در migration بعدی فقط نام تازه را می‌سنجد و وجود ایندکس معادل با نام دیگر مانع ساخت نمی‌شود.
- تأیید اجرایی مستقل با `SHOW INDEX`: در DB فعلی، `categories_slug_unique` و `categories_slug_idx` هر دو فقط ستون slug دارند؛ `products_sku_unique` و `products_sku_idx` نیز هر دو فقط ستون sku دارند. بنابراین موضوع صرفاً حدس بر اساس فایل‌های migration نیست.
- بازتولید بدون نوشتن: اجرای `SHOW INDEX FROM categories` و `SHOW INDEX FROM products`؛ مقایسهٔ Key_name، Non_unique و ترتیب Column_name.
- اثر: نگهداری B-tree اضافی در هر ایجاد/حذف یا تغییر ستون و اشغال فضای اضافه؛ شدت یا هزینهٔ زمانی آن در این داده اندازه‌گیری نشده است. ایندکس nonunique هیچ قید دادهٔ تازه‌ای فراهم نمی‌کند و lookup همان ستون از unique قابل انجام است.
- پیشنهاد: در یک migration آتی و مستقل، پس از بررسی schema مقصد و وابستگی‌ها، فقط nonuniqueهای همسان حذف شوند و uniqueها حفظ شوند. migration تاریخی و داده‌ها بازنویسی نشوند. این مورد مانع استفاده از صفحه نیست و برای این بازبینی تغییری اجرا نشد.

## تطبیق queryها با ایندکس‌های موجود

| مصرف‌کننده و محل | شکل فیلتر/ترتیب | ایندکس تأییدشده در DB فعلی |
| --- | --- | --- |
| rootها؛ `CategoryController.php:20` تا `:34` | parent_id IS NULL، is_active، soft-delete، sort_order | `categories_parent_id_is_active_sort_order_index(parent_id,is_active,sort_order)` |
| فرزند و نوه؛ `:23` تا `:30` | parent_id IN (...)، is_active، soft-delete، sort_order | همان ایندکس؛ برای چند parent تضمین حذف همهٔ sortingها ادعا نمی‌شود |
| withCount محصول؛ `:26`، `:30`، `:33` و accessor مدل | category_id، is_active، status، soft-delete | `products_category_active_status_idx(category_id,is_active,status)` |
| route binding دسته در show | slug و soft-delete | `categories_slug_unique(slug)`؛ ایندکس duplicate نیز فعلاً وجود دارد |
| descendants در show؛ `:83` | پیمایش parent_id به صورت recursive | prefix parent_id ایندکس ترکیبی categories و `categories_parent_active_idx` موجود است |
| فهرست محصول show؛ `:85` تا `:125` | category_id IN (...)، is_active، status، pricedFirst EXISTS، created_at DESC، paginate | ترکیب category/active/status و `products_created_at_index` موجودند؛ ترتیب CASE/EXISTS الزاماً از این B-treeها تأمین نمی‌شود |
| pricedFirst؛ `app/Models/Product.php:232` | product_supplier بر product_id، suppliers بر id، product_prices بر product_supplier_id | ایندکس‌های prefix product_id در product_supplier، PRIMARY suppliers، و چند ایندکس با prefix product_supplier_id در product_prices وجود دارند |
| گالری show؛ `CategoryController.php:94` | product_id، file_type، is_primary DESC، sort_order | `product_galleries_product_id_is_primary_index` و `product_galleries_product_id_sort_order_index` موجودند؛ برتری ترکیب جدید با EXPLAIN/حجم داده اثبات نشده است |

در schema واقعی، `products_category_id_is_active_index(category_id,is_active)` با prefix ایندکس سه‌ستونی هم‌پوشانی دارد و `categories_parent_active_idx(parent_id,is_active)` نیز prefix ایندکس سه‌ستونی دسته است. این‌ها را برخلاف duplicate تک‌ستونی، صرفاً به دلیل prefix حذف‌پذیر قطعی اعلام نمی‌کنیم؛ اندازه، queryهای سایر صفحات، انتخاب optimizer و نقش FK باید پیش از حذف سنجیده شود.

ستون deleted_at در دو ایندکس اصلی بالا نیست. بدون سنجش selectivity و نسبت رکوردهای soft-deleted و بدون مقایسهٔ plan/زمان، ایندکس جدید دارای deleted_at پیشنهاد نمی‌شود. همین‌طور filesort در CASE/EXISTS به‌تنهایی مدرک نیاز به ایندکس تازه نیست.

## بررسی واقعاً اجراشده و محدودیت‌ها

- این عامل migrationهای اصلی و تکمیلی categories/products را خواند، ترتیب ستون‌ها و duplicateها را مستقل بررسی کرد و `SHOW INDEX` را برای categories، products، product_supplier، product_prices و product_galleries روی اتصال فعلی برنامه اجرا کرد.
- بازبین مستقل query/MySQL تیم قبلاً EXPLAIN روی MariaDB 10.4.32 با strict و ONLY_FULL_GROUP_BY اجرا کرده و استفاده از `categories_parent_id_is_active_sort_order_index` و `products_category_active_status_idx` را گزارش کرده است؛ این شاهد متعلق به همان بازبین است. این عامل EXPLAIN را دوباره اجرا نکرد و تأیید مستقل خودش را به metadata واقعی و تطبیق query محدود می‌کند.
- `EXPLAIN ANALYZE`، benchmark دادهٔ حجیم، اندازهٔ دیسکی ایندکس‌ها، write throughput، قفل/زمان migration و رفتار هاست تولید اجرا یا اندازه‌گیری نشد. اجرای migration یا index cleanup جزو این کار نبود.
