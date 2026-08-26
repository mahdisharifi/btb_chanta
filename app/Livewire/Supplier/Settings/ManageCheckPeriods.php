<?php

namespace App\Livewire\Supplier\Settings;

use App\Models\CheckPeriod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.supplier')]
class ManageCheckPeriods extends Component
{
    public $globalPeriods = [];
    public $supplierPeriods = [];
    
    // فرم افزودن
    public $name = '';
    public $days = '';
    public $is_active = true;
    
    // ویرایش inline
    public $editingInline = null;
    public $inlineName = '';
    public $inlineDays = '';
    public $inlineIsActive = true;
    
    public function mount()
    {
        $this->loadPeriods();
    }
    
    public function loadPeriods()
    {
        // دوره‌های پیش‌فرض (global)
        $this->globalPeriods = CheckPeriod::global()
            ->active()
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
        
        // دوره‌های اختصاصی این تامین‌کننده
        $supplierId = Auth::user()->supplier->id;
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
            'is_active' => ['boolean'],
        ];
        
        // Unique validation for inline editing
        if ($this->editingInline) {
            $rules['inlineDays'][] = 'unique:check_periods,days,' . $this->editingInline . ',id,supplier_id,' . (Auth::user()->supplier->id ?? 'NULL');
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
        
        // افزودن جدید
        CheckPeriod::create([
            'name' => $sanitizedName,
            'days' => $sanitizedDays,
            'scope' => 'supplier',
            'supplier_id' => $supplierId,
            'is_active' => (bool)$this->is_active,
            'sort_order' => CheckPeriod::forSupplier($supplierId)->max('sort_order') + 1,
        ]);
        
        $this->reset(['name', 'days']);
        $this->is_active = true;
        $this->loadPeriods();
        
        $this->dispatch('notify', 
            message: 'دوره چک جدید با موفقیت اضافه شد.',
            type: 'success'
        );
    }
    
    public function startInlineEdit($id)
    {
        // امنیت: بررسی مالکیت دوره
        $supplierId = Auth::user()->supplier->id;
        $period = CheckPeriod::forSupplier($supplierId)->findOrFail($id);
        
        // بررسی: آیا در قیمت‌گذاری استفاده شده؟
        if ($period->productPrices()->exists()) {
            $this->dispatch('notify',
                message: 'این دوره در قیمت‌گذاری محصولات استفاده شده و قابل ویرایش نیست.',
                type: 'error'
            );
            return;
        }
        
        $this->editingInline = (int)$period->id;
        $this->inlineName = $period->name;
        $this->inlineDays = (int)$period->days;
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
            'inlineDays' => [
                'required',
                'integer',
                'min:1',
                'max:365',
                'unique:check_periods,days,' . $this->editingInline . ',id,supplier_id,' . (Auth::user()->supplier->id ?? 'NULL')
            ],
            'inlineIsActive' => ['boolean'],
        ], [
            'inlineName.required' => 'نام دوره الزامی است.',
            'inlineName.min' => 'نام دوره باید حداقل 3 کاراکتر باشد.',
            'inlineName.regex' => 'نام دوره فقط می‌تواند شامل حروف فارسی، اعداد و فاصله باشد.',
            'inlineDays.required' => 'تعداد روز الزامی است.',
            'inlineDays.unique' => 'این تعداد روز قبلاً ثبت شده است.',
        ]);
        
        $supplierId = Auth::user()->supplier->id;
        $period = CheckPeriod::forSupplier($supplierId)->findOrFail($this->editingInline);
        
        $period->update([
            'name' => $this->sanitizeInput($this->inlineName),
            'days' => (int)$this->inlineDays,
            'is_active' => (bool)$this->inlineIsActive,
        ]);
        
        $this->cancelInlineEdit();
        $this->loadPeriods();
        
        $this->dispatch('notify',
            message: 'دوره چک با موفقیت ویرایش شد.',
            type: 'success'
        );
    }
    
    public function cancelInlineEdit()
    {
        $this->reset(['editingInline', 'inlineName', 'inlineDays', 'inlineIsActive']);
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
    
    public function delete($id)
    {
        // امنیت: بررسی مالکیت دوره
        $supplierId = Auth::user()->supplier->id;
        $period = CheckPeriod::forSupplier($supplierId)->findOrFail($id);
        
        // بررسی: آیا این دوره در قیمت‌ها استفاده شده؟
        $usageCount = $period->productPrices()->count();
        
        if ($usageCount > 0) {
            $this->dispatch('notify',
                message: "این دوره در قیمت‌گذاری {$usageCount} محصول استفاده شده و نمی‌تواند حذف شود.",
                type: 'error'
            );
            return;
        }
        
        $period->delete();
        $this->loadPeriods();
        
        $this->dispatch('notify',
            message: 'دوره چک با موفقیت حذف شد.',
            type: 'success'
        );
    }
    
    /**
     * بررسی قابل حذف بودن دوره
     */
    public function canDelete($id): bool
    {
        $supplierId = Auth::user()->supplier->id;
        $period = CheckPeriod::forSupplier($supplierId)->find($id);
        
        if (!$period) {
            return false;
        }
        
        return !$period->productPrices()->exists();
    }
    
    public function toggleActive($id)
    {
        // امنیت: بررسی مالکیت دوره
        $supplierId = Auth::user()->supplier->id;
        $period = CheckPeriod::forSupplier($supplierId)->findOrFail($id);
        
        $period->update(['is_active' => !$period->is_active]);
        $this->loadPeriods();
        
        $status = $period->is_active ? 'فعال' : 'غیرفعال';
        $this->dispatch('notify',
            message: "دوره چک {$status} شد.",
            type: 'success'
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

    public function render()
    {
        return view('livewire.supplier.settings.manage-check-periods');
    }
}
