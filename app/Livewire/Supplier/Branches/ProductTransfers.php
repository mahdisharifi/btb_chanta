<?php

namespace App\Livewire\Supplier\Branches;

use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use App\Services\SupplierBranchTransferService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('components.layouts.supplier')]
class ProductTransfers extends Component
{
    use WithPagination;

    #[Computed]
    public function currentSupplier(): Supplier
    {
        return auth()->user()->currentSupplier();
    }

    public function mount(): void
    {
        $user = auth()->user();
        $branch = $user->currentSupplier();

        if ($branch->isBranch()) {
            app(SupplierBranchTransferService::class)->seedPendingTransfers(
                $user->supplier()->firstOrFail(),
                $branch,
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
                $user->currentSupplier(),
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
                $user->currentSupplier(),
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
}
