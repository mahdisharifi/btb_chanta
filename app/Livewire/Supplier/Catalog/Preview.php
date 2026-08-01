<?php

namespace App\Livewire\Supplier\Catalog;

use App\Enums\ProductStatus;
use Livewire\Component;
use App\Models\CatalogTemplate;
use App\Models\Product;
use App\Enums\PriceType;
use Illuminate\Support\Facades\Auth;
use PDF;

class Preview extends Component
{
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

    public function mount($catalog)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('super-admin');

        if ($isAdmin) {
            $this->template = CatalogTemplate::findOrFail($catalog);

            if ($this->template->supplier_id) {
                $this->supplier = \App\Models\Supplier::find($this->template->supplier_id);
            }

            if (!$this->supplier) {
                $this->supplier = \App\Models\Supplier::where('slug', 'admin-catalog')->first();
            }

            if (!$this->supplier) {
                $this->supplier = new \App\Models\Supplier([
                    'title' => 'کاتالوگ مرکزی',
                    'slug' => 'admin-catalog'
                ]);
            }
        } else {
            $this->supplier = $user->supplier;
            abort_if(! $this->supplier, 403, 'شما پروفایل تامین‌کننده ندارید.');

            $this->template = CatalogTemplate::where('id', $catalog)
                ->where('supplier_id', $this->supplier->id)
                ->firstOrFail();
        }

        $this->supplierName = $this->supplier->title ?? 'مدیریت مرکزی';
        $this->supplierLogoUrl = ($this->supplier->logo_path && file_exists(storage_path('app/public/' . $this->supplier->logo_path)))
            ? asset('storage/' . $this->supplier->logo_path)
            : null;

        $this->settings = $this->template->settings ?? [];

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

        $this->loadCatalogData($isAdmin ? null : $this->supplier->id);
    }

// داخل کلاس Preview.php

    public function loadCatalogData()
    {
        $settings = $this->template->settings ?? [];
        $config = $this->template->config ?? [];

        // ۱. استخراج کانفیگ‌های یکپارچه
        $supplierIds = $config['supplier_ids'] ?? ($this->supplier ? [$this->supplier->id] : []);
        $categoryIds = $settings['categories'] ?? [];
        $selectedPriceTypes = $settings['price_types'] ?? PriceType::values();

        // ۲. کوئری اصلی
        $query = Product::query()
            ->where('status', ProductStatus::ACTIVE->value);

        // ۳. فیلتر دسته‌بندی
        if (!empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        // ۴. فیلتر تامین‌کننده و قیمت (همگام با سرویس)
        $query->whereHas('productSuppliers', function ($q) use ($supplierIds, $selectedPriceTypes) {
            $q->where('is_active', true)
                ->whereIn('supplier_id', $supplierIds)
                ->whereHas('prices', function ($p) use ($selectedPriceTypes) {
                    $p->activeAndValid()->whereIn('price_type', $selectedPriceTypes);
                });
        });

        // ۵. دریافت داده‌ها با Eager Loading یکسان
        $products = $query->with([
            'productSuppliers' => function ($q) use ($supplierIds, $selectedPriceTypes) {
                $q->where('is_active', true)
                    ->whereIn('supplier_id', $supplierIds)
                    ->with(['prices' => function ($p) use ($selectedPriceTypes) {
                        $p->activeAndValid()->whereIn('price_type', $selectedPriceTypes);
                    }]);
            }
        ])->get();

        // ۶. تبدیل داده‌ها برای نمایش (Transformation)
        $this->groupedProducts = $products->map(function ($product) use ($selectedPriceTypes) {
            $supplier = $product->productSuppliers->first();

            // استخراج قیمت‌ها بر اساس نوع
            $prices = [];
            foreach ($selectedPriceTypes as $type) {
                $priceModel = $supplier->prices->firstWhere('price_type', $type);
                $prices[$type] = $priceModel ? $priceModel->price : null;
            }

            return [
                'id'            => $product->id,
                'title'         => $product->title,
                'category_name' => $product->category?->name ?? 'بدون دسته‌بندی',
                'prices'        => $prices,
                // سایر فیلدها...
            ];
        })->groupBy('category_name');
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

    public function downloadPdf()
    {
        return $this->generatePdfResponse('pdf.supplier-catalog', 'list-');
    }

    public function downloadPdf2()
    {
        return $this->generatePdfResponse('pdf.supplier-catalog2', 'catalog-');
    }

    private function generatePdfResponse($view, $prefix)
    {
        $fileName = $prefix . jdate(now())->format('Y-m-d') . '.pdf';

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
            'supplier'           => $this->supplier,
        ];

        $pdf = PDF::loadView($view, $data, [], [
            'format' => 'A4',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 10,
            'margin_bottom' => 10,
            'mode'          => 'utf-8',
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    public function render()
    {
        return view('livewire.supplier.catalog.preview')
            ->layout('components.layouts.blank');
    }
}
