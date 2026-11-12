<?php

namespace App\Livewire\Supplier;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductSupplier;
use App\Models\ProductPrice;
use App\Enums\ProductStatus;
use App\Enums\PriceType;
use App\Services\SupplierSalesListService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    public $draftBranchId = '';

    protected function draftSuppliers()
    {
        $user = Auth::user();
        $primary = $user->supplier()->firstOrFail();

        return \App\Models\Supplier::query()
            ->where('user_id', $user->id)
            ->where(fn ($query) => $query->whereKey($primary->id)->orWhere('parent_supplier_id', $primary->id));
    }

    protected function draftProductsQuery()
    {
        return Product::query()
            ->with(['supplier', 'category'])
            ->where('status', ProductStatus::DRAFT->value)
            ->whereIn('supplier_id', $this->draftSuppliers()->select('id'))
            ->when($this->draftBranchId !== '', fn ($query) => $query->where('supplier_id', $this->draftBranchId))
            ->when($this->search !== '', fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->latest();
    }

    public function updatedDraftBranchId(): void
    {
        $this->resetPage('draftsPage');
    }

    public function openDraftProduct(int $productId, int $step = 1)
    {
        abort_unless(in_array($step, [1, 3], true), 422);
        $product = $this->draftProductsQuery()->whereKey($productId)->firstOrFail();
        $owner = $this->draftSuppliers()->whereKey($product->supplier_id)->firstOrFail();
        abort_unless($owner->is_active, 403);
        session()->put('supplier.active_branch_id', $owner->id);

        return redirect()->route('supplier.products.edit', ['product' => $product->id, 'step' => $step]);
    }

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
            $this->resetPage('draftsPage');
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
        $supplier = Auth::user()->currentSupplier();

        $query = Product::query()
            ->canonical()
            ->with(['category', 'galleries' => function($q) {
                $q->orderBy('sort_order');
            }])
            ->addSelect([
                'competitors_count' => DB::table('product_supplier as ps')
                    ->join('products as family_member', 'family_member.id', '=', 'ps.product_id')
                    ->join('suppliers as offer_supplier', 'offer_supplier.id', '=', 'ps.supplier_id')
                    ->selectRaw('COUNT(DISTINCT COALESCE(offer_supplier.parent_supplier_id, offer_supplier.id))')
                    ->whereColumn('family_member.family_product_id', 'products.family_product_id'),
            ])
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
            $query->whereHas('familyProducts.productSuppliers', function($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id);
            });
        } elseif ($this->pricingStatus === 'unpriced') {
            $query->whereDoesntHave('familyProducts.productSuppliers', function($q) use ($supplier) {
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

    public function saveQuickPrice(SupplierSalesListService $salesList)
    {
        $supplier = Auth::user()->currentSupplier();
        $usesPriceMatrix = $supplier->isBranch() || $supplier->pricing_currency !== 'TOM';
        $this->validate([
            'quickPrice' => $usesPriceMatrix ? 'nullable|numeric|min:0' : 'required|numeric|min:100',
            'quickStock' => 'required|integer|min:0',
        ], [
            'quickPrice.required' => 'وارد کردن قیمت الزامی است.',
            'quickStock.required' => 'وارد کردن موجودی الزامی است.',
        ]);

        $product = Product::query()
            ->canonical()
            ->whereKey($this->quickProductId)
            ->where('status', ProductStatus::ACTIVE->value)
            ->firstOrFail();

        if ($supplier->isBranch()) {
            $offer = $salesList->add($product, $supplier);
            $offer->update(['stock_quantity' => $this->quickStock]);

            return redirect()->route('supplier.products.edit-price', ['product' => $product->id]);
        }

        if ($supplier->pricing_currency !== 'TOM') {
            session()->flash('info', 'برای ثبت قیمت با ارز پایه، از ماتریس قیمت استفاده کنید تا قیمت تومانی و تیراژها درست محاسبه شوند.');

            return redirect()->route('supplier.products.edit-price', ['product' => $product->id]);
        }

        $productSupplier = $this->saveQuickOffer($product, $supplier);
        \App\Jobs\BroadcastSupplierProductPrices::dispatch(
            (int) $productSupplier->supplier_id,
            [(int) $productSupplier->id],
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
        $supplier = Auth::user()->currentSupplier();

        // دریافت ID محصولاتی که این تامین کننده قبلا قیمت‌گذاری کرده است
        $pricedProductIds = Product::query()
            ->whereHas('familyProducts.productSuppliers', fn ($query) => $query->where('supplier_id', $supplier->id))
            ->pluck('family_product_id')
            ->unique()
            ->values()
            ->all();
        $categories = Category::all();

        $products = $this->getProductsQuery()->paginate(12);

        // کوئری گرفتن برای اطلاعات داخل مودال‌ها در صورت باز بودن
        $quickProduct = $this->quickProductId ? Product::canonical()->with('galleries')->find($this->quickProductId) : null;
        $viewingProduct = $this->viewingProductId ? Product::canonical()->with(['category', 'galleries'])->find($this->viewingProductId) : null;

        return view('livewire.supplier.product-selection', [
            'draftProducts' => $this->draftProductsQuery()->paginate(8, ['*'], 'draftsPage'),
            'draftBranches' => $this->draftSuppliers()->get(),
            'products' => $products,
            'categories' => $categories,
            'pricedProductIds' => $pricedProductIds,
            'quickProduct' => $quickProduct,
            'viewingProduct' => $viewingProduct,
        ])->layout('components.layouts.supplier');
    }

    private function saveQuickOffer(Product $product, \App\Models\Supplier $supplier): ProductSupplier
    {
        if ($supplier->isBranch()) {
            return DB::transaction(function () use ($product, $supplier): ProductSupplier {
                $productSupplier = ProductSupplier::query()->firstOrCreate(
                    ['product_id' => $product->id, 'supplier_id' => $supplier->id],
                    ['is_active' => true, 'status' => 'active']
                );

                $this->persistQuickOfferDetails($productSupplier);

                return $productSupplier;
            });
        }

        return DB::transaction(function () use ($product, $supplier): ProductSupplier {
            $productSupplierQuery = ProductSupplier::withTrashed()
                ->where('product_id', $product->id)
                ->where('supplier_id', $supplier->id);

            // A locking read sees the current row under InnoDB repeatable-read,
            // including rows inserted while an earlier request was in flight.
            $productSupplier = (clone $productSupplierQuery)
                ->lockForUpdate()
                ->first();

            if (!$productSupplier) {
                try {
                    $productSupplier = ProductSupplier::create([
                        'product_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'is_active' => false,
                        'status' => 'active',
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
                    // If another request won the unique (product_id, supplier_id)
                    // insert, use a locking current read instead of the transaction's
                    // repeatable-read snapshot. Re-throw unrelated insert failures.
                    $productSupplier = (clone $productSupplierQuery)
                        ->lockForUpdate()
                        ->first();

                    if (!$productSupplier) {
                        throw $exception;
                    }
                }
            }

            if ($productSupplier->trashed()) {
                $productSupplier->forceFill(['is_active' => false]);
                $productSupplier->restore();
            }

            $this->persistQuickOfferDetails($productSupplier);

            return $productSupplier;
        }, attempts: 3);
    }

    private function persistQuickOfferDetails(ProductSupplier $productSupplier): void
    {
        $productSupplier->update([
            'stock_quantity' => $this->quickStock,
            'status' => 'active',
            'is_active' => true,
        ]);

        ProductPrice::updateOrCreate(
            [
                'product_supplier_id' => $productSupplier->id,
                'price_type' => PriceType::custom->value,
            ],
            [
                'price' => $this->quickPrice,
                'is_active' => true,
            ]
        );

        app(\App\Services\ProductPriceRevisionService::class)
            ->increment((int) $productSupplier->product_id, (int) $productSupplier->supplier_id);
    }
}
