<?php

namespace App\Livewire\Supplier;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductSupplier;
use App\Models\ProductPrice;
use App\Enums\ProductStatus;
use App\Enums\PriceType;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

class ProductSelection extends Component
{
    use WithPagination;

    // متغیرهای فیلتر و جستجو
    public $search = '';
    public $categoryId = '';
    public $pricingStatus = 'all'; // all, priced, unpriced
    public $sort = 'latest'; // latest, popular, title_asc
    public $marketOpportunity = ''; // '', 'no_supplier'
    public $viewMode = 'grid'; // grid, list

    // متغیرهای انتخاب گروهی
    public $selectedProducts = [];
    public $selectAll = false;

    // متغیرهای مدیریت مودال‌ها
    public $showQuickPriceModal = false;
    public $showQuickViewModal = false;

    // دیتای مودال قیمت‌گذاری سریع
    public $quickProductId = null;
    public $quickPrice = null;
    public $quickStock = null;

    // دیتای مودال مشاهده سریع
    public $viewingProductId = null;

    public bool $showQuickEdit = false; // کنترل نمایش Slide-over
    public string $quickSearch = ''; // جستجو در حالت سریع

// می‌توانید مقادیر را در یک آرایه بایند کنید تا با هر تغییر بلافاصله یا با زدن یک دکمه ذخیره شوند
    public array $quickPrices = [];
    public array $quickStocks = [];



    public function updated($property)
    {
        // در صورت تغییر فیلترها، به صفحه اول برگردیم
        if (in_array($property, ['search', 'categoryId', 'pricingStatus', 'sort', 'marketOpportunity'])) {
            $this->resetPage();
        }

        // مدیریت انتخاب همه محصولات در حالت لیست
        if ($property === 'selectAll') {
            if ($this->selectAll) {
                $this->selectedProducts = $this->getProductsQuery()->pluck('id')->map(fn($id) => (string) $id)->toArray();
            } else {
                $this->selectedProducts = [];
            }
        }
    }

    protected function getProductsQuery()
    {
        $supplier = Auth::user()->supplier;

        $query = Product::query()
            ->with(['category', 'galleries' => function($q) {
                $q->orderBy('sort_order');
            }])
            ->withCount('suppliers as competitors_count') // محاسبه تعداد رقبا
            ->where('status', ProductStatus::ACTIVE->value);

        // اعمال فیلتر جستجو
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        // اعمال فیلتر دسته‌بندی
        if (!empty($this->categoryId)) {
            $query->where('category_id', $this->categoryId);
        }

        // اعمال فیلتر وضعیت فروش
        if ($this->pricingStatus === 'priced') {
            $query->whereHas('suppliers', function($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id);
            });
        } elseif ($this->pricingStatus === 'unpriced') {
            $query->whereDoesntHave('suppliers', function($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id);
            });
        }

        // اعمال فیلتر فرصت بازار (کالاهای بدون تامین کننده)
        if ($this->marketOpportunity === 'no_supplier') {
            $query->having('competitors_count', 0);
        }

        // مرتب‌سازی
        switch ($this->sort) {
            case 'title_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'popular':
                $query->orderBy('competitors_count', 'desc'); // بیشترین تامین‌کننده (پرفروش‌ترین کالاها در بازار)
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }

        return $query;
    }

    public function openQuickPrice($productId)
    {
        $this->quickProductId = $productId;
        $this->quickPrice = null;
        $this->quickStock = null;
        $this->showQuickPriceModal = true;
    }

    public function saveQuickPrice()
    {
        $this->validate([
            'quickPrice' => 'required|numeric|min:100',
            'quickStock' => 'required|integer|min:0',
        ], [
            'quickPrice.required' => 'وارد کردن قیمت الزامی است.',
            'quickStock.required' => 'وارد کردن موجودی الزامی است.',
        ]);

        $supplier = Auth::user()->supplier;

        // ایجاد یا آپدیت اتصال تامین‌کننده به محصول
        $productSupplier = ProductSupplier::updateOrCreate(
            [
                'product_id' => $this->quickProductId,
                'supplier_id' => $supplier->id,
            ],
            [
                'stock_quantity' => $this->quickStock,
                'status' => 'active',
            ]
        );

        // ایجاد یا آپدیت قیمت (با استفاده از اینام صحیح)
        ProductPrice::updateOrCreate(
            [
                'product_supplier_id' => $productSupplier->id,
                'price_type' => PriceType::custom->value,
            ],
            [
                'price' => $this->quickPrice,
            ]
        );

        $this->showQuickPriceModal = false;
        $this->reset(['quickProductId', 'quickPrice', 'quickStock']);

        $this->dispatch('notify', message: 'محصول با موفقیت به لیست فروش شما اضافه شد.');
    }

    public function openQuickView($productId)
    {
        $this->viewingProductId = $productId;
        $this->showQuickViewModal = true;
    }

    public function exportCsv()
    {
        $products = $this->getProductsQuery()->take(500)->get(); // محدودیت برای جلوگیری از تایم اوت

        $csvData = "\xEF\xBB\xBF"; // افزودن BOM برای پشتیبانی صحیح از زبان فارسی در اکسل
        $csvData .= "شناسه,نام محصول,کد SKU,دسته بندی,تعداد رقبا\n";

        foreach ($products as $product) {
            $categoryName = $product->category->name ?? 'بدون دسته';
            $csvData .= "{$product->id},\"{$product->title}\",{$product->sku},\"{$categoryName}\",{$product->competitors_count}\n";
        }

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, 'products_export_'.date('Ymd_His').'.csv');
    }

    public function render()
    {
        $supplier = Auth::user()->supplier;

        // دریافت ID محصولاتی که این تامین کننده قبلا قیمت‌گذاری کرده است
        $pricedProductIds = $supplier->products()->pluck('products.id')->toArray();
        $categories = Category::all();

        $products = $this->getProductsQuery()->paginate(12);

        // کوئری گرفتن برای اطلاعات داخل مودال‌ها در صورت باز بودن
        $quickProduct = $this->quickProductId ? Product::with('galleries')->find($this->quickProductId) : null;
        $viewingProduct = $this->viewingProductId ? Product::with(['category', 'galleries'])->find($this->viewingProductId) : null;

        return view('livewire.supplier.product-selection', [
            'products' => $products,
            'categories' => $categories,
            'pricedProductIds' => $pricedProductIds,
            'quickProduct' => $quickProduct,
            'viewingProduct' => $viewingProduct,
        ])->layout('components.layouts.supplier');
    }
}
