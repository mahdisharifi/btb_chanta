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

    public $search = '';
    public $category_id = '';
    public $status = '';
    public $sort = 'latest';

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

    protected function getFilteredQuery()
    {
        $query = Product::query();

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->category_id)) {
            $query->where('category_id', $this->category_id);
        }

        if (!empty($this->status)) {
            $query->where('status', $this->status);
        }

        switch ($this->sort) {
            case 'oldest': $query->oldest(); break;
            case 'title_asc': $query->orderBy('title', 'asc'); break;
            case 'title_desc': $query->orderBy('title', 'desc'); break;
            case 'latest':
            default: $query->latest(); break;
        }

        return $query;
    }

    /**
     * خروجی اکسل
     */
    public function exportExcel()
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $fileName = 'products-export-' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $filePath = storage_path('app/public/' . $fileName);

        // بررسی ادمین اصلی
        $isMainAdmin = auth()->check() && auth()->user()->email === 'admin@chanta.ir';

        // محاسبه میانگین
        $categoryAverages = $this->getCategoryAttributeAverages();

        // تعریف هدرها
        $headers = [
            'شناسه', 'عنوان محصول', 'کد کالا', 'دسته‌بندی',
            'وضعیت نمایش', 'وضعیت کالا', 'تعداد ویژگی', 'میانگین دسته',
            'نیاز به تکمیل', 'وضعیت SEO'
        ];

        if ($isMainAdmin) {
            $headers[] = 'آخرین بروزرسانی ویژگی';
            $headers[] = 'تعداد ویژگی بروز شده';
        }

        $headers[] = 'تاریخ ایجاد';

        $writer = SimpleExcelWriter::create($filePath)->addHeader($headers);

        $this->getFilteredQuery()
            ->with(['category'])
            ->leftJoin(\DB::raw('(
                SELECT
                    product_id,
                    COUNT(*) as attributes_count,
                    MAX(updated_at) as last_attribute_update
                FROM attribute_product
                GROUP BY product_id
            ) as attr_stats'), 'products.id', '=', 'attr_stats.product_id')
            ->select('products.*', 'attr_stats.attributes_count', 'attr_stats.last_attribute_update')
            ->lazy(100)
            ->each(function ($product) use ($writer, $categoryAverages, $isMainAdmin) {
                // وضعیت
                $statusLabel = 'نامشخص';
                if ($product->status instanceof ProductStatus) {
                    $statusLabel = ProductStatus::options()[$product->status->value] ?? $product->status->name;
                } elseif (is_scalar($product->status)) {
                    $statusLabel = ProductStatus::options()[$product->status] ?? $product->status;
                }

                // تعداد ویژگی (شامل توضیحات)
                $attributesCount = (int) ($product->attributes_count ?? 0);
                $attributesCount += (!empty($product->short_description) ? 1 : 0);
                $attributesCount += (!empty($product->full_description) ? 1 : 0);

                // میانگین دسته
                $categoryAvg = $categoryAverages[$product->category_id] ?? 0;
                $categoryAvgFormatted = number_format($categoryAvg, 1);

                // نیاز به تکمیل
                if ($attributesCount >= $categoryAvg) {
                    $needToComplete = 'کامل';
                } else {
                    $needCount = (int) ceil($categoryAvg - $attributesCount);
                    $needToComplete = "نیاز به {$needCount} ویژگی";
                }

                // SEO
                $seoStatus = $this->checkSeoCompletion($product);

                // ردیف پایه
                $row = [
                    $product->id,
                    $product->title,
                    $product->sku ?? '-',
                    $product->category?->name ?? 'بدون دسته',
                    $product->is_active ? 'فعال' : 'غیرفعال',
                    $statusLabel,
                    $attributesCount,
                    $categoryAvgFormatted,
                    $needToComplete,
                    $seoStatus
                ];

                // ستون‌های مخصوص ادمین
                if ($isMainAdmin) {
                    $row[] = $product->last_attribute_update
                        ? jdate($product->last_attribute_update)->format('Y/m/d H:i')
                        : 'هرگز';

                    $recentUpdatedCount = 0;
                    if ($product->last_attribute_update) {
                        $lastUpdate = \Carbon\Carbon::parse($product->last_attribute_update);
                        $recentUpdatedCount = \DB::table('attribute_product')
                            ->where('product_id', $product->id)
                            ->whereBetween('updated_at', [
                                $lastUpdate->copy()->subMinutes(5),
                                $lastUpdate->copy()->addMinutes(5)
                            ])
                            ->count();
                    }
                    $row[] = $recentUpdatedCount;
                }

                $row[] = $product->created_at ? jdate($product->created_at)->format('Y/m/d H:i') : '-';

                $writer->addRow($row);
            });

        $writer->close();
        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    /**
     * محاسبه میانگین (شامل توضیحات)
     */
    private function getCategoryAttributeAverages(): array
    {
        $results = \DB::table('products')
            ->select('category_id')
            ->selectRaw('AVG(
                COALESCE(attr_count, 0) +
                CASE WHEN short_description IS NOT NULL AND short_description != "" THEN 1 ELSE 0 END +
                CASE WHEN full_description IS NOT NULL AND full_description != "" THEN 1 ELSE 0 END
            ) as avg_attributes')
            ->leftJoin(\DB::raw('(
                SELECT product_id, COUNT(*) as attr_count
                FROM attribute_product
                GROUP BY product_id
            ) as counts'), 'products.id', '=', 'counts.product_id')
            ->whereNull('products.deleted_at')
            ->groupBy('category_id')
            ->get();

        $averages = [];
        foreach ($results as $row) {
            $averages[$row->category_id] = (float) $row->avg_attributes;
        }
        return $averages;
    }

    private function checkSeoCompletion(Product $product): string
    {
        $filledCount = 0;
        if (!empty($product->seo_title)) $filledCount++;
        if (!empty($product->seo_description)) $filledCount++;
        if (!empty($product->seo_keywords)) $filledCount++;

        return match($filledCount) {
            0 => 'خالی',
            3 => 'کامل',
            default => "ناقص ({$filledCount}/3)"
        };
    }

    public function render()
    {
        $query = $this->getFilteredQuery()->with(['category', 'galleries' => function($q) {
            $q->where('is_primary', true);
        }]);

        $categories = Category::where('is_active', true)->doesntHave('children')->get();
        $statuses = ProductStatus::options();

        return view('livewire.admin.products.index', [
            'products' => $query->paginate(15),
            'categories' => $categories,
            'statuses' => $statuses,
        ]);
    }
}
