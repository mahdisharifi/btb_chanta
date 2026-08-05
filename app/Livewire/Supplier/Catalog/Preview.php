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

    public function mount($catalog)
    {
        $user = Auth::user();
        $this->isAdmin = $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('super-admin');

        if ($this->isAdmin) {
            $this->template = CatalogTemplate::findOrFail($catalog);

            // اولویت اول: supplier_id از template
            if ($this->template->supplier_id) {
                $this->supplier = \App\Models\Supplier::find($this->template->supplier_id);
            }

            // اولویت دوم: supplier کاربر ادمین
            if (!$this->supplier && $user->supplier) {
                $this->supplier = $user->supplier;
            }

            // اولویت سوم: جستجوی supplier با user_id یا type=admin
            if (!$this->supplier) {
                $this->supplier = \App\Models\Supplier::where('user_id', $user->id)
                    ->orWhere('type', 'admin')
                    ->first();
            }

            // اگر هنوز supplier نیست، خطا برگردان
            if (!$this->supplier) {
                abort(403, 'شما پروفایل تامین‌کننده ندارید. لطفاً ابتدا اطلاعات فروشگاه را تکمیل کنید.');
            }
        } else {
            $this->supplier = $user->supplier;
            abort_if(! $this->supplier, 403, 'شما پروفایل تامین‌کننده ندارید.');

            $this->template = CatalogTemplate::where('id', $catalog)
                ->where('supplier_id', $this->supplier->id)
                ->firstOrFail();
        }

        $this->supplierName = $this->supplier->company_name 
            ?? $this->supplier->brand_name 
            ?? 'مدیریت مرکزی';
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

        $this->loadCatalogData($this->isAdmin ? null : $this->supplier->id);
    }

    private function loadCatalogData($supplierId = null)
    {
        $categoryIds = $this->settings['categories']
            ?? $this->template->selected_categories
            ?? 'all';

        $selectedSupplierIds = $this->settings['suppliers']
            ?? ($this->template->config['supplier_ids'] ?? [])
            ?? ($supplierId ? [$supplierId] : []);

        $query = Product::query()
            ->where('status', ProductStatus::ACTIVE->value);

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

        if ($categoryIds !== 'all' && is_array($categoryIds) && count($categoryIds) > 0) {
            $query->whereIn('category_id', $categoryIds);
        }

        $products = $query->with([
            'category',
            'primaryImage',
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

        $allItems = [];

        foreach ($products as $product) {
            $catName = $product->category?->name ?? 'بدون دسته‌بندی';

            $prices = [];
            if ($this->isAdmin && !empty($selectedSupplierIds) && is_array($selectedSupplierIds)) {
                // ادمین: کمترین قیمت هر نوع از بین همه تامین‌کنندگان انتخابی
                foreach ($product->productSuppliers as $ps) {
                    foreach ($ps->prices as $p) {
                        $key = $p->price_type instanceof \BackedEnum ? $p->price_type->value : $p->price_type;
                        if (!isset($prices[$key]) || $p->price < $prices[$key]) {
                            $prices[$key] = $p->price;
                        }
                    }
                }
            } else {
                // تامین‌کننده: فقط تامین‌کننده جاری
                $prices = [];
                if ($this->isAdmin) {
                    foreach ($product->productSuppliers as $ps) {
                        foreach ($ps->prices as $p) {
                            $key = $p->price_type instanceof \BackedEnum ? $p->price_type->value : $p->price_type;
                            if (!isset($prices[$key]) || $p->price < $prices[$key]) {
                                $prices[$key] = $p->price;
                            }
                        }
                    }
                } else {
                    $productSupplier = $product->productSuppliers->first();
                    if ($productSupplier && $productSupplier->prices) {
                        foreach ($productSupplier->prices as $p) {
                            $key = $p->price_type instanceof \BackedEnum ? $p->price_type->value : $p->price_type;
                            $prices[$key] = $p->price;
                        }
                    }
                }
            }

            if (empty($prices)) {
                continue;
            }

            $allItems[] = [
                'id'               => $product->id,
                'title'            => $product->title,
                'sku'              => $product->sku,
                'image'            => $product->primaryImage?->getVersionPath('sm') ?? $product->primaryImage?->file_path,
                'brand'            => $product->brand,
                'category_name'    => $catName,
                'category_order'   => $product->category?->sort_order ?? 0,
                'prices'           => $prices,
                'created_at'       => $product->created_at?->timestamp ?? 0,
                'price_updated_at' => $product->productSuppliers->max('price_updated_at')?->timestamp ?? 0,
            ];

        }

        $sorts = $this->settings['sort'] ?? [];
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
            'name'           => 'title',
            'created_at'     => 'created_at',
            'price_updated_at' => 'price_updated_at',
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
        $pdf = PDF::loadView('pdf.supplier-catalog2', $data, [], [
            'format'        => 'A4',
            'margin_left'   => 6,
            'margin_right'  => 6,
            'margin_top'    => 6,
            'margin_bottom' => 6,
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
