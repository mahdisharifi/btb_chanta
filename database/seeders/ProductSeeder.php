<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str; // افزوده شده برای تولید اسلاگ
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\RetailPrice;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\User;
use App\Enums\PriceType;
use App\Enums\ProductStatus;
use App\Enums\UserRole;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::with('user')->get();
        $categories = Category::whereNotNull('parent_id')->get();
        $retailerPremiums = User::role(UserRole::RETAILER_PREMIUM->value)->get();
        $retailerBasics = User::role(UserRole::RETAILER_BASIC->value)->get();

        if ($suppliers->isEmpty() || $categories->isEmpty()) {
            $this->command->warn('No suppliers or categories found. Run SupplierSeeder and CategorySeeder first.');
            return;
        }

        $products = [
            // ─── Supplier 1: Electronics ───
            [
                'supplier_index' => 0,
                'category_slug' => 'samsung',
                'title' => 'گوشی سامسونگ Galaxy S25 Ultra',
                'sku' => 'SAM-S25U-256',
                'short_description' => 'گوشی پرچمدار سامسونگ با دوربین ۲۰۰ مگاپیکسل و پردازنده Snapdragon 8 Elite',
                'full_description' => '<h2>سامسونگ Galaxy S25 Ultra</h2><p>جدیدترین پرچمدار سامسونگ با صفحه‌نمایش ۶.۹ اینچی Dynamic AMOLED 2X، دوربین اصلی ۲۰۰ مگاپیکسل، پردازنده Snapdragon 8 Elite، حافظه داخلی ۲۵۶ گیگابایت و باتری ۵۰۰۰ میلی‌آمپر ساعت. مقاوم در برابر آب و گرد و غبار با استاندارد IP68.</p>',
                'specifications' => ['صفحه نمایش' => '6.9 اینچ', 'پردازنده' => 'Snapdragon 8 Elite', 'رم' => '12 گیگابایت', 'حافظه' => '256 گیگابایت', 'دوربین' => '200 مگاپیکسل', 'باتری' => '5000 mAh', 'سیستم عامل' => 'Android 15'],
                'weight' => 232,
                'dimensions' => ['length' => 162.8, 'width' => 77.6, 'height' => 8.2],
                'is_featured' => true,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 250000000, // 25,000,000 تومان = 250,000,000 ریال
                'seo_title' => 'خرید عمده گوشی سامسونگ Galaxy S25 Ultra',
                'seo_description' => 'خرید عمده گوشی سامسونگ Galaxy S25 Ultra با بهترین قیمت از تأمین‌کنندگان معتبر',
            ],
            [
                'supplier_index' => 0,
                'category_slug' => 'samsung',
                'title' => 'گوشی سامسونگ Galaxy A55',
                'sku' => 'SAM-A55-128',
                'short_description' => 'گوشی میان‌رده سامسونگ با طراحی زیبا و عملکرد عالی',
                'full_description' => '<p>سامسونگ Galaxy A55 با صفحه‌نمایش Super AMOLED ۶.۶ اینچی و دوربین سه‌گانه ۵۰ مگاپیکسل.</p>',
                'specifications' => ['صفحه نمایش' => '6.6 اینچ', 'پردازنده' => 'Exynos 1480', 'رم' => '8 گیگابایت', 'حافظه' => '128 گیگابایت'],
                'weight' => 213,
                'dimensions' => ['length' => 158.0, 'width' => 77.0, 'height' => 8.2],
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 85000000,
                'seo_title' => 'خرید عمده گوشی سامسونگ Galaxy A55',
                'seo_description' => 'خرید عمده سامسونگ A55 با قیمت مناسب',
            ],
            [
                'supplier_index' => 0,
                'category_slug' => 'apple',
                'title' => 'آیفون ۱۶ پرو مکس ۲۵۶ گیگابایت',
                'sku' => 'APL-IP16PM-256',
                'short_description' => 'جدیدترین آیفون اپل با چیپ A18 Pro و دوربین پیشرفته',
                'full_description' => '<p>آیفون ۱۶ پرو مکس با صفحه‌نمایش ۶.۹ اینچی Super Retina XDR و دوربین ۴۸ مگاپیکسل.</p>',
                'specifications' => ['صفحه نمایش' => '6.9 اینچ', 'چیپ' => 'A18 Pro', 'حافظه' => '256 گیگابایت', 'دوربین' => '48 مگاپیکسل'],
                'weight' => 227,
                'dimensions' => ['length' => 163.0, 'width' => 77.6, 'height' => 8.3],
                'is_featured' => true,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 450000000,
                'seo_title' => 'خرید عمده آیفون 16 پرو مکس',
                'seo_description' => 'خرید عمده آیفون 16 پرو مکس با گارانتی معتبر',
            ],
            [
                'supplier_index' => 0,
                'category_slug' => 'xiaomi',
                'title' => 'شیائومی ردمی نوت ۱۳ پرو',
                'sku' => 'XIA-RN13P-256',
                'short_description' => 'گوشی پرفروش شیائومی با دوربین ۲۰۰ مگاپیکسل',
                'full_description' => '<p>ردمی نوت ۱۳ پرو با صفحه‌نمایش AMOLED و شارژ سریع ۶۷ وات.</p>',
                'specifications' => ['صفحه نمایش' => '6.67 اینچ', 'پردازنده' => 'Snapdragon 7s Gen 2', 'رم' => '8 گیگابایت'],
                'weight' => 187,
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 75000000,
                'seo_title' => 'خرید عمده شیائومی ردمی نوت 13 پرو',
                'seo_description' => 'خرید عمده ردمی نوت 13 پرو با قیمت عمده',
            ],
            [
                'supplier_index' => 0,
                'category_slug' => 'gaming-laptop',
                'title' => 'لپ‌تاپ گیمینگ ایسوس ROG Strix G16',
                'sku' => 'ASUS-ROG-G16',
                'short_description' => 'لپ‌تاپ گیمینگ قدرتمند با کارت گرافیک RTX 4070',
                'full_description' => '<p>ایسوس ROG Strix G16 با پردازنده Intel Core i9 نسل ۱۴ و گرافیک RTX 4070.</p>',
                'specifications' => ['پردازنده' => 'Core i9-14900HX', 'رم' => '32 گیگابایت', 'گرافیک' => 'RTX 4070', 'حافظه' => '1TB SSD'],
                'weight' => 2500,
                'is_featured' => true,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 680000000,
                'seo_title' => 'خرید عمده لپ‌تاپ گیمینگ ایسوس ROG',
                'seo_description' => 'خرید عمده لپ‌تاپ گیمینگ ASUS ROG Strix',
            ],

            // ─── Supplier 2: Home Appliances ───
            [
                'supplier_index' => 1,
                'category_slug' => 'refrigerator',
                'title' => 'یخچال فریزر ساید بای ساید ال جی',
                'sku' => 'LG-SBS-J267',
                'short_description' => 'یخچال ساید بای ساید ال جی با سیستم Linear Cooling',
                'full_description' => '<p>یخچال فریزر ساید بای ساید ال جی مدل J267 با حجم ۲۶ فوت و سیستم خنک‌کننده Linear Cooling.</p>',
                'specifications' => ['حجم' => '26 فوت', 'رنگ' => 'نقره‌ای', 'نوع' => 'ساید بای ساید', 'تکنولوژی' => 'Linear Cooling'],
                'weight' => 98000,
                'is_featured' => true,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 320000000,
                'seo_title' => 'خرید عمده یخچال ال جی ساید بای ساید',
                'seo_description' => 'خرید عمده یخچال ال جی با بهترین قیمت',
            ],
            [
                'supplier_index' => 1,
                'category_slug' => 'washing-machine',
                'title' => 'ماشین لباسشویی ۹ کیلویی بوش',
                'sku' => 'BOSCH-WM9-S450',
                'short_description' => 'ماشین لباسشویی ۹ کیلویی بوش با تکنولوژی i-DOS',
                'full_description' => '<p>ماشین لباسشویی بوش سری ۸ با ظرفیت ۹ کیلوگرم و سیستم هوشمند i-DOS.</p>',
                'specifications' => ['ظرفیت' => '9 کیلوگرم', 'دور موتور' => '1400 RPM', 'کلاس انرژی' => 'A+++'],
                'weight' => 75000,
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 280000000,
                'seo_title' => 'خرید عمده ماشین لباسشویی بوش',
                'seo_description' => 'خرید عمده ماشین لباسشویی بوش 9 کیلویی',
            ],
            [
                'supplier_index' => 1,
                'category_slug' => 'dishwasher',
                'title' => 'ماشین ظرفشویی ۱۴ نفره سامسونگ',
                'sku' => 'SAM-DW14-W200',
                'short_description' => 'ماشین ظرفشویی سامسونگ با تکنولوژی WaterWall',
                'full_description' => '<p>ماشین ظرفشویی ۱۴ نفره سامسونگ با سیستم شست‌وشوی WaterWall.</p>',
                'specifications' => ['ظرفیت' => '14 نفره', 'سطح صدا' => '42 dB', 'مصرف آب' => '9.7 لیتر'],
                'weight' => 48000,
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 195000000,
                'seo_title' => 'خرید عمده ماشین ظرفشویی سامسونگ',
                'seo_description' => 'ماشین ظرفشویی سامسونگ 14 نفره با قیمت عمده',
            ],
            [
                'supplier_index' => 1,
                'category_slug' => 'stove',
                'title' => 'اجاق‌گاز ۵ شعله اخوان مدل ونوس',
                'sku' => 'AKH-VENUS-5',
                'short_description' => 'اجاق‌گاز رومیزی ۵ شعله اخوان با شیشه مقاوم',
                'full_description' => '<p>اجاق‌گاز ۵ شعله اخوان مدل ونوس با شیشه سکوریت و فندک اتوماتیک.</p>',
                'specifications' => ['تعداد شعله' => '5', 'نوع' => 'رومیزی', 'جنس' => 'استیل و شیشه'],
                'weight' => 15000,
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 45000000,
                'seo_title' => 'خرید عمده اجاق‌گاز اخوان',
                'seo_description' => 'اجاق‌گاز 5 شعله اخوان مدل ونوس با قیمت عمده',
            ],
            [
                'supplier_index' => 1,
                'category_slug' => 'vacuum-cleaner',
                'title' => 'جاروبرقی بوش سری ۴ مدل ProPower',
                'sku' => 'BOSCH-VC-PP4',
                'short_description' => 'جاروبرقی قدرتمند بوش با مکش ۲۴۰۰ وات',
                'full_description' => '<p>جاروبرقی بوش ProPower با قدرت مکش بالا و فیلتر HEPA.</p>',
                'specifications' => ['قدرت مکش' => '2400 وات', 'فیلتر' => 'HEPA', 'حجم مخزن' => '4 لیتر'],
                'weight' => 6500,
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 38000000,
                'seo_title' => 'خرید عمده جاروبرقی بوش',
                'seo_description' => 'جاروبرقی بوش ProPower با قیمت عمده',
            ],

            // ─── Supplier 3: Food & Cosmetics ───
            [
                'supplier_index' => 2,
                'category_slug' => 'dairy',
                'title' => 'شیر پرچرب کاله ۱ لیتری (بسته ۱۲ عددی)',
                'sku' => 'KAL-MILK-FC12',
                'short_description' => 'شیر پرچرب کاله بسته‌بندی ۱ لیتری — کارتن ۱۲ تایی',
                'full_description' => '<p>شیر پرچرب کاله با ۳.۲٪ چربی. بسته‌بندی تتراپک ۱ لیتری. هر کارتن شامل ۱۲ عدد.</p>',
                'specifications' => ['حجم' => '1 لیتر', 'چربی' => '3.2%', 'تعداد در کارتن' => '12'],
                'weight' => 12500,
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 4800000,
                'seo_title' => 'خرید عمده شیر کاله',
                'seo_description' => 'خرید عمده شیر پرچرب کاله 1 لیتری',
            ],
            [
                'supplier_index' => 2,
                'category_slug' => 'beverages',
                'title' => 'نوشابه کوکاکولا ۳۳۰ میلی‌لیتر (بسته ۲۴ عددی)',
                'sku' => 'COCA-330-24',
                'short_description' => 'نوشابه کوکاکولا قوطی ۳۳۰ میلی — کارتن ۲۴ تایی',
                'full_description' => '<p>نوشابه کوکاکولا کلاسیک در قوطی ۳۳۰ میلی‌لیتر. هر کارتن ۲۴ عدد.</p>',
                'specifications' => ['حجم' => '330 میلی‌لیتر', 'نوع بسته‌بندی' => 'قوطی', 'تعداد در کارتن' => '24'],
                'weight' => 8200,
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 3600000,
                'seo_title' => 'خرید عمده نوشابه کوکاکولا',
                'seo_description' => 'خرید عمده کوکاکولا 330 میلی‌لیتر',
            ],
            [
                'supplier_index' => 2,
                'category_slug' => 'snacks',
                'title' => 'چیپس خامه‌ترشی چی‌توز ۱۵۰ گرم (بسته ۲۰ عددی)',
                'sku' => 'CHT-SC-150-20',
                'short_description' => 'چیپس خامه‌ترشی چی‌توز — کارتن ۲۰ تایی',
                'full_description' => '<p>چیپس خامه‌ترشی چی‌توز ۱۵۰ گرمی. هر کارتن ۲۰ بسته.</p>',
                'specifications' => ['وزن' => '150 گرم', 'طعم' => 'خامه‌ترشی', 'تعداد در کارتن' => '20'],
                'weight' => 3200,
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 2400000,
                'seo_title' => 'خرید عمده چیپس چی‌توز',
                'seo_description' => 'خرید عمده چیپس چی‌توز 150 گرم',
            ],
            [
                'supplier_index' => 2,
                'category_slug' => 'cosmetics',
                'title' => 'کرم مرطوب‌کننده نیوآ ۱۵۰ میل (بسته ۶ عددی)',
                'sku' => 'NIV-MC-150-6',
                'short_description' => 'کرم مرطوب‌کننده نیوآ — بسته ۶ تایی',
                'full_description' => '<p>کرم مرطوب‌کننده نیوآ Soft حجم ۱۵۰ میلی‌لیتر. بسته ۶ عددی.</p>',
                'specifications' => ['حجم' => '150 میلی‌لیتر', 'نوع پوست' => 'انواع پوست', 'تعداد در بسته' => '6'],
                'weight' => 1100,
                'is_featured' => false,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 5400000,
                'seo_title' => 'خرید عمده کرم نیوآ',
                'seo_description' => 'خرید عمده کرم مرطوب‌کننده نیوآ',
            ],
            [
                'supplier_index' => 2,
                'category_slug' => 'perfume',
                'title' => 'ادکلن مردانه ساواج دیور ۱۰۰ میل',
                'sku' => 'DIOR-SAU-100',
                'short_description' => 'ادکلن ساواج دیور ادوتویلت ۱۰۰ میلی‌لیتر',
                'full_description' => '<p>عطر مردانه Sauvage از برند دیور. ادوتویلت ۱۰۰ میلی‌لیتر. رایحه تند و جذاب.</p>',
                'specifications' => ['حجم' => '100 میلی‌لیتر', 'نوع' => 'ادوتویلت', 'گروه بویایی' => 'فوگر'],
                'weight' => 350,
                'is_featured' => true,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 42000000,
                'seo_title' => 'خرید عمده ادکلن ساواج دیور',
                'seo_description' => 'خرید عمده ادکلن ساواج دیور 100 میل',
            ],

            // ─── Draft & Pending Products ───
            [
                'supplier_index' => 0,
                'category_slug' => 'mobile-accessories',
                'title' => 'کاور سیلیکونی آیفون ۱۶ پرو',
                'sku' => 'CASE-IP16P-SIL',
                'short_description' => 'کاور سیلیکونی اورجینال آیفون ۱۶ پرو',
                'full_description' => '<p>کاور سیلیکونی اصلی اپل برای آیفون ۱۶ پرو.</p>',
                'specifications' => ['جنس' => 'سیلیکون', 'سازگاری' => 'iPhone 16 Pro'],
                'weight' => 45,
                'is_featured' => false,
                'status' => ProductStatus::DRAFT,
                'base_price' => 8500000,
                'seo_title' => 'خرید عمده کاور آیفون 16',
                'seo_description' => 'کاور سیلیکونی آیفون 16 پرو',
            ],
            [
                'supplier_index' => 1,
                'category_slug' => 'audio-video',
                'title' => 'تلویزیون ۶۵ اینچ QLED سامسونگ',
                'sku' => 'SAM-TV65-QLED',
                'short_description' => 'تلویزیون ۶۵ اینچ QLED سامسونگ ۴K هوشمند',
                'full_description' => '<p>تلویزیون 65 اینچ QLED سامسونگ با کیفیت 4K و سیستم عامل Tizen.</p>',
                'specifications' => ['اندازه' => '65 اینچ', 'رزولوشن' => '4K UHD', 'تکنولوژی' => 'QLED'],
                'weight' => 22000,
                'is_featured' => false,
                'status' => ProductStatus::PENDING_REVIEW,
                'base_price' => 410000000,
                'seo_title' => 'خرید عمده تلویزیون سامسونگ QLED',
                'seo_description' => 'تلویزیون 65 اینچ QLED سامسونگ',
            ],
            [
                'supplier_index' => 2,
                'category_slug' => 'skincare',
                'title' => 'ست مراقبت پوست سراوی CeraVe',
                'sku' => 'CRV-SKIN-SET3',
                'short_description' => 'ست ۳ تایی مراقبت پوست سراوی',
                'full_description' => '<p>ست مراقبت پوست سراوی شامل ژل شست‌وشو، لوسیون مرطوب‌کننده و کرم آبرسان.</p>',
                'specifications' => ['شامل' => '3 محصول', 'نوع پوست' => 'حساس و خشک'],
                'weight' => 800,
                'is_featured' => false,
                'status' => ProductStatus::PENDING_REVIEW,
                'base_price' => 12000000,
                'seo_title' => 'خرید عمده ست سراوی CeraVe',
                'seo_description' => 'ست مراقبت پوست سراوی با قیمت عمده',
            ],
            [
                'supplier_index' => 0,
                'category_slug' => 'computer-parts',
                'title' => 'کارت گرافیک NVIDIA RTX 4090',
                'sku' => 'NV-RTX4090-24G',
                'short_description' => 'کارت گرافیک GeForce RTX 4090 با ۲۴ گیگابایت حافظه GDDR6X',
                'full_description' => '<p>قدرتمندترین کارت گرافیک NVIDIA برای گیمینگ و کارهای حرفه‌ای.</p>',
                'specifications' => ['حافظه' => '24GB GDDR6X', 'CUDA Cores' => '16384', 'بوست کلاک' => '2520 MHz'],
                'weight' => 2200,
                'is_featured' => true,
                'status' => ProductStatus::ACTIVE,
                'base_price' => 520000000,
                'seo_title' => 'خرید عمده کارت گرافیک RTX 4090',
                'seo_description' => 'کارت گرافیک RTX 4090 با قیمت عمده',
            ],
        ];

        foreach ($products as $productData) {
            $supplier = $suppliers[$productData['supplier_index']] ?? $suppliers->first();
            $category = $categories->firstWhere('slug', $productData['category_slug']);

            if (!$category) {
                // Fallback to first available category
                $category = $categories->first();
            }

            $basePrice = $productData['base_price'];

            $product = Product::create([
                'supplier_id' => $supplier->id,
                'category_id' => $category->id,
                'title' => $productData['title'],
                'slug' => Str::slug($productData['title']), // تولید اسلاگ بر اساس عنوان محصول
                'sku' => $productData['sku'],
                'short_description' => $productData['short_description'],
                'full_description' => $productData['full_description'],
                'specifications' => $productData['specifications'],
                'weight' => $productData['weight'],
                'dimensions' => $productData['dimensions'] ?? null,
                'is_active' => $productData['status'] === ProductStatus::ACTIVE,
                'is_featured' => $productData['is_featured'],
                'status' => $productData['status'],
                'view_count' => rand(10, 5000),
                'seo_title' => $productData['seo_title'] ?? null,
                'seo_description' => $productData['seo_description'] ?? null,
            ]);

            // Only create prices for non-draft products
            if ($productData['status'] !== ProductStatus::DRAFT) {
                $this->createPrices($product, $basePrice);
                $this->createRetailPrices($product, $basePrice, $retailerPremiums, $retailerBasics);
            }
        }
    }

    /**
     * Create multiple price types for a product.
     */
    private function createPrices(Product $product, int $basePrice): void
    {
        $priceVariants = [
            [
                'price_type' => PriceType::SUB_CARTON,
                'price' => $basePrice,
                'min_quantity' => 1,
                'max_quantity' => 4,
                'description' => 'قیمت زیر کارتن (۱ تا ۴ عدد)',
            ],
            [
                'price_type' => PriceType::CARTON,
                'price' => (int) ($basePrice * 0.97), // 3% discount
                'min_quantity' => 5,
                'max_quantity' => null,
                'description' => 'قیمت کارتن (۵ عدد به بالا)',
            ],
            [
                'price_type' => PriceType::WARRANTY,
                'price' => (int) ($basePrice * 1.05), // 5% markup
                'min_quantity' => 1,
                'warranty_months' => 12,
                'description' => 'با گارانتی ۱۲ ماهه',
            ],
            [
                'price_type' => PriceType::WARRANTY,
                'price' => (int) ($basePrice * 1.08), // 8% markup
                'min_quantity' => 1,
                'warranty_months' => 18,
                'description' => 'با گارانتی ۱۸ ماهه',
            ],
            [
                'price_type' => PriceType::CHECK_1_MONTH,
                'price' => (int) ($basePrice * 1.03), // 3% markup
                'min_quantity' => 1,
                'check_months' => 1,
                'description' => 'چکی ۱ ماهه',
            ],
            [
                'price_type' => PriceType::CHECK_2_MONTH,
                'price' => (int) ($basePrice * 1.06), // 6% markup
                'min_quantity' => 1,
                'check_months' => 2,
                'description' => 'چکی ۲ ماهه',
            ],
            [
                'price_type' => PriceType::CHECK_3_MONTH,
                'price' => (int) ($basePrice * 1.09), // 9% markup
                'min_quantity' => 1,
                'check_months' => 3,
                'description' => 'چکی ۳ ماهه',
            ],
        ];

        foreach ($priceVariants as $variant) {
            ProductPrice::create(array_merge($variant, [
                'product_id' => $product->id,
                'is_active' => true,
            ]));
        }
    }

    /**
     * Create retail prices for active products.
     */
    private function createRetailPrices(
        Product $product,
        int $basePrice,
                $retailerPremiums,
                $retailerBasics
    ): void {
        // Premium retailers set higher markup (15-25%)
        foreach ($retailerPremiums as $retailer) {
            $markup = rand(115, 125) / 100;
            RetailPrice::create([
                'product_id' => $product->id,
                'user_id' => $retailer->id,
                'retail_price' => (int) ($basePrice * $markup),
                'is_published' => (bool) rand(0, 1),
            ]);
        }

        // Basic retailers set markup (18-30%)
        foreach ($retailerBasics as $retailer) {
            $markup = rand(118, 130) / 100;
            RetailPrice::create([
                'product_id' => $product->id,
                'user_id' => $retailer->id,
                'retail_price' => (int) ($basePrice * $markup),
                'is_published' => (bool) rand(0, 1),
            ]);
        }
    }
}
