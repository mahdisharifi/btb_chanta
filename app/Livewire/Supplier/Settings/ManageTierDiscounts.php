<?php

namespace App\Livewire\Supplier\Settings;

use App\Models\Category;
use App\Models\SupplierTierDiscount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.supplier')]
class ManageTierDiscounts extends Component
{
    public $tierSettings = [];
    public $affectedPricesCount = 0;
    public $showUpdateConfirm = false;
    public $hasChanges = false;
    
    // برای تنظیمات دسته‌بندی
    public $categories = [];
    public $selectedCategoryId = null;
    public $categoryTierPercentages = []; // [tier_index => percentage]
    public $categoryTierSettings = []; // دسته‌بندی‌های تنظیم شده
    public $editingCategoryId = null;
    
    // نام‌های پیشفرض تیراژها
    public const DEFAULT_TIER_NAMES = [
        0 => 'زیر کارتن',
        1 => 'کارتن (مرجع)',
        2 => 'عمده',
        3 => 'بار',
    ];
    
    public function mount()
    {
        $this->loadTierSettings();
        $this->loadCategories();
        $this->loadCategoryTierSettings();
        $this->calculateAffectedPrices();
    }
    
    public function loadTierSettings()
    {
        $supplierId = Auth::user()->supplier->id;
        
        // بارگذاری تنظیمات موجود
        $existing = SupplierTierDiscount::where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->get()
            ->keyBy('tier_index');
        
        // ایجاد آرایه تنظیمات برای 4 تیراژ
        $this->tierSettings = [];
        for ($i = 0; $i <= 3; $i++) {
            if (isset($existing[$i])) {
                $this->tierSettings[$i] = [
                    'tier_index' => $i,
                    'name' => self::DEFAULT_TIER_NAMES[$i],
                    'percentage_change' => (float)$existing[$i]->percentage_change,
                    'label' => $existing[$i]->label,
                    'exists' => true,
                ];
            } else {
                $this->tierSettings[$i] = [
                    'tier_index' => $i,
                    'name' => self::DEFAULT_TIER_NAMES[$i],
                    'percentage_change' => $this->getDefaultPercentage($i),
                    'label' => null,
                    'exists' => false,
                ];
            }
        }
    }
    
    /**
     * درصد پیشفرض برای هر تیراژ:
     * - تیراژ 0 (زیر کارتن): +5% (معمولاً گران‌تر از کارتن)
     * - تیراژ 1 (کارتن): 0% (مرجع)
     * - تیراژ 2 (عمده): -5% (ارزان‌تر از کارتن)
     * - تیراژ 3 (بار): -10% (ارزان‌ترین)
     */
    private function getDefaultPercentage(int $tierIndex): float
    {
        return match($tierIndex) {
            0 => 5.0,   // زیر کارتن
            1 => 0.0,   // کارتن (مرجع)
            2 => -5.0,  // عمده
            3 => -10.0, // بار
            default => 0.0,
        };
    }
    
    public function rules()
    {
        $rules = [];
        
        foreach ($this->tierSettings as $index => $tier) {
            $rules["tierSettings.{$index}.percentage_change"] = [
                'required',
                'numeric',
                'min:-100',
                'max:100',
            ];
            
            $rules["tierSettings.{$index}.label"] = [
                'nullable',
                'string',
                'max:50',
                'regex:/^[\x{0600}-\x{06FF}\s\d]+$/u'
            ];
        }
        
        return $rules;
    }
    
    public function messages()
    {
        $messages = [];
        
        foreach ($this->tierSettings as $index => $tier) {
            $tierName = $tier['name'];
            
            $messages["tierSettings.{$index}.percentage_change.required"] = "درصد تغییر برای {$tierName} الزامی است.";
            $messages["tierSettings.{$index}.percentage_change.numeric"] = "درصد تغییر باید یک عدد باشد.";
            $messages["tierSettings.{$index}.percentage_change.min"] = "درصد تغییر نمی‌تواند کمتر از -100 باشد.";
            $messages["tierSettings.{$index}.percentage_change.max"] = "درصد تغییر نمی‌تواند بیشتر از 100 باشد.";
            
            $messages["tierSettings.{$index}.label.string"] = "عنوان اختصاصی باید متن باشد.";
            $messages["tierSettings.{$index}.label.max"] = "عنوان اختصاصی نباید بیشتر از 50 کاراکتر باشد.";
            $messages["tierSettings.{$index}.label.regex"] = "عنوان اختصاصی فقط می‌تواند شامل حروف فارسی، اعداد و فاصله باشد.";
        }
        
        return $messages;
    }
    
    public function save()
    {
        $this->validate();
        
        $supplierId = Auth::user()->supplier->id;
        
        DB::transaction(function () use ($supplierId) {
            foreach ($this->tierSettings as $index => $tier) {
                $tierIndex = (int)$tier['tier_index'];
                $percentage = (float)$tier['percentage_change'];
                $label = $this->sanitizeInput($tier['label']);
                
                SupplierTierDiscount::updateOrCreate(
                    [
                        'supplier_id' => $supplierId,
                        'tier_index' => $tierIndex,
                    ],
                    [
                        'percentage_change' => $percentage,
                        'label' => $label,
                        'is_active' => true,
                    ]
                );
            }
        });
        
        $this->dispatch('notify',
            message: '✅ تنظیمات درصد تیراژ با موفقیت ذخیره شد.',
            type: 'success'
        );
        
        $this->hasChanges = false;
        $this->loadTierSettings();
        $this->calculateAffectedPrices();
    }
    
    public function resetToDefaults()
    {
        foreach ($this->tierSettings as $index => $tier) {
            $this->tierSettings[$index]['percentage_change'] = $this->getDefaultPercentage($index);
            $this->tierSettings[$index]['label'] = null;
        }
        
        $this->hasChanges = true;
        
        $this->dispatch('notify',
            message: 'تنظیمات به حالت پیشفرض بازگشت. برای اعمال، ذخیره کنید.',
            type: 'info'
        );
    }
    
    /**
     * محاسبه تعداد قیمت‌های تحت تأثیر
     */
    public function calculateAffectedPrices()
    {
        $supplierId = Auth::user()->supplier->id;
        
        // شمارش قیمت‌های نقدی که با mode='default' هستند
        $this->affectedPricesCount = DB::table('product_prices as pp')
            ->join('product_supplier as ps', 'pp.product_supplier_id', '=', 'ps.id')
            ->where('ps.supplier_id', $supplierId)
            ->where('pp.price_type_id', function($query) {
                $query->select('id')
                    ->from('price_types')
                    ->where('code', 'cash')
                    ->limit(1);
            })
            ->where('pp.is_active', true)
            ->count();
    }
    
    /**
     * نمایش تأییدیه بروزرسانی
     */
    public function confirmUpdatePrices()
    {
        if ($this->hasChanges) {
            $this->dispatch('notify',
                message: 'ابتدا تغییرات را ذخیره کنید.',
                type: 'warning'
            );
            return;
        }
        
        $this->calculateAffectedPrices();
        $this->showUpdateConfirm = true;
    }
    
    /**
     * بروزرسانی قیمت‌های تیراژ
     */
    public function updatePrices()
    {
        try {
            $supplierId = Auth::user()->supplier->id;
            
            // اجرای command برای بروزرسانی
            $result = \App\Helpers\TierPriceUpdater::updateSupplierPrices($supplierId);
            
            $this->dispatch('notify',
                message: "✅ بروزرسانی انجام شد. {$result['updated']} قیمت بروز شد.",
                type: 'success'
            );
            
            if ($result['errors'] > 0) {
                $this->dispatch('notify',
                    message: "⚠️ {$result['errors']} قیمت با خطا مواجه شد.",
                    type: 'warning'
                );
            }
            
            $this->showUpdateConfirm = false;
            $this->calculateAffectedPrices();
            
        } catch (\Exception $e) {
            \Log::error('Error updating tier prices: ' . $e->getMessage());
            $this->dispatch('notify',
                message: 'خطا در بروزرسانی قیمت‌ها: ' . $e->getMessage(),
                type: 'error'
            );
            $this->showUpdateConfirm = false;
        }
    }
    
    /**
     * لغو modal
     */
    public function cancelUpdate()
    {
        $this->showUpdateConfirm = false;
    }
    
    /**
     * بارگذاری دسته‌بندی‌ها
     */
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
    
    /**
     * بارگذاری تنظیمات دسته‌بندی
     */
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
            
            // فقط تیراژهای غیر از مرجع (tier_index != 1)
            if ($record->tier_index != 1) {
                $grouped[$categoryId]['tiers'][] = [
                    'tier_index' => (int)$record->tier_index,
                    'tier_name' => self::DEFAULT_TIER_NAMES[$record->tier_index] ?? "تیراژ {$record->tier_index}",
                    'percentage' => (float)$record->percentage_change,
                ];
            }
        }
        
        $this->categoryTierSettings = array_values($grouped);
    }
    
    /**
     * افزودن دسته‌بندی
     */
    public function addCategory()
    {
        $this->validate([
            'selectedCategoryId' => ['required', 'exists:categories,id'],
            'categoryTierPercentages' => ['required', 'array', 'min:1'],
            'categoryTierPercentages.*' => ['required', 'numeric', 'min:-100', 'max:100'],
        ], [
            'selectedCategoryId.required' => 'لطفاً یک دسته‌بندی انتخاب کنید.',
            'selectedCategoryId.exists' => 'دسته‌بندی انتخاب شده معتبر نیست.',
            'categoryTierPercentages.required' => 'لطفاً حداقل یک درصد تعیین کنید.',
            'categoryTierPercentages.*.numeric' => 'درصد باید یک عدد باشد.',
            'categoryTierPercentages.*.min' => 'درصد نمی‌تواند کمتر از -100 باشد.',
            'categoryTierPercentages.*.max' => 'درصد نمی‌تواند بیشتر از 100 باشد.',
        ]);
        
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
        
        // افزودن رکوردها (فقط برای تیراژهای غیر از مرجع)
        DB::transaction(function () use ($supplierId, $categoryId) {
            foreach ($this->categoryTierPercentages as $tierIndex => $percentage) {
                // تیراژ مرجع (1) را skip کن
                if ($tierIndex == 1) {
                    continue;
                }
                
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
        
        $this->reset(['selectedCategoryId', 'categoryTierPercentages']);
        $this->loadCategoryTierSettings();
        $this->calculateAffectedPrices();
        
        $this->dispatch('notify',
            message: '✅ درصدهای دسته‌بندی با موفقیت تنظیم شد.',
            type: 'success'
        );
    }
    
    /**
     * شروع ویرایش دسته‌بندی
     */
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
        $this->categoryTierPercentages = [];
        
        foreach ($existing as $row) {
            // تیراژ مرجع را skip کن
            if ($row->tier_index != 1) {
                $this->categoryTierPercentages[$row->tier_index] = (float)$row->percentage_change;
            }
        }
    }
    
    /**
     * ذخیره ویرایش دسته‌بندی
     */
    public function saveCategoryEdit()
    {
        $this->validate([
            'categoryTierPercentages' => ['required', 'array', 'min:1'],
            'categoryTierPercentages.*' => ['required', 'numeric', 'min:-100', 'max:100'],
        ]);
        
        $supplierId = Auth::user()->supplier->id;
        $categoryId = $this->editingCategoryId;
        
        DB::transaction(function () use ($supplierId, $categoryId) {
            // حذف تنظیمات قبلی
            DB::table('category_tier_discount')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->delete();
            
            // افزودن تنظیمات جدید (فقط برای تیراژهای غیر از مرجع)
            foreach ($this->categoryTierPercentages as $tierIndex => $percentage) {
                if ($tierIndex == 1) {
                    continue; // تیراژ مرجع را skip کن
                }
                
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
        $this->calculateAffectedPrices();
        
        $this->dispatch('notify',
            message: '✅ درصدهای دسته‌بندی بروزرسانی شد.',
            type: 'success'
        );
    }
    
    /**
     * لغو ویرایش دسته‌بندی
     */
    public function cancelCategoryEdit()
    {
        $this->reset(['editingCategoryId', 'categoryTierPercentages']);
    }
    
    /**
     * حذف دسته‌بندی
     */
    public function removeCategory($categoryId)
    {
        $supplierId = Auth::user()->supplier->id;
        
        DB::table('category_tier_discount')
            ->where('supplier_id', $supplierId)
            ->where('category_id', $categoryId)
            ->delete();
        
        $this->loadCategoryTierSettings();
        $this->calculateAffectedPrices();
        
        $this->dispatch('notify',
            message: '✅ تنظیمات دسته‌بندی حذف شد.',
            type: 'success'
        );
    }
    
    /**
     * Hook برای تشخیص تغییرات
     */
    public function updated($property)
    {
        if (str_starts_with($property, 'tierSettings')) {
            $this->hasChanges = true;
        }
    }
    
    /**
     * Sanitize user input
     */
    private function sanitizeInput(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public function render()
    {
        // دریافت درصدهای پیشفرض تامین‌کننده (برای نمایش در فرم دسته‌بندی)
        $supplierId = Auth::user()->supplier->id;
        $defaultTiers = SupplierTierDiscount::getAllForSupplier($supplierId)
            ->mapWithKeys(function($tier) {
                return [$tier->tier_index => $tier];
            })
            ->toArray();
        
        return view('livewire.supplier.settings.manage-tier-discounts', [
            'defaultTiers' => $defaultTiers,
        ]);
    }
}
