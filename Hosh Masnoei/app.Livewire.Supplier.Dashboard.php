<?php

namespace App\Livewire\Supplier;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.supplier')]
class Dashboard extends Component
{
    /**
     * تأمین‌کنندهٔ کاربر جاری.
     */
    #[Computed]
    public function supplier(): Supplier
    {
        return Supplier::where('user_id', Auth::id())->firstOrFail();
    }

    /**
     * آمار داشبورد بر اساس جدول pivot محصولات تأمین‌کننده (product_supplier).
     *
     * منطق صحیح: تأمین‌کننده محصولات را از کاتالوگ سراسری انتخاب و قیمت‌گذاری می‌کند،
     * پس آمار باید بر اساس رکوردهای product_supplier این تأمین‌کننده باشد، نه supplier_id روی Product.
     */
    #[Computed]
    public function stats(): array
    {
        $supplierId = $this->supplier->id;

        $base = ProductSupplier::query()->where('supplier_id', $supplierId);

        return [
            // تعداد کل محصولاتی که تأمین‌کننده انتخاب کرده است.
            'total_products' => (clone $base)->count(),

            // محصولات فعال بر اساس وضعیت pivot.
            'active_products' => (clone $base)
                ->where('status', ProductSupplier::STATUS_ACTIVE)
                ->count(),

            // محصولات در انتظار بررسی بر اساس وضعیت pivot.
            'pending_review_products' => (clone $base)
                ->where('status', ProductSupplier::STATUS_PENDING)
                ->count(),

            // مجموع بازدید محصولات انتخاب‌شدهٔ این تأمین‌کننده.
            'total_views' => Product::whereIn(
                'id',
                (clone $base)->select('product_id')
            )->sum('view_count'),
        ];
    }

    /**
     * ۱۰ محصول اخیرِ انتخاب‌شده توسط تأمین‌کننده از طریق رابطهٔ many-to-many.
     */
    #[Computed]
    public function recentProducts()
    {
        return $this->supplier->products()
            ->with([
                'category',
                'galleries' => fn ($query) => $query->where('is_primary', true),
            ])
            ->orderByDesc('product_supplier.created_at')
            ->take(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.supplier.dashboard', [
            'supplier' => $this->supplier,
            'stats' => $this->stats,
            'recentProducts' => $this->recentProducts,
        ]);
    }
}
