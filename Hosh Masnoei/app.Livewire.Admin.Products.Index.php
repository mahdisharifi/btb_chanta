<?php

namespace App\Livewire\Admin\Products;

use App\Models\Product;
use App\Models\Category;
use App\Enums\ProductStatus;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('components.layouts.admin')]
class Index extends Component
{
    use WithPagination;

    // متغیرهای جستجو و فیلتر
    public $search = '';
    public $category_id = '';
    public $status = '';
    public $sort = 'latest';

    // ریست کردن صفحه هنگام تغییر فیلترها
    public function updatedSearch() { $this->resetPage(); }
    public function updatedCategoryId() { $this->resetPage(); }
    public function updatedStatus() { $this->resetPage(); }
    public function updatedSort() { $this->resetPage(); }

    public function toggleActive($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['is_active' => !$product->is_active]);

        $this->dispatch('toast', message: 'وضعیت نمایش محصول با موفقیت تغییر کرد.', type: 'success');
    }

    public function delete($id)
    {
        $product = Product::findOrFail($id);

        $product->galleries()->delete();
        $product->attributeValues()->detach();
        $product->delete();

        $this->dispatch('toast', message: 'محصول با موفقیت حذف شد.', type: 'success');
    }

    /**
     * ساخت کوئری مشترک جهت استفاده در نمایش لیست و خروجی اکسل
     */
    protected function getFilteredQuery()
    {
        $query = Product::query();

        // اعمال جستجو
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        // اعمال فیلتر دسته‌بندی
        if (!empty($this->category_id)) {
            $query->where('category_id', $this->category_id);
        }

        // اعمال فیلتر وضعیت
        if (!empty($this->status)) {
            $query->where('status', $this->status);
        }

        // اعمال مرتب‌سازی
        switch ($this->sort) {
            case 'oldest':
                $query->oldest();
                break;
            case 'title_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'title_desc':
                $query->orderBy('title', 'desc');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }

        return $query;
    }

    /**
     * خروجی اکسل با مصرف بهینه حافظه و ترجمه وضعیت‌ها و تاریخ شمسی
     */
    public function exportExcel()
    {
        $fileName = 'products-export-' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $filePath = storage_path('app/public/' . $fileName);

        // ایجاد فایل اکسل و نوشتن هدرها به صورت فارسی و منظم
        $writer = SimpleExcelWriter::create($filePath)
            ->addHeader([
                'شناسه کالا',
                'عنوان محصول',
                'کد کالا (SKU)',
                'دسته‌بندی',
                'وضعیت نمایش',
                'وضعیت کالا',
                'تاریخ ایجاد'
            ]);

        // استفاده از lazy() برای لود پله‌پله کالاها جهت جلوگیری از خطای Memory Limit در رکوردهای بالا
        $this->getFilteredQuery()
            ->with(['category'])
            ->lazy(100)
            ->each(function (Product $product) use ($writer) {
                // تبدیل هوشمند نوع داده وضعیت به برچسب فارسی خوانا
                $statusLabel = 'نامشخص';
                if ($product->status instanceof ProductStatus) {
                    $statusLabel = ProductStatus::options()[$product->status->value] ?? $product->status->name;
                } elseif (is_scalar($product->status)) {
                    $statusLabel = ProductStatus::options()[$product->status] ?? $product->status;
                }

                $writer->addRow([
                    $product->id,
                    $product->title,
                    $product->sku ?? '-',
                    $product->category?->name ?? 'بدون دسته‌بندی',
                    $product->is_active ? 'فعال' : 'غیرفعال',
                    $statusLabel,
                    $product->created_at ? jdate($product->created_at)->format('Y/m/d H:i') : '-'
                ]);
            });

        $writer->close();

        // بازگرداندن فایل جهت دانلود و سپس حذف آن از روی سرور برای عدم اشغال فضای دیسک
        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function render()
    {
        // دریافت کوئری فیلتر شده به همراه لود بهینه تصاویر اصلی و دسته‌بندی
        $query = $this->getFilteredQuery()->with(['category', 'galleries' => function($q) {
            $q->where('is_primary', true);
        }]);

        // واکشی دسته‌بندی‌ها برای دراپ‌داون فیلتر (فقط دسته‌های نهایی)
        $categories = Category::where('is_active', true)->doesntHave('children')->get();
        $statuses = ProductStatus::options();

        return view('livewire.admin.products.index', [
            'products' => $query->paginate(15),
            'categories' => $categories,
            'statuses' => $statuses,
        ]);
    }
}
