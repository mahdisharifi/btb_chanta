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

    #[Computed]
    public function transfers()
    {
        if (!$this->currentSupplier->isBranch()) {
            return SupplierBranchProductTransfer::query()->whereRaw('1 = 0')->paginate(20);
        }

        return SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $this->currentSupplier->id)
            ->with([
                'sourceProduct:id,title,sku,category_id',
                'sourceProduct.category:id,name',
                'destinationProduct:id,title,slug',
            ])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
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
                'message' => $changed ? 'محصول برای این شعبه رد شد.' : 'درخواست قبلاً پذیرفته شده و قابل رد نیست.',
            ]);
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
