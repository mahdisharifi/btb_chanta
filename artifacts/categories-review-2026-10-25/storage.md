# بازبینی مستقل فایل و ذخیره‌سازی

تاریخ: ۲۰۲۶-۱۰-۲۵. محدوده: آیکون/تصویر دسته در index و تولیدکنندهٔ مستقیم آن در پنل؛ گالری کالای show به‌عنوان مصرف‌کنندهٔ بلافصل. هیچ upload/delete یا تغییر داده انجام نشد؛ بررسی گستردهٔ چرخهٔ رسانهٔ برون‌سپاری جزو این کار نیست.

## S1 — P2 / متوسط: حذف تصویر دسته قبل از ذخیرهٔ فرم، و حذف فایل قدیمی پیش از commit

- محل: `app/Livewire/Admin/Categories/Create.php:281` تا `:288`؛ action `removeImage` در `:285` فایل موجود را فیزیکی حذف می‌کند ولی مدل/ستون image_path در این action به‌روز نمی‌شود. دکمهٔ تصویر موجود در `resources/views/livewire/admin/categories/create.blade.php:215` همین action را صدا می‌زند.
- سناریوی بازتولید در محیط تست: دسته‌ای با تصویر واقعی باز کنید، دکمهٔ حذف تصویر را بزنید و بدون Save صفحه را ترک کنید. فایل قبلاً حذف شده ولی DB هنوز image_path قبلی را دارد؛ در بازکردن دوبارهٔ فرم، پیش‌نمایش از URL همان فایل حذف‌شده ساخته می‌شود (`:213`). این سناریو اجرا نشد چون حذف فایل واقعی مجازِ این بازبینی فقط‌خواندنی نیست.
- سناریوی دوم از همان نقص چرخه: هنگام replacement در `Create.php:355` فایل قدیمی حذف و در `:357` فایل جدید ذخیره می‌شود؛ سپس update مدل و بررسی attributes ادامه دارد. خطای قابل‌انتظارِ حذف attribute مصرف‌شده در `:410` می‌تواند ValidationException بدهد. transaction در `:441` rollback می‌شود اما حذف فایل قدیمی یا ایجاد فایل جدید rollback نمی‌شود؛ DB به مسیر قدیمیِ حذف‌شده برمی‌گردد و فایل جدید هم ممکن است بی‌مرجع بماند. commit موفق تازه در `:432` است.
- شاهد: مسیر کد/ترتیب عملیات و binding دکمه مستقل خوانده شد؛ روی دادهٔ فعلی ۳۳ دسته، هیچ image_path ثبت‌شده‌ای وجود ندارد. بنابراین **نمی‌گوییم تصویر دسته‌های فعلی از دست رفته است**؛ نقص در مسیر پشتیبانی‌شدهٔ ثبت/ویرایش تصویر است. خود index دسته‌ها image_path را نمایش نمی‌دهد، پس اثر مستقیم فعلی آن صفحه از این مورد ادعا نمی‌شود.
- اصلاح پیشنهادی: removeImage فقط state فرم/علامت حذف را تغییر دهد. فایل جدید با نام تازه stage شود؛ مدل در transaction موفق تغییر کند و پس از commit فایل قبلی حذف شود. در failure فایل stageشده پاک شود و فایل قدیمی دست‌نخورده بماند. در تست مسیر Save موفق، Cancel و validation failure پس از آماده‌سازی فایل پوشش داده شوند. نیازی به تغییر storage driver یا سرویس جدید صرفاً برای این مشکل نیست.

## قرارداد آیکون و تصویر: مشاهدات هم‌پوشان با بازبین فرانت/امنیت

- `resources/views/pages/categories/index.blade.php:175` و `:212` مقدار icon را نام component Heroicon می‌دانند؛ `Category.php:241` در accessor icon_url برای مقدارهای شروع‌شده با `/` رفتار مسیر storage دارد. index از این accessor استفاده نمی‌کند؛ بنابراین icon به شکل path فایل، در renderer فعلی index پشتیبانی نمی‌شود و می‌تواند به همان خطای dynamic component گزارش‌شده منتهی شود. این **یافتهٔ مستقل جدید نیست** و با همان اعتبارسنجی/قرارداد icon باید حل شود.
- آمار واقعی metadata: ۳۳ دسته، سه icon غیرخالی که هر سه prefix heroicon دارند؛ icon شبیه مسیر فایل در دادهٔ فعلی صفر است. خود مقدار یا مسیر خصوصی فایل‌ها منتشر نشد. بنابراین migration انبوه برای «تبدیل pathهای فعلی» بدون شاهد لازم نیست.
- show زیرشاخه‌ها در `resources/views/pages/categories/show.blade.php:60` اگر آن بخش رندر شود، icon را به صورت `asset('storage/'.$sub->icon)` تصویر می‌کند؛ Heroicon name مسیر فایل نیست. در کنترلر فعلی متغیر subcategories به view داده نمی‌شود، پس خرابی درخواست تصویر این بخش در صفحهٔ فعلی مشاهده نشده است. هنگام رفع بخش زیرشاخه‌ها، renderer آیکون باید هم‌زمان با index هماهنگ شود؛ صرف اتصال داده، مشکل قرارداد را فعال می‌کند.
- ثبت تصویر دسته در `Create.php:243` به image با mimes jpg/jpeg/png/webp و سقف ۲۰۴۸KB محدود است و فایل در `:357` روی public/categories ذخیره می‌شود. این مسیر تصویر، از icon رشته‌ای جداست؛ SVG آپلودی دسته در این rule پذیرفته نمی‌شود. بررسی امنیتی جامع upload این گزارش نیست.

## گالری کالای show: بررسی واقعی مسیر و fallback

- مصرف‌کنندهٔ واقعی `<x-product.card>` در `resources/views/pages/categories/show.blade.php:113` است؛ فایل component متناظر `resources/views/components/product/card.blade.php` است.
- کنترلر در `CategoryController.php:94` تا `:99` تصویر نوع image با اولویت primary و sort_order و limit=1 را eager-load می‌کند. component در `product/card.blade.php:27` از `getVersionUrl('sm')` و در `:30`/`:37` از placeholder SVG استفاده می‌کند.
- `app/Models/ProductGallery.php:164` تا `:213` aliasهای sm/small را می‌سنجد و وجود فایل روی public disk را پیش از URL بررسی می‌کند؛ `full_url` در `:214` به بعد مسیر اصلی را می‌سازد. component در نبود variant به اصل فایل fallback نمی‌کند و مستقیم placeholder می‌دهد. این رفتار برای رسانهٔ قدیمی/variant مفقود قابل‌توجه است، اما خرابی فعلیِ این سناریو در نمونه تأیید نشد و به‌عنوان issue تازهٔ قطعی شمرده نمی‌شود.
- نمونهٔ واقعی فقط‌خواندنی: show کنترلر برای root فعال id=1 بدون رندر کامل اجرا شد؛ صفحهٔ اول ۱۶ کالا و ۱۶ تصویر منتخب داشت و **هر ۱۶ مورد sm موجود** داشتند. فایل `public/images/placeholders/product-default.svg` نیز موجود بود. این بررسی filesystem است، نه تأیید HTTP URL روی هاست یا تمام کالاهای ۷۵ صفحه.
- تولیدکنندهٔ مستقیم مدیر در `app/Livewire/Admin/Products/Create.php:824` از ProductImageService استفاده می‌کند؛ `app/Services/ProductImageService.php:13` اندازه‌های xs/sm/md/lg/xl را تعریف می‌کند و در `:55` به بعد WebP و metadata نسخه‌ها را تولید می‌کند. اسکن کل تولیدکننده‌ها/تمام عملیات فایل محصول خارج از محدودهٔ این گزارش بود.

## مرز اعتبارسنجی

انجام شد: خواندن renderer واقعی index/show/product.card، accessorهای Category/ProductGallery، مسیر upload/remove/replace دسته و تولید variant مستقیم محصول؛ query فقط‌خواندنی شمارش metadata دسته‌ها؛ اجرای کنترلر show برای نمونه و بررسی وجود variant/placeholder. آیکون invalid و og-default مفقود از قبل در گزارش‌های دیگر آمده‌اند و دوباره به تعداد یافته‌ها اضافه نشوند.

انجام نشد: upload/replace/remove واقعی، شبیه‌سازی failure یا rollback با فایل، درخواست HTTP همهٔ تصاویر، پیمایش تمام کالاها، migration/storage cleanup، تغییر symlink، بررسی bucket/CDN/production یا رسانه‌های برون‌سپاری. هیچ فایل کاربردی، رکورد یا cache عمداً تغییر نکرد؛ فقط این گزارش نوشته شد.
