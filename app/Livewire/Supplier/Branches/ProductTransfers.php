<?php

namespace App\Livewire\Supplier\Branches;

use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use App\Models\ProductSupplier;
use App\Models\Category;
use App\Services\SupplierBranchCatalogService;
use App\Services\SupplierBranchTransferService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('components.layouts.supplier')]
class ProductTransfers extends Component
{
    use WithPagination;

    public int $branchId = 0;
    public string $tab = 'products';
    public string $search = '';
    public string $categoryId = '';
    public string $status = '';
    public array $selected = [];
    public string $percentage = '';
    #[Locked]
    public array $preview = [];
    #[Locked]
    public array $snapshots = [];
    #[Locked]
    public ?float $previewPercentage = null;

    #[Computed]
    public function primary(): Supplier
    {
        return auth()->user()->supplier()->firstOrFail();
    }

    #[Computed]
    public function branches()
    {
        return $this->primary->branches()->where('is_active', true)->get();
    }

    public function updated($property): void
    {
        if (in_array($property, ['branchId', 'tab', 'search', 'categoryId', 'status'], true)) {
            $this->resetPage();
            $this->selected = [];
            $this->closePreview();
            unset($this->currentSupplier, $this->offers, $this->transfers);
            if ($property === 'branchId' && $this->branchId) {
                $this->categoryId = '';
                app(SupplierBranchTransferService::class)->seedPendingTransfers($this->primary, $this->currentSupplier);
                unset($this->categories);
            }
        }
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->whereHas('products.productSuppliers', fn ($query) => $query
            ->whereIn('supplier_id', [$this->branchId, $this->primary->id]))->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function offers()
    {
        $branch = $this->currentSupplier;
        return ProductSupplier::query()->where('supplier_id', $branch->id)
            ->whereHas('product', function ($query): void {
                $query->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('title', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%')))
                    ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId));
            })
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->with('product.category')->orderByDesc('id')->paginate(20);
    }

    public function selectPage(): void
    {
        $this->selected = $this->offers->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function previewSync(?int $offerId = null): void
    {
        $this->validate(['percentage' => ['nullable', 'numeric', 'min:-100', 'max:10000']]);
        $this->closePreview();
        $ids = $offerId !== null ? [$offerId] : array_values(array_unique($this->selected));
        if (!$ids || count($ids) > 20) {
            $this->addError('preview', 'از یک تا ۲۰ محصول را انتخاب کنید.');
            return;
        }
        $branch = $this->currentSupplier;
        $offers = ProductSupplier::query()->where('supplier_id', $branch->id)->whereIn('id', $ids)->with('product')->get();
        abort_unless($offers->count() === count($ids), 403);
        $service = app(SupplierBranchCatalogService::class);
        $this->previewPercentage = $this->percentage === '' ? null : (float) $this->percentage;
        foreach ($offers as $offer) {
            $comparison = $service->comparison($offer, $this->primary);
            $this->snapshots[$offer->id] = $comparison['fingerprint'];
            foreach ($comparison['rows'] as &$row) {
                $row['after'] = $row['base'] === null ? null : max(0, (int) round($row['base']
                    * (1 + ($this->previewPercentage ?? $comparison['percentage']) / 100), 0, PHP_ROUND_HALF_UP));
            }
            unset($row);
            $this->preview[] = ['id' => $offer->id, 'title' => $offer->product->title] + $comparison;
        }
    }

    public function applySync(SupplierBranchCatalogService $service): void
    {
        $service->synchronize($this->primary, $this->branchId, $this->snapshots, $this->previewPercentage);
        $this->closePreview();
        $this->selected = [];
        unset($this->offers);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'قیمت‌های شعبه همگام شد.']);
    }

    public function closePreview(): void
    {
        $this->preview = [];
        $this->snapshots = [];
        $this->previewPercentage = null;
        $this->resetValidation();
    }

    public function removeOffer(int $offerId, SupplierBranchCatalogService $service): void
    {
        $service->remove($this->primary, $this->branchId, $offerId);
        $this->closePreview();
        $this->selected = [];
        unset($this->offers, $this->transfers);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'فقط لیست فروش و قیمت‌های این شعبه حذف شد؛ اطلاعات محصول محفوظ است.']);
    }

    #[Computed]
    public function currentSupplier(): Supplier
    {
        return app(SupplierBranchCatalogService::class)->branch($this->primary, $this->branchId);
    }

    public function mount(): void
    {
        $user = auth()->user();
        $branch = $user->currentSupplier();
        $this->branchId = $branch->isBranch() ? (int) $branch->id : (int) ($this->branches->first()?->id ?? 0);

        if ($this->branchId) {
            app(SupplierBranchTransferService::class)->seedPendingTransfers(
                $user->supplier()->firstOrFail(),
                $this->currentSupplier,
            );
        }
    }

    #[Computed]
    public function transfers()
    {
        if (!$this->currentSupplier->isBranch()) {
            return SupplierBranchProductTransfer::query()->whereRaw('1 = 0')->paginate(20);
        }

        return SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $this->currentSupplier->id)
            ->where('status', SupplierBranchProductTransfer::PENDING)
            ->whereHas('sourceProduct', function ($query): void {
                $query->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('title', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%')))
                    ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId));
            })
            ->with([
                'sourceProduct:id,title,sku,category_id',
                'sourceProduct.category:id,name',
            ])
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function accept(int $transferId, SupplierBranchTransferService $transferService): void
    {
        try {
            $user = auth()->user();
            $product = $transferService->accept(
                $user->supplier()->firstOrFail(),
                $this->currentSupplier,
                $transferId
            );

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'محصول به این شعبه اضافه شد. مشخصات، قیمت و تنظیمات شعبه را تکمیل کنید.',
            ]);
            $this->resetPage();
            unset($this->transfers);
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'پذیرش محصول انجام نشد. صفحه را تازه‌سازی و دوباره بررسی کنید.']);
        }
    }

    public function reject(int $transferId, SupplierBranchTransferService $transferService): void
    {
        try {
            $user = auth()->user();
            $changed = $transferService->reject(
                $user->supplier()->firstOrFail(),
                $this->currentSupplier,
                $transferId
            );

            $this->dispatch('notify', [
                'type' => $changed ? 'success' : 'error',
                'message' => $changed ? 'محصول برای این شعبه رد شد.' : 'این درخواست قبلاً تعیین تکلیف شده و قابل رد نیست.',
            ]);
            $this->resetPage();
            unset($this->transfers);
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'رد محصول انجام نشد. صفحه را تازه‌سازی و دوباره بررسی کنید.']);
        }
    }

    public function render()
    {
        return view('livewire.supplier.branches.product-transfers');
    }

    public function archiveOffer(int $offerId): void
    {
        $branch = $this->currentSupplier;
        ProductSupplier::query()->where('supplier_id', $branch->id)->whereKey($offerId)
            ->firstOrFail()->update(['is_active' => false]);
        app(\App\Services\CatalogSnapshotRevisionService::class)->invalidateSupplier((int) $branch->id);
        $this->closePreview();
        unset($this->offers);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'فروش محصول در این شعبه متوقف شد.']);
    }
}
