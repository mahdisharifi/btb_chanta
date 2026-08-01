<?php

namespace App\Livewire\Supplier\Catalog;

use Livewire\Component;
use App\Models\CatalogTemplate;
use App\Models\Product;
use App\Enums\PriceType;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelPdf\Facades\Pdf;

class Preview2 extends Component
{
    public $catalog;
    public $supplierLogo;
    public $supplierLogoUrl;
    public $supplierLogoPath ;
    public $supplier;
    public $supplierName ;
    public $template;
    public $settings = [];
    public $groupedProducts = [];
    public $selectedPriceTypes = [];
    public $separateCategories = false;
    public $showCoverPage = true;
    public $priceLabels = [];

    public function mount($catalog)
    {
        $supplier = Auth::user()->supplier;

        $this->supplierLogoUrl = $supplier->logo_path
            ? asset('storage/' . ltrim($supplier->logo_path, '/'))
            : null;

        $this->supplierLogoPath = $supplier->logo_path
            ? storage_path('app/public/' . ltrim($supplier->logo_path, '/'))
            : null;

        $this->supplier=Auth::user()->supplier;
        $this->supplierName = Auth::user()->supplier->title ?? Auth::user()->name;

        $this->template = CatalogTemplate::where('id', $catalog)
            ->where('supplier_id', Auth::user()->supplier->id)
            ->firstOrFail();

        $this->settings = $this->template->settings ?? [];

        // تنظیم وضعیت تفکیک دسته‌بندی‌ها
        $this->separateCategories = filter_var($this->settings['separate_categories'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // تنظیم وضعیت نمایش صفحه اول (کاور)
        $this->showCoverPage = filter_var($this->settings['show_cover_page'] ?? true, FILTER_VALIDATE_BOOLEAN);

        // دریافت برچسب‌های فارسی و معتبر از کلاسی که در پروژه موجود است
        $this->priceLabels = PriceType::options();

        // استخراج انواع قیمت‌های انتخاب شده
        $this->selectedPriceTypes = $this->settings['prices'] ?? 'all';

        // اگر همه انتخاب شده بود یا مقداری نداشت، تمام قیمت‌های موجود در سیستم تامین کننده را قرار بده
        if ($this->selectedPriceTypes === 'all' || empty($this->selectedPriceTypes)) {
            $this->selectedPriceTypes = PriceType::values();
        }

        $this->loadCatalogData(Auth::user()->supplier->id);
    }

    private function loadCatalogData($supplierId)
    {
        $categoryIds = $this->settings['categories'] ?? 'all';

        $query = Product::query()
            ->whereHas('productSuppliers', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId)
                    ->where('is_active', true);
            });

        if ($categoryIds !== 'all' && is_array($categoryIds) && count($categoryIds) > 0) {
            $query->whereIn('category_id', $categoryIds);
        }

        $products = $query->with([
            'category',
            'primaryImage',
            'productSuppliers' => function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId)->with('prices');
            }
        ])->get();

        $allItems = [];

        foreach ($products as $product) {
            $catName = $product->category?->name ?? 'بدون دسته‌بندی';

            $productSupplier = $product->productSuppliers->first();

            $prices = [];
            if ($productSupplier && $productSupplier->prices) {
                foreach ($productSupplier->prices as $p) {
                    $key = $p->price_type instanceof \BackedEnum ? $p->price_type->value : $p->price_type;
                    $prices[$key] = $p->price;
                }
            }

            $allItems[] = [
                'id'             => $product->id,
                'title'          => $product->title,
                'sku'            => $product->sku,
                'image'          => $product->primaryImage?->file_path,
                'brand'          => $product->brand,
                'category_name'  => $catName,
                'category_order' => $product->category?->sort_order ?? 0,
                'prices'         => $prices,
            ];
        }

        // 1. اعمال مرتب‌سازی به صورت یکپارچه روی تمام محصولات
        $sort1    = $this->settings['sort_level1'] ?? null;
        $sort1Dir = $this->settings['sort_level1_dir'] ?? 'asc';
        $sort2    = $this->settings['sort_level2'] ?? null;
        $sort2Dir = $this->settings['sort_level2_dir'] ?? 'asc';

        if ($sort1 || $sort2) {
            usort($allItems, function ($a, $b) use ($sort1, $sort1Dir, $sort2, $sort2Dir) {
                $cmp1 = $this->compareItems($a, $b, $sort1, $sort1Dir);
                if ($cmp1 !== 0) return $cmp1;

                return $this->compareItems($a, $b, $sort2, $sort2Dir);
            });
        }

        // 2. گروه‌بندی پس از مرتب‌سازی
        $grouped = [];

        if ($this->separateCategories) {
            // اگر تنظیم شده که دسته‌بندی‌ها جدا باشند، بعد از مرتب‌سازی آن‌ها را گروه‌بندی می‌کنیم
            foreach ($allItems as $item) {
                $grouped[$item['category_name']][] = $item;
            }
        } else {
            // اگر جدا کردن دسته‌بندی‌ها تیک نخورده باشد، کل محصولات مرتب شده را در یک گروه فرضی می‌ریزیم
            // تا فایل بلید بدون به هم زدن نظم سراسری، آن‌ها را پشت سر هم نمایش دهد
            $grouped['all'] = $allItems;
        }

        $this->groupedProducts = $grouped;
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
            if ($result == 0) return 0;
        }

        return $dir === 'desc' ? -$result : $result;
    }

    private function getSortValue($item, $sortKey)
    {
        if (str_starts_with($sortKey, 'price_')) {
            $type = str_replace('price_', '', $sortKey);
            return $item['prices'][$type] ?? 0;
        }

        $map = [
            'product_name'   => 'title',
            'category'       => 'category_name',
            'category_order' => 'category_order',
            'sku'            => 'sku',
            'brand'          => 'brand',
        ];

        $key = $map[$sortKey] ?? $sortKey;
        return $item[$key] ?? '';
    }

    // این متد برای جلوگیری از خطای wire:click روی دکمه PDF اضافه شد
    public function downloadPdf()
    {
        $supplierName = Auth::user()->supplier->title ?? Auth::user()->name;
        $fileName = 'list-' . jdate(now())->format('Y-m-d') . '.pdf';

        // مسیر ذخیره موقت فایل
        $filePath = storage_path('app/public/' . $fileName);

        \Spatie\LaravelPdf\Facades\Pdf::view('pdf.supplier-catalog', [
            'supplierName'       => $supplierName,
            'groupedProducts'    => $this->groupedProducts,
            'selectedPriceTypes' => $this->selectedPriceTypes,
            'priceLabels'        => $this->priceLabels,
            'settings'           => $this->settings,
            'template'           => $this->template,
            'separateCategories' => $this->separateCategories,
            'showCoverPage'      => $this->showCoverPage,
            'supplierLogo'=>Auth::user()->supplier->logo_path,
            'supplier'=>Auth::user()->supplier,
        ])
            ->withBrowsershot(function ($browsershot) {
                // استفاده از دابل بک‌اسلش برای مسیر ویندوز
                $browsershot->setChromePath('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe');

                // دور زدن محدودیت‌های امنیتی کروم برای لود کردن فونت‌ها و تصاویر با مسیر public_path
                $browsershot->setOption('args', ['--disable-web-security', '--no-sandbox']);
            })
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->save($filePath); // ذخیره فایل

        // دانلود فایل و سپس حذف خودکار آن از سرور
        return response()->download($filePath)->deleteFileAfterSend(true);
    }
    public function downloadPdf2()
    {
        $supplierName = Auth::user()->supplier->title ?? Auth::user()->name;
        $fileName = 'catalog-' . jdate(now())->format('Y-m-d') . '.pdf';

        // مسیر ذخیره موقت فایل
        $filePath = storage_path('app/public/' . $fileName);

        \Spatie\LaravelPdf\Facades\Pdf::view('pdf.supplier-catalog2', [
            'supplierName'       => $supplierName,
            'groupedProducts'    => $this->groupedProducts,
            'selectedPriceTypes' => $this->selectedPriceTypes,
            'priceLabels'        => $this->priceLabels,
            'settings'           => $this->settings,
            'template'           => $this->template,
            'separateCategories' => $this->separateCategories,
            'showCoverPage'      => $this->showCoverPage,
            'supplierLogo'=>Auth::user()->supplier->logo_path,
            'supplier'=>Auth::user()->supplier,
        ])
            ->withBrowsershot(function ($browsershot) {
                // استفاده از دابل بک‌اسلش برای مسیر ویندوز
                $browsershot->setChromePath('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe');

                // دور زدن محدودیت‌های امنیتی کروم برای لود کردن فونت‌ها و تصاویر با مسیر public_path
                $browsershot->setOption('args', ['--disable-web-security', '--no-sandbox']);
            })
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->save($filePath); // ذخیره فایل

        // دانلود فایل و سپس حذف خودکار آن از سرور
        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function render()
    {
        return view('livewire.supplier.catalog.preview')
            ->layout('components.layouts.blank');
    }
}
