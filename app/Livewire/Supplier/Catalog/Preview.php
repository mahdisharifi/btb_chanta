<?php

namespace App\Livewire\Supplier\Catalog;

use App\Enums\ProductStatus;
use Livewire\Component;
use App\Models\CatalogTemplate;
use App\Models\Product;
use App\Enums\PriceType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use PDF;

class Preview extends Component
{
    public bool $isAdmin = false;

    public $catalog;
    public $supplierLogoUrl;
    public $supplierLogoPath;
    public $supplier;
    public $supplierName;
    public $template;
    public $settings = [];
    public $groupedProducts = [];
    public $selectedPriceTypes = [];
    public $separateCategories = false;
    public $showCoverPage = true;
    public $priceLabels = [];
    public $progressKey = null;
    private $enableProgress = false;

    public function mount($catalog)
    {
        // تولید کلید یونیک برای tracking progress در ابتدا
        $this->progressKey = 'catalog_progress_' . session()->getId() . '_' . time();
        
        $user = Auth::user();
        $this->isAdmin = $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('super-admin');

        $this->template = CatalogTemplate::findOrFail($catalog);

        if ($this->isAdmin) {
            // ادمین: supplier صاحب کاتالوگ را بگیر (برای صفحه اول)
            $this->supplier = \App\Models\Supplier::find($this->template->supplier_id);

            if (!$this->supplier) {
                // اگر کاتالوگ supplier نداشت، supplier خود ادمین را بگیر
                $this->supplier = \App\Models\Supplier::where('user_id', $user->id)
                    ->where('type', 'admin')
                    ->first();
            }

            abort_if(! $this->supplier, 403, 'شما پروفایل تامین‌کننده ندارید.');
        } else {
            // تامین‌کننده: فقط کاتالوگ خودش
            $this->supplier = $user->supplier;
            abort_if(! $this->supplier, 403, 'شما پروفایل تامین‌کننده ندارید.');

            // بررسی مالکیت
            abort_if($this->template->supplier_id !== $this->supplier->id, 403, 'این کاتالوگ متعلق به شما نیست.');
        }

        $this->supplierLogoPath = ($this->supplier->logo_path && file_exists(storage_path('app/public/' . $this->supplier->logo_path)))
    ? storage_path('app/public/' . $this->supplier->logo_path)
    : null;

        $this->supplierName = $this->supplier->company_name ?? 'مدیریت مرکزی';
        $this->supplierLogoUrl = ($this->supplier->logo_path && file_exists(storage_path('app/public/' . $this->supplier->logo_path)))
            ? asset('storage/' . $this->supplier->logo_path)
            : null;

        $this->settings = $this->template->settings ?? [];
        
        // Resolve settings: اگر source_catalog_id دارد، از تنظیمات کاتالوگ source استفاده کن
        $this->resolveSettings();

        $this->separateCategories = filter_var($this->settings['separate_categories'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->showCoverPage = filter_var(
            $this->settings['show_cover_page']
            ?? $this->settings['show_cover']
            ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        $this->priceLabels = PriceType::options();

        $selectedPrices = $this->settings['price_types']
            ?? $this->settings['prices']
            ?? 'all';

        $this->selectedPriceTypes = $selectedPrices;

        if ($this->selectedPriceTypes === 'all' || empty($this->selectedPriceTypes)) {
            $this->selectedPriceTypes = PriceType::values();
        }

        // بارگذاری داده‌ها برای preview (بدون progress)
        $this->loadCatalogData($this->isAdmin ? null : $this->supplier->id);
    }

    /**
     * Resolve settings: اگر source_catalog_id دارد، از تنظیمات کاتالوگ source استفاده کن
     */
    private function resolveSettings()
    {
        if (isset($this->settings['custom_cover']['source_catalog_id']) && $this->settings['custom_cover']['source_catalog_id']) {
            $sourceCatalogId = $this->settings['custom_cover']['source_catalog_id'];
            $sourceCatalog = CatalogTemplate::find($sourceCatalogId);
            
            if ($sourceCatalog && isset($sourceCatalog->settings['custom_cover'])) {
                // استفاده از تنظیمات کاتالوگ source
                $this->settings['custom_cover'] = $sourceCatalog->settings['custom_cover'];
            }
        }
    }
    
    private function loadCatalogData($supplierId = null)
    {
        // تعیین supplier_ids برای فیلتر (قبل از ساخت کلید کش)
        $selectedSupplierIds = [];
        
        // اگر ادمین است و supplier_ids در config وجود دارد و خالی نیست
        if ($this->isAdmin && !empty($this->template->config['supplier_ids']) && is_array($this->template->config['supplier_ids'])) {
            $selectedSupplierIds = $this->template->config['supplier_ids'];
        } elseif ($supplierId) {
            // اگر supplier_id مشخص است (تامین‌کننده)
            $selectedSupplierIds = [$supplierId];
        }
        
        // دریافت timestamp آخرین تغییرات برای تشخیص نیاز به رفرش کش
        // این timestampها باعث می‌شوند وقتی داده‌ها تغییر کنند، کش خودکار رفرش شود
        $productsUpdatedAt = \App\Models\Product::max('updated_at');
        $pricesUpdatedAt = \App\Models\ProductPrice::max('updated_at');
        $supplierUpdatedAt = \App\Models\Supplier::whereIn('id', $selectedSupplierIds)->max('updated_at');
        
        // اضافه کردن timestamp برای داده‌های مرتبط دیگر
        $categoriesUpdatedAt = \App\Models\Category::max('updated_at');
        $attributesUpdatedAt = \App\Models\AttributeValue::max('updated_at');
        $imagesUpdatedAt = \App\Models\ProductGallery::max('updated_at');
        
        // تولید کلید کش یونیک برای این کاتالوگ و تنظیمات + timestamp تغییرات
        // نکته: selected_supplier_ids هم اضافه می‌شود تا با تغییر تامین‌کننده‌ها کش رفرش شود
        $cacheKey = 'catalog_' . $this->template->id . '_' . md5(json_encode([
            'supplier_ids' => $selectedSupplierIds, // تغییر مهم: استفاده از supplier_ids به جای supplier_id
            'settings' => $this->settings,
            'selected_prices' => $this->selectedPriceTypes,
            'products_updated' => $productsUpdatedAt,
            'prices_updated' => $pricesUpdatedAt,
            'supplier_updated' => $supplierUpdatedAt,
            'categories_updated' => $categoriesUpdatedAt,
            'attributes_updated' => $attributesUpdatedAt,
            'images_updated' => $imagesUpdatedAt,
        ]));
        
        // تلاش برای دریافت از کش
        $cached = Cache::get($cacheKey);
        if ($cached && isset($cached['grouped_products'])) {
            $this->groupedProducts = $cached['grouped_products'];
            $this->updateProgress(30, 'داده‌ها از کش بازیابی شدند');
            return;
        }
        
        $categoryIds = $this->settings['categories']
            ?? $this->template->selected_categories
            ?? 'all';

        $this->updateProgress(10, 'در حال فیلتر کردن محصولات...');

        // ساخت کوئری اصلی برای دریافت محصولات یونیک
        $query = Product::query()
            ->where('status', ProductStatus::ACTIVE->value);

        // فیلتر بر اساس تامین‌کنندگان
        if (!empty($selectedSupplierIds) && is_array($selectedSupplierIds)) {
            $query->whereHas('productSuppliers', function ($q) use ($selectedSupplierIds) {
                $q->whereIn('supplier_id', $selectedSupplierIds)
                    ->where('is_active', true)
                    ->whereHas('prices', function ($priceQuery) {
                        $priceQuery->where('is_active', true);
                    });
            });
        } elseif ($supplierId) {
            $query->whereHas('productSuppliers', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId)
                    ->where('is_active', true)
                    ->whereHas('prices', function ($priceQuery) {
                        $priceQuery->where('is_active', true);
                    });
            });
        }

        // فیلتر بر اساس دسته‌بندی
        if ($categoryIds !== 'all' && is_array($categoryIds) && count($categoryIds) > 0) {
            $query->whereIn('category_id', $categoryIds);
        }

        $this->updateProgress(15, 'در حال دریافت اطلاعات محصولات...');

        // دریافت همه محصولات یونیک با روابط مورد نیاز
        // بهینه‌سازی: اضافه کردن attributeValues.attribute برای جلوگیری از N+1
        $products = $query->with([
            'category',
            'primaryImage',
            'attributeValues.attribute', // eager load برای جلوگیری از N+1
            'productSuppliers' => function ($q) use ($selectedSupplierIds, $supplierId) {
                if (!empty($selectedSupplierIds) && is_array($selectedSupplierIds)) {
                    $q->whereIn('supplier_id', $selectedSupplierIds);
                } elseif ($supplierId) {
                    $q->where('supplier_id', $supplierId);
                }

                $q->where('is_active', true)
                    ->with(['prices' => function ($priceQuery) {
                        $priceQuery->where('is_active', true);
                    }]);
            }
        ])->get();

        $this->updateProgress(18, 'در حال پردازش محصولات...');

        // ساخت آرایه محصولات با محاسبه کمترین قیمت از همه تامین‌کنندگان
        $allItems = [];
        $totalProducts = $products->count();
        $processedProducts = 0;

        foreach ($products as $product) {
            $processedProducts++;
            
            // هر 10 محصول progress را بروز کن
            if ($processedProducts % 10 === 0) {
                $percent = 18 + (($processedProducts / $totalProducts) * 7); // 18% تا 25%
                $this->updateProgress($percent, "پردازش محصول {$processedProducts} از {$totalProducts}...");
            }

            $catName = $product->category?->name ?? 'بدون دسته‌بندی';

            // محاسبه کمترین قیمت برای هر نوع قیمت از بین همه تامین‌کنندگان
            $prices = [];
            
            foreach ($product->productSuppliers as $ps) {
                foreach ($ps->prices as $p) {
                    $key = $p->price_type instanceof \BackedEnum ? $p->price_type->value : $p->price_type;
                    $priceValue = is_array($p->price) ? ($p->price['price'] ?? $p->price) : $p->price;
                    
                    // انتخاب کمترین قیمت
                    if (!isset($prices[$key]) || $priceValue < ($prices[$key]['price'] ?? PHP_INT_MAX)) {
                        $prices[$key] = [
                            'price' => $priceValue,
                            'warranty_months' => $p->warranty_months,
                        ];
                    }
                }
            }

            // اگر هیچ قیمتی نداشت، این محصول رو نادیده بگیر
            if (empty($prices)) {
                continue;
            }
            
            // دریافت مقدار attribute عمومی و اختصاصی
            // بهینه‌سازی: استفاده از eager loaded data برای جلوگیری از N+1
            $attributeValues = [];
            $categoryId = $product->category_id;
            $showLabels = $this->settings['show_attribute_labels'] ?? true;
            
            // Helper function برای fix کردن جهت متن در PDF
            $fixTextDirection = function($text) {
                // اضافه کردن Left-to-Right Mark برای کنترل جهت پرانتزها
                return "\u{200E}" . $text . "\u{200E}";
            };
            
            // ابتدا مشخصه عمومی - استفاده از collection به جای کوئری
            if (!empty($this->settings['global_attribute'])) {
                $attrId = $this->settings['global_attribute'];
                // استفاده از eager loaded attributeValues
                $attrValue = $product->attributeValues->firstWhere('attribute_id', $attrId);
                if ($attrValue) {
                    // استفاده از eager loaded attribute relation
                    $attrName = $attrValue->attribute?->name ?? '';
                    if ($showLabels) {
                        $attributeValues[] = $fixTextDirection('(' . $attrName . ': ' . $attrValue->value . ')');
                    } else {
                        $attributeValues[] = $fixTextDirection('(' . $attrValue->value . ')');
                    }
                }
            }
            
            // سپس مشخصات اختصاصی دسته‌بندی - استفاده از collection به جای کوئری
            if (!empty($this->settings['category_attributes'][$categoryId])) {
                $categoryAttrs = $this->settings['category_attributes'][$categoryId];
                
                foreach ($categoryAttrs as $attrId) {
                    // استفاده از eager loaded attributeValues
                    $attrValue = $product->attributeValues->firstWhere('attribute_id', $attrId);
                    if ($attrValue) {
                        // استفاده از eager loaded attribute relation
                        $attrName = $attrValue->attribute?->name ?? '';
                        if ($showLabels) {
                            $attributeValues[] = $fixTextDirection('(' . $attrName . ': ' . $attrValue->value . ')');
                        } else {
                            $attributeValues[] = $fixTextDirection('(' . $attrValue->value . ')');
                        }
                    }
                }
            }
            
            $attributeValue = !empty($attributeValues) ? implode(' ', $attributeValues) : null;

            // دریافت کیفیت تصویر از تنظیمات
            $imageQuality = $this->settings['image_quality'] ?? 'sm';
            $imagePath = null;
            
            if ($product->primaryImage) {
                // اگر کیفیت original انتخاب شده باشد
                if ($imageQuality === 'original') {
                    $imagePath = $product->primaryImage->file_path;
                } else {
                    // استفاده از version مورد نظر
                    $imagePath = $product->primaryImage->getVersionPath($imageQuality) 
                                ?? $product->primaryImage->file_path;
                }
            }

            $allItems[] = [
                'id'               => $product->id,
                'title'            => $product->title,
                'sku'              => $product->sku,
                'image'            => $imagePath,
                'brand'            => $product->brand,
                'category_name'    => $catName,
                'category_order'   => $product->category?->sort_order ?? 0,
                'prices'           => $prices,
                'created_at'       => $product->created_at?->timestamp ?? 0,
                'price_updated_at' => $product->productSuppliers->max('price_updated_at')?->timestamp ?? 0,
                'attribute_value'  => $attributeValue,
            ];

        }

        // پردازش تنظیمات سورت - پشتیبانی از هر دو فرمت قدیم و جدید
        $sorts = $this->settings['sort'] ?? [];
        
        // اگر فرمت جدید خالی بود، از فرمت قدیمی استفاده کن
        if (empty($sorts)) {
            $sorts = [];
            for ($i = 1; $i <= 3; $i++) {
                $field = $this->settings["sort_level{$i}"] ?? '';
                $dir = $this->settings["sort_level{$i}_dir"] ?? 'asc';
                
                if ($field !== '' && $field !== null) {
                    $sorts[] = ['field' => $field, 'dir' => $dir];
                }
            }
        }
        
        if (!empty($sorts)) {
            usort($allItems, function ($a, $b) use ($sorts) {
                foreach ($sorts as $sort) {
                    $field = $sort['field'] ?? null;
                    if (!$field) continue;
                    $dir = strtolower($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                    $result = $this->compareItems($a, $b, $field, $dir);
                    if ($result !== 0) return $result;
                }
                return 0;
            });
        }


        $grouped = [];
        if ($this->separateCategories) {
            foreach ($allItems as $item) {
                $grouped[$item['category_name']][] = $item;
            }
        } else {
            $grouped['all'] = $allItems;
        }

        $this->groupedProducts = $grouped;
        
        // ذخیره در کش برای استفاده‌های بعدی (2 ساعت)
        Cache::put($cacheKey, [
            'grouped_products' => $this->groupedProducts,
            'generated_at' => now()->toDateTimeString(),
        ], now()->addHours(2));
        
        $this->updateProgress(30, 'داده‌ها آماده شدند، در حال تولید PDF...');
    }

    private function compareItems($a, $b, $sortKey, $dir)
    {
        if (!$sortKey) return 0;
        $valA = $this->getSortValue($a, $sortKey);
        $valB = $this->getSortValue($b, $sortKey);

        if (is_numeric($valA) && is_numeric($valB)) {
            if ($valA == $valB) return 0;
            $result = ($valA < $valB) ? -1 : 1;
        } else {
            $result = strcmp((string)$valA, (string)$valB);
        }

        return $dir === 'desc' ? -$result : $result;
    }

    private function getSortValue($item, $sortKey)
    {
        if (str_starts_with($sortKey, 'price_')) {
            $type = str_replace('price_', '', $sortKey);
            $priceData = $item['prices'][$type] ?? null;
            
            if (is_array($priceData)) {
                return $priceData['price'] ?? 0;
            }
            
            return $priceData ?? 0;
        }

        $map = [
            'product_name'   => 'title',
            'category'       => 'category_name',
            'category_order' => 'category_order',
            'sku'            => 'sku',
            'brand'          => 'brand',
            'name'           => 'title',            // کلید فرم ادمین
            'created_at'     => 'created_at',
            'price_updated_at' => 'price_updated_at',
        ];



        $key = $map[$sortKey] ?? $sortKey;
        return $item[$key] ?? '';
    }

    public function downloadPdf()
    {
        // استفاده از progressKey که در mount ساخته شده
        return $this->generatePdfResponse('pdf.supplier-catalog', 'list-');
    }

    public function downloadPdf2()
    {
        // استفاده از progressKey که در mount ساخته شده
        return $this->generatePdfResponse('pdf.supplier-catalog2', 'catalog-');
    }

    /**
     * بروزرسانی درصد پیشرفت در Cache
     */
    private function updateProgress($percent, $message = '')
    {
        // اگر progress غیرفعال است یا progressKey وجود ندارد، کاری نکن
        if (!$this->enableProgress || empty($this->progressKey)) {
            return;
        }
        
        $data = [
            'percent' => $percent,
            'message' => $message,
            'timestamp' => time()
        ];
        
        // استفاده از Cache به جای Session برای جلوگیری از lock
        Cache::put($this->progressKey, $data, now()->addMinutes(5));
        
        // حذف تاخیر مصنوعی برای کاهش فشار CPU
        // usleep(100000); // حذف شد - در production نیازی به تاخیر نیست
        
        \Log::info("📈 Progress: {$percent}% - {$message}");
    }

    /**
     * پاک کردن اطلاعات progress
     */
    private function clearProgress()
    {
        if (!empty($this->progressKey)) {
            Cache::forget($this->progressKey);
        }
    }

    private function generatePdfResponse($view, $prefix)
    {
        // فعال کردن progress برای PDF generation
        $this->enableProgress = true;
        
        \Log::info("🚀 Progress enabled for PDF generation");
        
        // شروع progress
        $this->updateProgress(0, 'آماده‌سازی...');

        $fileName = $prefix . jdate(now())->format('Y-m-d') . '.pdf';

        $this->updateProgress(3, 'در حال بارگذاری داده‌های کاتالوگ...');
        
        \Log::info('=== Before loadCatalogData ===');
        
        // بارگذاری مجدد داده‌های محصولات با progress tracking
        $this->loadCatalogData($this->isAdmin ? null : $this->supplier->id);

        \Log::info('=== After loadCatalogData ===');

        $this->updateProgress(40, 'در حال آماده‌سازی اطلاعات کاتالوگ...');

        $data = [
            'supplierName'       => $this->supplierName,
            'groupedProducts'    => $this->groupedProducts,
            'selectedPriceTypes' => $this->selectedPriceTypes,
            'priceLabels'        => $this->priceLabels,
            'settings'           => $this->settings,
            'template'           => $this->template,
            'separateCategories' => $this->separateCategories,
            'showCoverPage'      => $this->showCoverPage,
            'supplierLogoUrl'    => $this->supplierLogoUrl,
            'supplierLogoPath'   => $this->supplierLogoPath,
            'forPdf'             => true,
            'supplier'           => $this->supplier,
            'contactPhones'      => $this->settings['contact_phones'] ?? [],
            'globalAttribute'    => $this->settings['global_attribute'] ?? null,
            'coverTheme'         => $this->settings['cover_theme'] ?? 'default',
            'catalogFont'        => $this->settings['catalog_font'] ?? $this->settings['custom_cover_table']['settings']['catalog_font'] ?? 'vazir',
        ];
        
        $this->updateProgress(45, 'در حال آماده‌سازی قالب PDF...');
        
        // آزاد کردن session برای جلوگیری از timeout
        session()->save();
        
        $this->updateProgress(50, 'در حال رندر کردن صفحات PDF...');
        
        // دریافت فونت کاتالوگ
        $catalogFont = $this->settings['catalog_font'] ?? $this->settings['custom_cover_table']['settings']['catalog_font'] ?? 'vazir';
        
        $pdf = PDF::loadView($view, $data, [], [
            'format'        => 'A4',
            'margin_left'   => 6,
            'margin_right'  => 6,
            'margin_top'    => 6,
            'margin_bottom' => 6,
            'mode'          => 'utf-8',
            'default_font'  => $catalogFont, // اعمال فونت انتخابی
        ]);
        
        $this->updateProgress(75, 'در حال پردازش فایل PDF...');
        
        // تولید خروجی PDF
        $pdfOutput = $pdf->output();
        
        $this->updateProgress(90, 'در حال نهایی‌سازی کاتالوگ...');
        
        $this->updateProgress(100, 'کاتالوگ آماده است!');
        
        // پاک کردن progress بعد از 2 ثانیه
        sleep(2);
        $this->clearProgress();
        
        return response()->streamDownload(function () use ($pdfOutput) {
            echo $pdfOutput;
        }, $fileName);
    }

    public function render()
    {
        return view('livewire.supplier.catalog.preview')
            ->layout('components.layouts.blank');
    }
    
    /**
     * پاکسازی کش کاتالوگ
     */
    public function clearCatalogCache()
    {
        $supplierId = $this->isAdmin ? null : $this->supplier->id;
        
        // پاک کردن کش با استفاده از версینینگ
        // یک کلید نسخه جدید می‌سازیم که کلید کش را تغییر دهد
        $versionKey = 'catalog_version_' . $this->template->id;
        $newVersion = now()->timestamp;
        Cache::put($versionKey, $newVersion, now()->addDays(30));
        
        // رفرش داده‌ها - چون کلید کش تغییر کرده، داده‌های جدید بارگذاری می‌شوند
        $this->loadCatalogData($supplierId);
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'داده‌های کاتالوگ با موفقیت بروزرسانی شد.'
        ]);
    }
    
    /**
     * دریافت مقدار attribute برای یک محصول
     */
    private function getProductAttributeValue($product, $attributeId)
    {
        if (!$attributeId) {
            return null;
        }
        
        // دریافت attribute از محصول
        $attributeValue = $product->attributeValues()
            ->where('attribute_id', $attributeId)
            ->first();
            
        return $attributeValue ? $attributeValue->value : null;
    }
}
