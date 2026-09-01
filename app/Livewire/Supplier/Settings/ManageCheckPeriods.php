<?php

namespace App\Livewire\Supplier\Settings;

use App\Models\Category;
use App\Models\CheckPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.supplier')]
class ManageCheckPeriods extends Component
{
    public $globalPeriods = [];
    public $supplierPeriods = [];
    public $categoryPeriods = []; // دسته‌بندی‌های تنظیم شده
    
    // فرم افزودن
    public $name = '';
    public $days = '';
    public $percentage_increase = 0;
    public $is_active = true;
    
    // ویرایش inline
    public $editingInline = null;
    public $inlineName = '';
    public $inlineDays = '';
    public $inlinePercentage = 0;
    public $inlineIsActive = true;
    
    // مدیریت دسته‌بندی‌ها
    public $selectedCategoryId = null;
    public $categoryPercentages = []; // ['check_period_id' => percentage]
    public $editingCategoryId = null;
    
    public function mount()
    {
        $this->loadPeriods();
        $this->loadCategoryPeriods();
    }
    
    public function loadPeriods()
    {
        $supplierId = Auth::user()->supplier->id;
        
        // دوره‌های پیش‌فرض (global) با اطلاعات فعال‌سازی و درصد از pivot
        $this->globalPeriods = CheckPeriod::select('check_periods.*')
            ->selectRaw('COALESCE(scp.percentage_increase, 0) as supplier_percentage')
            ->selectRaw('COALESCE(scp.is_active, 0) as is_enabled')
            ->selectRaw('scp.id as pivot_id')
            ->selectRaw('scp.label as supplier_label')
            ->leftJoin('supplier_check_period as scp', function($join) use ($supplierId) {
                $join->on('check_periods.id', '=', 'scp.check_period_id')
                     ->where('scp.supplier_id', '=', $supplierId);
            })
            ->where('check_periods.scope', 'global')
            ->where('check_periods.is_active', true)
            ->orderBy('check_periods.days')
            ->get()
            ->map(function($period) {
                return [
                    'id' => $period->id,
                    'name' => $period->name,
                    'display_name' => $period->supplier_label ?: $period->name, // استفاده از label اختصاصی
                    'days' => $period->days,
                    'supplier_percentage' => (float)$period->supplier_percentage,
                    'is_enabled' => (bool)$period->is_enabled,
                    'has_pivot' => $period->pivot_id !== null,
                ];
            })
            ->toArray();
        
        // دوره‌های اختصاصی این تامین‌کننده
        $this->supplierPeriods = CheckPeriod::forSupplier($supplierId)
            ->orderBy('days')
            ->get()
            ->map(function($period) {
                return [
                    'id' => $period->id,
                    'name' => $period->name,
                    'days' => $period->days,
                    'is_active' => $period->is_active,
                ];
            })
            ->toArray();
    }
    
    public function rules()
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[\x{0600}-\x{06FF}\s\d]+$/u'
            ],
            'days' => [
                'required',
                'integer',
                'min:1',
                'max:365',
            ],
            'percentage_increase' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            'is_active' => ['boolean'],
        ];
        
        // Unique validation for inline editing
        if ($this->editingInline) {
            $rules['inlineDays'][] = 'unique:check_periods,days,' . $this->editingInline . ',id,supplier_id,' . (Auth::user()->supplier->id ?? 'NULL');
            $rules['inlinePercentage'] = ['required', 'numeric', 'min:0', 'max:100'];
        } else {
            $rules['days'][] = 'unique:check_periods,days,NULL,id,supplier_id,' . (Auth::user()->supplier->id ?? 'NULL');
        }
        
        return $rules;
    }
    
    public function messages()
    {
        return [
            'name.required' => 'نام دوره الزامی است.',
            'name.min' => 'نام دوره باید حداقل 3 کاراکتر باشد.',
            'name.max' => 'نام دوره نباید بیشتر از 50 کاراکتر باشد.',
            'name.regex' => 'نام دوره فقط می‌تواند شامل حروف فارسی، اعداد و فاصله باشد.',
            'days.required' => 'تعداد روز الزامی است.',
            'days.min' => 'تعداد روز باید حداقل 1 باشد.',
            'days.max' => 'تعداد روز نمی‌تواند بیشتر از 365 باشد.',
            'days.unique' => 'این تعداد روز قبلاً ثبت شده است.',
            'percentage_increase.required' => 'درصد افزایش قیمت الزامی است.',
            'percentage_increase.numeric' => 'درصد افزایش باید یک عدد باشد.',
            'percentage_increase.min' => 'درصد افزایش نمی‌تواند منفی باشد.',
            'percentage_increase.max' => 'درصد افزایش نمی‌تواند بیشتر از 100 باشد.',
            'inlinePercentage.required' => 'درصد افزایش قیمت الزامی است.',
            'inlinePercentage.min' => 'درصد افزایش نمی‌تواند منفی باشد.',
            'inlinePercentage.max' => 'درصد افزایش نمی‌تواند بیشتر از 100 باشد.',
        ];
    }
    
    public function save()
    {
        $this->validate();
        
        // امنیت: دریافت supplier_id از کاربر احراز هویت شده
        $supplierId = Auth::user()->supplier->id;
        
        // امنیت: Sanitize inputs
        $sanitizedName = $this->sanitizeInput($this->name);
        $sanitizedDays = (int)$this->days;
        $sanitizedPercentage = (float)$this->percentage_increase;
        
        // بررسی: آیا دوره‌ای با این تعداد روز قبلاً وجود دارد؟
        $existingPeriod = CheckPeriod::where('days', $sanitizedDays)
            ->where(function($q) use ($supplierId) {
                $q->where('scope', 'global')
                  ->orWhere('supplier_id', $supplierId);
            })
            ->first();
        
        if ($existingPeriod) {
            // دوره وجود دارد → فقط به pivot اضافه کن با label اختصاصی
            DB::table('supplier_check_period')->updateOrInsert(
                [
                    'supplier_id' => $supplierId,
                    'check_period_id' => $existingPeriod->id,
                ],
                [
                    'label' => $sanitizedName, // ذخیره عنوان اختصاصی
                    'percentage_increase' => $sanitizedPercentage,
                    'is_active' => (bool)$this->is_active,
                    'updated_at' => now(),
                ]
            );
            
            $this->dispatch('notify', 
                message: 'دوره چک به لیست شما اضافه شد و درصد افزایش تنظیم شد.',
                type: 'success'
            );
            
            // نمایش هشدار بروزرسانی قیمت (چون درصد تغییر کرد)
            $this->showPriceUpdateWarning();
        } else {
            // دوره جدید → ایجاد کن
            $newPeriod = CheckPeriod::create([
                'name' => $sanitizedName,
                'days' => $sanitizedDays,
                'scope' => 'supplier',
                'supplier_id' => $supplierId,
                'is_active' => (bool)$this->is_active,
                'sort_order' => CheckPeriod::forSupplier($supplierId)->max('sort_order') + 1,
            ]);
            
            // اضافه کردن به pivot (label برای دوره‌های جدید خودِ name است)
            DB::table('supplier_check_period')->insert([
                'supplier_id' => $supplierId,
                'check_period_id' => $newPeriod->id,
                'label' => null, // برای دوره‌های خودساخته، از name استفاده می‌شود
                'percentage_increase' => $sanitizedPercentage,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->dispatch('notify', 
                message: 'دوره چک جدید با موفقیت اضافه شد.',
                type: 'success'
            );
            
            // نمایش هشدار بروزرسانی قیمت (چون دوره جدید با درصد اضافه شد)
            $this->showPriceUpdateWarning();
        }
        
        $this->reset(['name', 'days', 'percentage_increase']);
        $this->is_active = true;
        $this->loadPeriods();
    }
    
    public function startInlineEdit($id)
    {
        // امنیت: بررسی مالکیت دوره
        $supplierId = Auth::user()->supplier->id;
        $period = CheckPeriod::forSupplier($supplierId)->findOrFail($id);
        
        // بارگذاری درصد از pivot
        $pivot = DB::table('supplier_check_period')
            ->where('supplier_id', $supplierId)
            ->where('check_period_id', $period->id)
            ->first();
        
        $this->editingInline = (int)$period->id;
        $this->inlineName = $period->name;
        $this->inlineDays = (int)$period->days;
        $this->inlinePercentage = $pivot ? (float)$pivot->percentage_increase : 0;
        $this->inlineIsActive = (bool)$period->is_active;
    }
    
    public function saveInlineEdit()
    {
        $this->validate([
            'inlineName' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[\x{0600}-\x{06FF}\s\d]+$/u'
            ],
            'inlinePercentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            'inlineIsActive' => ['boolean'],
        ], [
            'inlineName.required' => 'نام دوره الزامی است.',
            'inlineName.min' => 'نام دوره باید حداقل 3 کاراکتر باشد.',
            'inlineName.regex' => 'نام دوره فقط می‌تواند شامل حروف فارسی، اعداد و فاصله باشد.',
            'inlinePercentage.required' => 'درصد افزایش الزامی است.',
            'inlinePercentage.numeric' => 'درصد افزایش باید عدد باشد.',
            'inlinePercentage.min' => 'درصد افزایش نمی‌تواند منفی باشد.',
            'inlinePercentage.max' => 'درصد افزایش نمی‌تواند بیشتر از 100 باشد.',
        ]);
        
        $supplierId = Auth::user()->supplier->id;
        $period = CheckPeriod::forSupplier($supplierId)->findOrFail($this->editingInline);
        
        // بروزرسانی دوره
        $period->update([
            'name' => $this->sanitizeInput($this->inlineName),
            'is_active' => (bool)$this->inlineIsActive,
        ]);
        
        // بروزرسانی درصد در pivot
        DB::table('supplier_check_period')->updateOrInsert(
            [
                'supplier_id' => $supplierId,
                'check_period_id' => $period->id,
            ],
            [
                'percentage_increase' => (float)$this->inlinePercentage,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );
        
        $this->cancelInlineEdit();
        $this->loadPeriods();
        
        $this->dispatch('notify',
            message: 'دوره چک با موفقیت ویرایش شد.',
            type: 'success'
        );
        
        // نمایش هشدار بروزرسانی قیمت
        $this->showPriceUpdateWarning();
    }
    
    public function cancelInlineEdit()
    {
        $this->reset(['editingInline', 'inlineName', 'inlineDays', 'inlinePercentage', 'inlineIsActive']);
        $this->inlineIsActive = true;
    }
    
    public function edit($id)
    {
        // این متد دیگر استفاده نمی‌شود - از startInlineEdit استفاده کنید
        $this->startInlineEdit($id);
    }
    
    public function cancelEdit()
    {
        $this->reset(['name', 'days']);
        $this->is_active = true;
    }
    
    /**
     * @deprecated این متد حذف شده - دوره‌ها دیگر قابل حذف نیستند
     */
    public function delete($id)
    {
        $this->dispatch('notify',
            message: 'دوره‌ها قابل حذف نیستند. از دکمه فعال/غیرفعال استفاده کنید.',
            type: 'warning'
        );
    }
    
    /**
     * فعال/غیرفعال کردن یک دوره اختصاصی supplier
     * توجه: فعال/غیرفعال کردن نیازی به بروزرسانی قیمت ندارد
     */
    public function toggleSupplierPeriod($periodId)
    {
        $supplierId = Auth::user()->supplier->id;
        $period = CheckPeriod::forSupplier($supplierId)->findOrFail($periodId);
        
        // تغییر وضعیت
        $period->update([
            'is_active' => !$period->is_active
        ]);
        
        $status = $period->is_active ? 'فعال' : 'غیرفعال';
        
        $this->dispatch('notify',
            message: "دوره چک {$status} شد.",
            type: 'success'
        );
        
        $this->loadPeriods();
    }
    
    /**
     * فعال/غیرفعال کردن یک دوره global برای تامین‌کننده
     * توجه: فعال/غیرفعال کردن نیازی به بروزرسانی قیمت ندارد
     */
    public function toggleGlobalPeriod($periodId, $percentage)
    {
        $supplierId = Auth::user()->supplier->id;
        
        // بررسی: آیا این دوره قبلاً فعال شده؟
        $existing = DB::table('supplier_check_period')
            ->where('supplier_id', $supplierId)
            ->where('check_period_id', $periodId)
            ->first();
        
        if ($existing) {
            if ($existing->is_active) {
                // غیرفعال کردن: درصد را صفر کن (بدون هشدار بروزرسانی)
                DB::table('supplier_check_period')
                    ->where('id', $existing->id)
                    ->update([
                        'is_active' => false,
                        'percentage_increase' => 0,
                        'updated_at' => now(),
                    ]);
                
                $this->dispatch('notify',
                    message: 'دوره چک غیرفعال شد.',
                    type: 'success'
                );
            } else {
                // فعال کردن: باید درصد تعیین شده باشد
                $percentage = (float)$percentage;
                if ($percentage < 0 || $percentage > 100) {
                    $this->dispatch('notify',
                        message: 'درصد افزایش باید بین 0 تا 100 باشد.',
                        type: 'error'
                    );
                    return;
                }
                
                // هشدار برای درصد صفر
                if ($percentage == 0) {
                    $this->dispatch('notify',
                        message: '⚠️ توجه: درصد افزایش صفر است. قیمت چکی برابر با نقدی خواهد بود.',
                        type: 'warning'
                    );
                }
                
                DB::table('supplier_check_period')
                    ->where('id', $existing->id)
                    ->update([
                        'is_active' => true,
                        'percentage_increase' => $percentage,
                        'updated_at' => now(),
                    ]);
                
                $this->dispatch('notify',
                    message: 'دوره چک فعال شد.',
                    type: 'success'
                );
                
                // فقط در فعال‌سازی هشدار بروزرسانی نمایش داده می‌شود
                $this->showPriceUpdateWarning();
            }
        } else {
            // ایجاد جدید: باید درصد تعیین شده باشد
            $percentage = (float)$percentage;
            if ($percentage < 0 || $percentage > 100) {
                $this->dispatch('notify',
                    message: 'درصد افزایش باید بین 0 تا 100 باشد.',
                    type: 'error'
                );
                return;
            }
            
            // هشدار برای درصد صفر
            if ($percentage == 0) {
                $this->dispatch('notify',
                    message: '⚠️ توجه: درصد افزایش صفر است. قیمت چکی برابر با نقدی خواهد بود.',
                    type: 'warning'
                );
            }
            
            DB::table('supplier_check_period')->insert([
                'supplier_id' => $supplierId,
                'check_period_id' => $periodId,
                'label' => null, // دوره‌های global از name خود استفاده می‌کنند
                'percentage_increase' => $percentage,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->dispatch('notify',
                message: 'دوره چک فعال شد.',
                type: 'success'
            );
            
            // فقط در ایجاد جدید هشدار بروزرسانی نمایش داده می‌شود
            $this->showPriceUpdateWarning();
        }
        
        $this->loadPeriods();
    }
    
    /**
     * بروزرسانی درصد افزایش برای یک دوره global
     */
    public function updateGlobalPercentage($periodId, $percentage)
    {
        // Validation
        $percentage = (float)$percentage;
        if ($percentage < 0 || $percentage > 100) {
            $this->dispatch('notify',
                message: 'درصد افزایش باید بین 0 تا 100 باشد.',
                type: 'error'
            );
            return;
        }
        
        $supplierId = Auth::user()->supplier->id;
        
        DB::table('supplier_check_period')->updateOrInsert(
            [
                'supplier_id' => $supplierId,
                'check_period_id' => $periodId,
            ],
            [
                'label' => null, // دوره‌های global از name استفاده می‌کنند
                'percentage_increase' => $percentage,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );
        
        $this->loadPeriods();
        
        $this->dispatch('notify',
            message: 'درصد افزایش بروزرسانی شد.',
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

    /**
     * Sanitize user input
     */
    private function sanitizeInput(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    // ══════════════════════════════════════════════════════════
    //  CATEGORY MANAGEMENT
    // ══════════════════════════════════════════════════════════

    /**
     * بارگذاری دسته‌بندی‌هایی که برای آن‌ها درصد تعیین شده
     */
    public function loadCategoryPeriods()
    {
        $supplierId = Auth::user()->supplier->id;
        
        // دریافت تمام تنظیمات
        $records = DB::table('category_check_period as ccp')
            ->join('categories as c', 'ccp.category_id', '=', 'c.id')
            ->join('check_periods as cp', 'ccp.check_period_id', '=', 'cp.id')
            ->where('ccp.supplier_id', $supplierId)
            ->where('ccp.is_active', true)
            ->select([
                'c.id as category_id',
                'c.name as category_name',
                'cp.id as check_period_id',
                'cp.name as check_period_name',
                'cp.days',
                'ccp.percentage_increase',
            ])
            ->orderBy('c.name')
            ->orderBy('cp.days')
            ->get();
        
        // گروه‌بندی بر اساس دسته‌بندی
        $grouped = [];
        foreach ($records as $record) {
            $categoryId = $record->category_id;
            
            if (!isset($grouped[$categoryId])) {
                $grouped[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => $record->category_name,
                    'periods' => [],
                ];
            }
            
            $grouped[$categoryId]['periods'][] = [
                'check_period_id' => (int)$record->check_period_id,
                'name' => $record->check_period_name,
                'percentage' => (float)$record->percentage_increase,
            ];
        }
        
        $this->categoryPeriods = array_values($grouped);
    }

    /**
     * افزودن دسته‌بندی جدید با درصدهای مشخص
     */
    public function addCategory()
    {
        $this->validate([
            'selectedCategoryId' => ['required', 'exists:categories,id'],
            'categoryPercentages' => ['required', 'array', 'min:1'],
            'categoryPercentages.*' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'selectedCategoryId.required' => 'لطفاً یک دسته‌بندی انتخاب کنید.',
            'selectedCategoryId.exists' => 'دسته‌بندی انتخاب شده معتبر نیست.',
            'categoryPercentages.required' => 'لطفاً حداقل یک درصد تعیین کنید.',
            'categoryPercentages.min' => 'لطفاً حداقل یک درصد تعیین کنید.',
            'categoryPercentages.*.numeric' => 'درصد باید یک عدد باشد.',
            'categoryPercentages.*.min' => 'درصد نمی‌تواند منفی باشد.',
            'categoryPercentages.*.max' => 'درصد نمی‌تواند بیشتر از 100 باشد.',
        ]);
        
        $supplierId = Auth::user()->supplier->id;
        $categoryId = (int)$this->selectedCategoryId;
        
        // بررسی: آیا این دسته‌بندی قبلاً اضافه شده؟
        $existing = DB::table('category_check_period')
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
            foreach ($this->categoryPercentages as $checkPeriodId => $percentage) {
                if ($percentage !== null && $percentage !== '') {
                    DB::table('category_check_period')->insert([
                        'supplier_id' => $supplierId,
                        'category_id' => $categoryId,
                        'check_period_id' => (int)$checkPeriodId,
                        'percentage_increase' => (float)$percentage,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
        
        $this->reset(['selectedCategoryId', 'categoryPercentages']);
        $this->loadCategoryPeriods();
        
        $this->dispatch('notify',
            message: 'درصدهای دسته‌بندی با موفقیت تنظیم شد.',
            type: 'success'
        );
        
        // نمایش هشدار بروزرسانی قیمت
        $this->showPriceUpdateWarning();
    }

    /**
     * حذف تمام تنظیمات یک دسته‌بندی
     */
    public function removeCategory($categoryId)
    {
        $supplierId = Auth::user()->supplier->id;
        
        DB::table('category_check_period')
            ->where('supplier_id', $supplierId)
            ->where('category_id', $categoryId)
            ->delete();
        
        $this->loadCategoryPeriods();
        
        $this->dispatch('notify',
            message: 'تنظیمات دسته‌بندی حذف شد.',
            type: 'success'
        );
        
        // نمایش هشدار بروزرسانی قیمت
        $this->showPriceUpdateWarning();
    }

    /**
     * شروع ویرایش دسته‌بندی
     */
    public function startEditCategory($categoryId)
    {
        $supplierId = Auth::user()->supplier->id;
        
        // بارگذاری درصدهای فعلی
        $existing = DB::table('category_check_period')
            ->where('supplier_id', $supplierId)
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->get();
        
        $this->editingCategoryId = $categoryId;
        $this->categoryPercentages = [];
        
        foreach ($existing as $row) {
            $this->categoryPercentages[$row->check_period_id] = (float)$row->percentage_increase;
        }
    }

    /**
     * ذخیره ویرایش دسته‌بندی
     */
    public function saveCategoryEdit()
    {
        $this->validate([
            'categoryPercentages' => ['required', 'array', 'min:1'],
            'categoryPercentages.*' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'categoryPercentages.required' => 'لطفاً حداقل یک درصد تعیین کنید.',
            'categoryPercentages.min' => 'لطفاً حداقل یک درصد تعیین کنید.',
            'categoryPercentages.*.numeric' => 'درصد باید یک عدد باشد.',
            'categoryPercentages.*.min' => 'درصد نمی‌تواند منفی باشد.',
            'categoryPercentages.*.max' => 'درصد نمی‌تواند بیشتر از 100 باشد.',
        ]);
        
        $supplierId = Auth::user()->supplier->id;
        $categoryId = $this->editingCategoryId;
        
        DB::transaction(function () use ($supplierId, $categoryId) {
            // حذف تنظیمات قبلی
            DB::table('category_check_period')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->delete();
            
            // افزودن تنظیمات جدید
            foreach ($this->categoryPercentages as $checkPeriodId => $percentage) {
                if ($percentage !== null && $percentage !== '') {
                    DB::table('category_check_period')->insert([
                        'supplier_id' => $supplierId,
                        'category_id' => $categoryId,
                        'check_period_id' => (int)$checkPeriodId,
                        'percentage_increase' => (float)$percentage,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
        
        $this->cancelCategoryEdit();
        $this->loadCategoryPeriods();
        
        $this->dispatch('notify',
            message: 'درصدهای دسته‌بندی بروزرسانی شد.',
            type: 'success'
        );
        
        // نمایش هشدار بروزرسانی قیمت
        $this->showPriceUpdateWarning();
    }

    /**
     * لغو ویرایش دسته‌بندی
     */
    public function cancelCategoryEdit()
    {
        $this->reset(['editingCategoryId', 'categoryPercentages']);
    }

    public function render()
    {
        // دریافت فقط دسته‌بندی‌های برگ (که فرزند ندارند) برای dropdown
        $categories = \App\Models\Category::active()
            ->whereDoesntHave('children') // فقط دسته‌های بدون فرزند
            ->ordered()
            ->get()
            ->map(function($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->full_path, // نمایش مسیر کامل
                ];
            })
            ->pluck('name', 'id')
            ->toArray();
        
        // دریافت دوره‌های فعال برای نمایش در فرم
        $supplierId = Auth::user()->supplier->id;
        $activePeriods = CheckPeriod::getActiveForSupplier($supplierId);
        
        return view('livewire.supplier.settings.manage-check-periods', [
            'categories' => $categories,
            'activePeriods' => $activePeriods,
        ]);
    }
}
