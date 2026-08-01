<?php

namespace App\Livewire\Admin\Product;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Enums\ProductStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('مدیریت محصولات')]
class extends Component
{
    use WithPagination;

    // ─── فیلترها ─────────────────────────────────────────────
    public string $search = '';
    public string $statusFilter = '';
    public ?int $categoryFilter = null;
    public ?int $supplierFilter = null;

    // ─── وضعیت‌ها ────────────────────────────────────────────
    public array $statuses = [];

    public function mount(): void
    {
        $this->statuses = ProductStatus::cases();
    }

    // ─── کوئری اصلی ──────────────────────────────────────────
    public function products()
    {
        return Product::query()
            ->with(['category', 'supplier', 'basePrice'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('sku', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->categoryFilter, function ($query) {
                $query->where('category_id', $this->categoryFilter);
            })
            ->when($this->supplierFilter, function ($query) {
                $query->whereHas('suppliers', function ($q) {
                    $q->where('supplier_id', $this->supplierFilter);
                });
            })
            ->latest('id')
            ->paginate(15);
    }

    // ─── حذف محصول ───────────────────────────────────────────
    public function delete(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $this->authorize('delete', $product);

        $product->delete();
        session()->flash('message', 'محصول با موفقیت حذف شد.');
    }

    // ─── تغییر وضعیت سریع ───────────────────────────────────
    public function changeStatus(int $productId, string $newStatus): void
    {
        $product = Product::findOrFail($productId);
        $this->authorize('update', $product);

        $status = ProductStatus::from($newStatus);

        if (!$product->status->canBeChangedTo($status)) {
            session()->flash('error', 'تغییر وضعیت به ' . $status->label() . ' مجاز نیست.');
            return;
        }

        $product->update(['status' => $status]);
        session()->flash('message', 'وضعیت محصول با موفقیت به ' . $status->label() . ' تغییر کرد.');
    }

    // ─── ریست فیلترها ───────────────────────────────────────
    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'categoryFilter', 'supplierFilter']);
        $this->resetPage();
    }

    // ─── آپدیت خودکار صفحه ──────────────────────────────────
    public function updated($property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'categoryFilter', 'supplierFilter'])) {
            $this->resetPage();
        }
    }

    // ─── داده‌های کمکی برای ویو ─────────────────────────────
    public function with(): array
    {
        return [
            'products'   => $this->products(),
            'categories' => Category::orderBy('name')->get(),
            'suppliers'  => Supplier::orderBy('name')->get(),
        ];
    }
};
