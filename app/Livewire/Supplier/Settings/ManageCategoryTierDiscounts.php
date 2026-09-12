<?php

namespace App\Livewire\Supplier\Settings;

use App\Models\Category;
use App\Models\CategoryTierDiscount;
use App\Models\SupplierTierDiscount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.supplier')]
class ManageCategoryTierDiscounts extends Component
{
    public $categories = [];
    
    public $selectedCategoryId = null;
    public $tierPercentages = []; // ['tier_index' => percentage]
    
    public $categoryTierSettings = []; // دسته‌بندی‌های تنظیم شده
    public $editingCategoryId = null;
    
    public function mount()
    {
        $this->loadCategories();
        $this->loadCategoryTierSettings();
    }
    
    public function loadCategories()
    {
        // فقط دسته‌های برگ (بدون فرزند)
        $this->categories = Category::active()
            ->whereDoesntHave('children')
            ->ordered()
            ->get()
            ->map(function($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->full_path,
                ];
            })
            ->pluck('name', 'id')
            ->toArray();
    }
    
    public function loadCategoryTierSettings()
    {
        $supplierId = Auth::user()->supplier->id;
        
        // دریافت تمام تنظیمات
        $records = DB::table('category_tier_discount as ctd')
            ->join('categories as c', 'ctd.category_id', '=', 'c.id')
            ->where('ctd.supplier_id', $supplierId)
            ->where('ctd.is_active', true)
            ->select([
                'c.id as category_id',
                'c.name as category_name',
                'ctd.tier_index',
                'ctd.percentage_change',
            ])
            ->orderBy('c.name')
            ->orderBy('ctd.tier_index')
            ->get();
        
        // گروه‌بندی بر اساس دسته‌بندی
        $grouped = [];
        foreach ($records as $record) {
            $categoryId = $record->category_id;
            
            if (!isset($grouped[$categoryId])) {
                // دریافت مسیر کامل دسته‌بندی
                $category = Category::find($categoryId);
                
                $grouped[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => $category ? $category->full_path : $record->category_name,
                    'tiers' => [],
                ];
            }
            
            $grouped[$categoryId]['tiers'][] = [
                'tier_index' => (int)$record->tier_index,
                'tier_name' => SupplierTierDiscount::TIER_NAMES[$record->tier_index] ?? "تیراژ {$record->tier_index}",
                'percentage' => (float)$record->percentage_change,
            ];
        }
        
        $this->categoryTierSettings = array_values($grouped);
    }
    
    public function rules()
    {
        return [
            'selectedCategoryId' => ['required', 'exists:categories,id'],
            'tierPercentages' => ['required', 'array', 'min:1'],
            'tierPercentages.*' => ['required', 'numeric', 'min:-100', 'max:100'],
        ];
    }
    
    public function messages()
    {
        return [
            'selectedCategoryId.required' => 'لطفاً یک دسته‌بندی انتخاب کنید.',
            'selectedCategoryId.exists' => 'دسته‌بندی انتخاب شده معتبر نیست.',
            'tierPercentages.required' => 'لطفاً حداقل یک درصد تعیین کنید.',
            'tierPercentages.min' => 'لطفاً حداقل یک درصد تعیین کنید.',
            'tierPercentages.*.numeric' => 'درصد باید یک عدد باشد.',
            'tierPercentages.*.min' => 'درصد نمی‌تواند کمتر از -100 باشد.',
            'tierPercentages.*.max' => 'درصد نمی‌تواند بیشتر از 100 باشد.',
        ];
    }
    
    public function addCategory()
    {
        $this->validate();
        
        $supplierId = Auth::user()->supplier->id;
        $categoryId = (int)$this->selectedCategoryId;
        
        // بررسی: آیا این دسته‌بندی قبلاً اضافه شده؟
        $existing = DB::table('category_tier_discount')
            ->where('supplier_id', $supplierId)
            ->where('category_id', $categoryId)
            ->exists();
        
        if ($existing) {
            $this->dispatch('notify',
                message: 'این دسته‌بندی قبلاً اضافه شده است. می‌توانید آن را ویرایش کنید.',
                type: 'warning'
            );
            return;
        }
        
        // افزودن رکوردها
        DB::transaction(function () use ($supplierId, $categoryId) {
            foreach ($this->tierPercentages as $tierIndex => $percentage) {
                if ($percentage !== null && $percentage !== '') {
                    DB::table('category_tier_discount')->insert([
                        'supplier_id' => $supplierId,
                        'category_id' => $categoryId,
                        'tier_index' => (int)$tierIndex,
                        'percentage_change' => (float)$percentage,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
        
        $this->reset(['selectedCategoryId', 'tierPercentages']);
        $this->loadCategoryTierSettings();
        
        $this->dispatch('notify',
            message: '✅ درصدهای دسته‌بندی با موفقیت تنظیم شد.',
            type: 'success'
        );
        
        // نمایش هشدار بروزرسانی قیمت
        $this->showPriceUpdateWarning();
    }
    
    public function startEditCategory($categoryId)
    {
        $supplierId = Auth::user()->supplier->id;
        
        // بارگذاری درصدهای فعلی
        $existing = DB::table('category_tier_discount')
            ->where('supplier_id', $supplierId)
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->get();
        
        $this->editingCategoryId = $categoryId;
        $this->tierPercentages = [];
        
        foreach ($existing as $row) {
            $this->tierPercentages[$row->tier_index] = (float)$row->percentage_change;
        }
    }
    
    public function saveCategoryEdit()
    {
        $this->validate([
            'tierPercentages' => ['required', 'array', 'min:1'],
            'tierPercentages.*' => ['required', 'numeric', 'min:-100', 'max:100'],
        ]);
        
        $supplierId = Auth::user()->supplier->id;
        $categoryId = $this->editingCategoryId;
        
        DB::transaction(function () use ($supplierId, $categoryId) {
            // حذف تنظیمات قبلی
            DB::table('category_tier_discount')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->delete();
            
            // افزودن تنظیمات جدید
            foreach ($this->tierPercentages as $tierIndex => $percentage) {
                if ($percentage !== null && $percentage !== '') {
                    DB::table('category_tier_discount')->insert([
                        'supplier_id' => $supplierId,
                        'category_id' => $categoryId,
                        'tier_index' => (int)$tierIndex,
                        'percentage_change' => (float)$percentage,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
        
        $this->cancelCategoryEdit();
        $this->loadCategoryTierSettings();
        
        $this->dispatch('notify',
            message: '✅ درصدهای دسته‌بندی بروزرسانی شد.',
            type: 'success'
        );
        
        // نمایش هشدار بروزرسانی قیمت
        $this->showPriceUpdateWarning();
    }
    
    public function cancelCategoryEdit()
    {
        $this->reset(['editingCategoryId', 'tierPercentages']);
    }
    
    public function removeCategory($categoryId)
    {
        $supplierId = Auth::user()->supplier->id;
        
        DB::table('category_tier_discount')
            ->where('supplier_id', $supplierId)
            ->where('category_id', $categoryId)
            ->delete();
        
        $this->loadCategoryTierSettings();
        
        $this->dispatch('notify',
            message: '✅ تنظیمات دسته‌بندی حذف شد.',
            type: 'success'
        );
        
        // نمایش هشدار بروزرسانی قیمت
        $this->showPriceUpdateWarning();
    }
    
    /**
     * نمایش هشدار بروزرسانی قیمت (بدون redirect خودکار)
     */
    private function showPriceUpdateWarning()
    {
        $this->dispatch('notify-with-link',
            message: '✅ تغییرات ذخیره شد. برای اعمال تغییرات روی قیمت‌ها، به صفحه قیمت‌گذاری سریع بروید.',
            type: 'success',
            linkUrl: route('supplier.fast-pricing.import'),
            linkText: 'رفتن به قیمت‌گذاری سریع'
        );
    }
    
    public function render()
    {
        // دریافت درصدهای پیشفرض تامین‌کننده (برای نمایش در فرم)
        $supplierId = Auth::user()->supplier->id;
        $defaultTiers = SupplierTierDiscount::getAllForSupplier($supplierId)
            ->mapWithKeys(function($tier) {
                return [$tier->tier_index => $tier];
            })
            ->toArray();
        
        return view('livewire.supplier.settings.manage-category-tier-discounts', [
            'defaultTiers' => $defaultTiers,
        ]);
    }
}
