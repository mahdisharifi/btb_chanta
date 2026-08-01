<?php

namespace App\Livewire\Supplier\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.supplier')]
class Index extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $categoryId = '';

    #[Url(history: true)]
    public string $sortBy = 'latest';

    // ریست کردن صفحه‌بندی هنگام تغییر فیلترها
    public function updated($property)
    {
        if (in_array($property, ['search', 'status', 'categoryId', 'sortBy'])) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function supplier()
    {
        return Supplier::where('user_id', Auth::id())->firstOrFail();
    }

    #[Computed]
    public function categories()
    {
        return Category::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function stats()
    {
        $supplierId = $this->supplier->id;

        return [
            'total' => Product::where('supplier_id', $supplierId)->count(),
            'active' => Product::where('supplier_id', $supplierId)->where('status', 'active')->count(),
            'pending' => Product::where('supplier_id', $supplierId)->where('status', 'pending_review')->count(),
            'draft' => Product::where('supplier_id', $supplierId)->where('status', 'draft')->count(),
        ];
    }

    #[Computed]
    public function products()
    {

        $query = Product::query()
            ->where('supplier_id', $this->supplier->id)
            ->with([
                'category',
                'galleries' => fn($q) => $q->where('is_primary', true)
            ]);

        // جستجو
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        // فیلتر وضعیت
        if (!empty($this->status)) {
            $query->where('status', $this->status);
        }

        // فیلتر دسته‌بندی
        if (!empty($this->categoryId)) {
            $query->where('category_id', $this->categoryId);
        }

        // مرتب‌سازی
        match ($this->sortBy) {
            'title' => $query->orderBy('title'),
            'views' => $query->orderBy('view_count', 'desc'),
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };

        return $query->paginate(16);
    }

    public function deleteProduct($productId)
    {
        try {
            // پیدا کردن محصول
            $product = \App\Models\Product::findOrFail($productId);

            // 1. بررسی مالکیت
            if ($product->supplier_id !== $this->supplier->id) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'شما دسترسی لازم برای حذف این محصول را ندارید. (آیدی تامین‌کننده شما: ' . $this->supplier->id . ' - آیدی مالک محصول: ' . $product->supplier_id . ')'
                ]);
                return;
            }

            // 2. بررسی وضعیت پیش‌نویس
            if ($product->status !== \App\Enums\ProductStatus::DRAFT) {
                // دریافت مقدار رشته‌ای/عددی وضعیت برای نمایش در پیام خطا
                $currentStatusValue = $product->status instanceof \App\Enums\ProductStatus
                    ? $product->status->value
                    : $product->status;

                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'فقط محصولاتی که در وضعیت پیش‌نویس هستند قابل حذف می‌باشند. (وضعیت فعلی محصول: ' . $currentStatusValue . ')'
                ]);
                return;
            }


            // 3. بررسی عدم قیمت‌گذاری توسط سایر تامین‌کنندگان
            $otherSuppliersCount = $product->suppliers()->where('suppliers.id', '!=', $this->supplier->id)->count();
            if ($otherSuppliersCount > 0) {
               dd('notify', [
                    'type' => 'error',
                    'message' => 'این محصول توسط ' . $otherSuppliersCount . ' تامین‌کننده دیگر قیمت‌گذاری شده است و امکان حذف آن وجود ندارد.'
                ]);
                return;
            }

            // حذف محصول
            $product->delete();

            // نمایش پیام موفقیت
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'محصول با موفقیت حذف شد.'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'محصول مورد نظر یافت نشد. (ممکن است قبلا حذف شده باشد)'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'خطای سیستمی رخ داد: ' . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.supplier.products.index');
    }
}
