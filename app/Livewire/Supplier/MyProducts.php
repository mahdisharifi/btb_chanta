<?php

namespace App\Livewire\Supplier;

use App\Models\ProductSupplier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class MyProducts extends Component
{
    use WithPagination;

    public $search = '';

    #[Locked]
    public ?int $pendingDeleteOfferId = null;

    #[Locked]
    public ?int $pendingDeleteProductId = null;

    #[Locked]
    public ?string $pendingDeleteProductTitle = null;

    #[Locked]
    public bool $pendingDeleteHasPrices = false;

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function archiveProduct(int $productId): void
    {
        $supplier = Auth::user()->currentSupplier();
        $offer = ProductSupplier::query()
            ->where('supplier_id', $supplier->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        if (!$offer->is_active) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'این کالا قبلاً بایگانی شده است.']);
            return;
        }

        $offer->update(['is_active' => false]);
        app(\App\Services\CatalogSnapshotRevisionService::class)->invalidateSupplier((int) $supplier->id);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'عرضهٔ این کالا بایگانی شد؛ محصول اصلی در کاتالوگ باقی می‌ماند.']);
    }

    public function requestDeleteProduct(int $productId): void
    {
        $supplier = Auth::user()->currentSupplier();
        $offer = ProductSupplier::query()
            ->with('product')
            ->where('supplier_id', $supplier->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        $this->pendingDeleteOfferId = (int) $offer->id;
        $this->pendingDeleteProductId = (int) $offer->product_id;
        $this->pendingDeleteProductTitle = $offer->product?->title_fa
            ?: $offer->product?->title
            ?: 'این کالا';
        $this->pendingDeleteHasPrices = $this->offerHasPrices($offer);
    }

    public function cancelDelete(): void
    {
        $this->pendingDeleteOfferId = null;
        $this->pendingDeleteProductId = null;
        $this->pendingDeleteProductTitle = null;
        $this->pendingDeleteHasPrices = false;
    }

    public function confirmDeleteProduct(): void
    {
        if (!$this->pendingDeleteOfferId || !$this->pendingDeleteProductId) {
            return;
        }

        $supplier = Auth::user()->currentSupplier();

        try {
            $priceWarningRequired = DB::transaction(function () use ($supplier): bool {
                $offer = ProductSupplier::query()
                    ->whereKey($this->pendingDeleteOfferId)
                    ->where('supplier_id', $supplier->id)
                    ->where('product_id', $this->pendingDeleteProductId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // اگر بعد از بازشدن مودال قیمت ثبت شده باشد، ابتدا هشدار قیمت را نمایش بده.
                if ($this->offerHasPrices($offer) && !$this->pendingDeleteHasPrices) {
                    return true;
                }

                $offer->forceDelete();

                return false;
            }, attempts: 3);
        } catch (ModelNotFoundException) {
            $this->cancelDelete();
            $this->dispatch('notify', ['type' => 'error', 'message' => 'عرضهٔ این کالا دیگر در فهرست شما نیست.']);
            return;
        }

        if ($priceWarningRequired) {
            $this->pendingDeleteHasPrices = true;
            return;
        }

        app(\App\Services\CatalogSnapshotRevisionService::class)->invalidateSupplier((int) $supplier->id);
        $this->cancelDelete();
        $this->resetPage();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'عرضهٔ کالا و اطلاعات قیمت آن از فهرست شما حذف شد؛ محصول اصلی باقی می‌ماند.']);
    }

    private function offerHasPrices(ProductSupplier $offer): bool
    {
        return $offer->prices()->exists()
            || (float) $offer->foreign_price_amount > 0
            || (int) $offer->cost_price > 0;
    }

    public function render()
    {
        $supplier = Auth::user()->currentSupplier();

        $products = $supplier->products()
            ->when($this->search, function ($query) {
                $query->where('title_fa', 'like', '%' . $this->search . '%')
                    ->orWhere('title_en', 'like', '%' . $this->search . '%');
            })
            // فیلدهای price و stock_quantity از اینجا حذف شدند
            ->withPivot(['id', 'status', 'is_active', 'foreign_price_amount', 'cost_price'])
            ->paginate(10);

        $offerIds = $products->getCollection()
            ->map(fn ($product) => (int) $product->pivot->id)
            ->filter()
            ->values();

        $pricedOfferIds = DB::table('product_prices')
            ->whereIn('product_supplier_id', $offerIds)
            ->distinct()
            ->pluck('product_supplier_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return view('livewire.supplier.my-products', [
            'products' => $products,
            'pricedOfferIds' => $pricedOfferIds,
        ])->layout('components.layouts.supplier');
    }
}
