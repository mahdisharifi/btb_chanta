<?php

namespace App\Livewire\Supplier\Settings;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\SupplierPriceRoundingRange;
use App\Helpers\PriceRoundingHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.supplier')]
class ManagePriceRoundingRanges extends Component
{
    // Form properties - array of ranges
    public $ranges = [];
    public $affectedPricesCount = 0;
    public $coverageWarning = null;
    public $showUpdateAllConfirm = false;
    public $hasChanges = false;

    protected function rules(): array
    {
        return [
            'ranges.*.min_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'ranges.*.max_price' => ['nullable', 'numeric', 'max:999999999999'],
            'ranges.*.rounding_method' => ['required', 'in:ceil,floor,nearest'],
            'ranges.*.rounding_step' => ['required', 'in:100,200,500,1000,2000,5000,10000,20000,50000'],
            'ranges.*.is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'ranges.*.min_price.required' => 'حداقل قیمت الزامی است.',
            'ranges.*.min_price.numeric' => 'حداقل قیمت باید عدد باشد.',
            'ranges.*.min_price.min' => 'حداقل قیمت نمی‌تواند منفی باشد.',
            'ranges.*.max_price.numeric' => 'حداکثر قیمت باید عدد باشد.',
            'ranges.*.rounding_method.required' => 'نوع رُند کردن الزامی است.',
            'ranges.*.rounding_step.required' => 'مبلغ مرجع الزامی است.',
        ];
    }

    public function mount()
    {
        $this->loadRanges();
        $this->calculateAffectedPrices();
    }

    /**
     * بارگذاری رنج‌های تأمین‌کننده
     */
    public function loadRanges()
    {
        $supplierId = Auth::user()->supplier->id;
        
        $existingRanges = SupplierPriceRoundingRange::forSupplier($supplierId)
            ->ordered()
            ->get();

        if ($existingRanges->isEmpty()) {
            // پیش‌فرض: یک رنج از 0 تا بی‌نهایت
            $this->ranges = [
                [
                    'id' => null,
                    'min_price' => 0,
                    'max_price' => '',
                    'rounding_method' => 'ceil',
                    'rounding_step' => 100,
                    'is_active' => true,
                ]
            ];
        } else {
            $this->ranges = $existingRanges->map(function($range) {
                return [
                    'id' => $range->id,
                    'min_price' => (float) $range->min_price,
                    'max_price' => $range->max_price ? (float) $range->max_price : '',
                    'rounding_method' => $range->rounding_method,
                    'rounding_step' => $range->rounding_step,
                    'is_active' => $range->is_active,
                ];
            })->toArray();
        }
        
        $this->checkCoverage();
        $this->hasChanges = false;
    }

    /**
     * افزودن رنج جدید
     */
    public function addRange()
    {
        $lastRange = end($this->ranges);
        $lastRange = $lastRange ?: ['max_price' => 0];
        
        // min_price باید 1 تومان بیشتر از max_price رنج قبلی باشد
        $newMinPrice = 0;
        if ($lastRange['max_price'] !== '' && $lastRange['max_price'] !== null) {
            $newMinPrice = $lastRange['max_price'] + 1;
        }
        
        $newRange = [
            'id' => null,
            'min_price' => $newMinPrice,
            'max_price' => '',
            'rounding_method' => 'ceil',
            'rounding_step' => 100,
            'is_active' => true,
        ];
        
        $this->ranges[] = $newRange;
        $this->hasChanges = true;
        $this->checkCoverage();
    }

    /**
     * حذف رنج
     */
    public function removeRange($index)
    {
        if (count($this->ranges) > 1) {
            unset($this->ranges[$index]);
            $this->ranges = array_values($this->ranges); // re-index
            $this->hasChanges = true;
            $this->checkCoverage();
        } else {
            $this->dispatch('toast', type: 'error', message: 'حداقل یک رنج باید وجود داشته باشد.');
        }
    }

    /**
     * ذخیره تمام رنج‌ها
     */
    public function saveAll()
    {
        $this->validate();

        $supplierId = Auth::user()->supplier->id;

        try {
            DB::beginTransaction();

            // حذف رنج‌های قدیمی
            SupplierPriceRoundingRange::where('supplier_id', $supplierId)->delete();

            // ایجاد رنج‌های جدید
            foreach ($this->ranges as $rangeData) {
                // تبدیل max_price خالی به null
                $maxPrice = null;
                if (isset($rangeData['max_price']) && $rangeData['max_price'] !== '' && $rangeData['max_price'] !== null) {
                    $maxPrice = (float) $rangeData['max_price'];
                }

                SupplierPriceRoundingRange::create([
                    'supplier_id' => $supplierId,
                    'min_price' => (float) $rangeData['min_price'],
                    'max_price' => $maxPrice,
                    'rounding_method' => $rangeData['rounding_method'],
                    'rounding_step' => (int) $rangeData['rounding_step'],
                    'is_active' => (bool) ($rangeData['is_active'] ?? true),
                ]);
            }

            // بررسی پوشش کامل
            $coverage = SupplierPriceRoundingRange::validateCompleteCoverage($supplierId);
            if (!$coverage['valid']) {
                DB::rollBack();
                $this->dispatch('toast', type: 'error', message: '❌ ' . $coverage['message']);
                return;
            }

            DB::commit();

            $this->dispatch('toast', type: 'success', message: '✅ رنج‌ها با موفقیت ذخیره شدند.');
            $this->hasChanges = false;
            $this->loadRanges();
            $this->calculateAffectedPrices();

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error saving price rounding ranges: ' . $e->getMessage());
            $this->dispatch('toast', type: 'error', message: 'خطا در ذخیره رنج‌ها: ' . $e->getMessage());
        }
    }

    /**
     * انصراف و بازگشت به حالت اولیه
     */
    public function cancelChanges()
    {
        $this->loadRanges();
        $this->dispatch('toast', type: 'info', message: 'تغییرات لغو شد.');
    }

    /**
     * بررسی پوشش کامل رنج‌ها
     */
    public function checkCoverage()
    {
        // ساخت رنج‌های موقت برای بررسی
        $tempRanges = collect($this->ranges)->map(function($r) {
            return (object)[
                'min_price' => (float) ($r['min_price'] ?? 0),
                'max_price' => (!empty($r['max_price']) && $r['max_price'] !== '') ? (float) $r['max_price'] : null,
            ];
        });

        // بررسی شروع از صفر
        if ($tempRanges->isEmpty() || $tempRanges->first()->min_price != 0) {
            $this->coverageWarning = 'رنج‌ها باید از صفر شروع شوند.';
            return;
        }

        // بررسی شکاف‌ها
        $expectedStart = 0;
        $gaps = [];
        
        foreach ($tempRanges->sortBy('min_price') as $range) {
            // بررسی شکاف: اگر min_price بزرگتر از expectedStart باشد
            if ($range->min_price > $expectedStart) {
                $gaps[] = [
                    'from' => $expectedStart,
                    'to' => $range->min_price - 1,
                ];
            }
            
            if ($range->max_price === null) {
                break;
            }
            
            // رنج بعدی باید از max_price + 1 شروع شود
            $expectedStart = $range->max_price + 1;
        }

        // بررسی آخرین رنج
        $lastRange = $tempRanges->sortBy('min_price')->last();
        if ($lastRange && $lastRange->max_price !== null) {
            $this->coverageWarning = 'آخرین رنج باید تا بی‌نهایت ادامه داشته باشد (حداکثر قیمت = خالی).';
            return;
        }

        if (count($gaps) > 0) {
            $gapsText = collect($gaps)->map(function($gap) {
                return number_format($gap['from']) . ' تا ' . number_format($gap['to']);
            })->implode(', ');
            $this->coverageWarning = 'شکاف در رنج‌های قیمتی: ' . $gapsText;
            return;
        }

        $this->coverageWarning = null;
    }

    /**
     * تغییر فیلد و علامت‌گذاری تغییرات
     */
    public function updated($property)
    {
        if (str_starts_with($property, 'ranges')) {
            $this->hasChanges = true;
            
            // اگر max_price تغییر کرد، min_price رنج بعدی را آپدیت کن
            if (preg_match('/^ranges\.(\d+)\.max_price$/', $property, $matches)) {
                $index = (int)$matches[1];
                $this->updateNextRangeMinPrice($index);
            }
            
            $this->checkCoverage();
        }
    }

    /**
     * آپدیت خودکار min_price رنج بعدی
     */
    private function updateNextRangeMinPrice(int $currentIndex)
    {
        $nextIndex = $currentIndex + 1;
        
        // اگر رنج بعدی وجود دارد
        if (isset($this->ranges[$nextIndex])) {
            $currentMaxPrice = $this->ranges[$currentIndex]['max_price'];
            
            // اگر max_price خالی نیست
            if ($currentMaxPrice !== '' && $currentMaxPrice !== null) {
                $this->ranges[$nextIndex]['min_price'] = $currentMaxPrice + 1;
            }
        }
    }

    /**
     * محاسبه تعداد قیمت‌های تحت تأثیر
     */
    public function calculateAffectedPrices()
    {
        $supplierId = Auth::user()->supplier->id;
        $this->affectedPricesCount = PriceRoundingHelper::getAffectedPricesCount($supplierId);
    }

    /**
     * نمایش تأییدیه بروزرسانی همه قیمت‌ها
     */
    public function confirmUpdateAll()
    {
        // بررسی ذخیره شدن تغییرات
        if ($this->hasChanges) {
            $this->dispatch('toast', type: 'warning', message: 'ابتدا رنج‌ها را ذخیره کنید.');
            return;
        }

        $this->calculateAffectedPrices();
        $this->showUpdateAllConfirm = true;
    }

    /**
     * بروزرسانی همه قیمت‌ها بر اساس رنج‌های جدید
     */
    public function updateAllPrices()
    {
        try {
            $supplierId = Auth::user()->supplier->id;
            
            \Log::info("Starting price update for supplier {$supplierId}");
            
            $result = PriceRoundingHelper::updateSupplierPrices($supplierId);

            \Log::info("Price update completed", $result);

            $this->dispatch('toast', 
                type: 'success', 
                message: "✅ بروزرسانی انجام شد. {$result['updated']} قیمت بروز شد."
            );

            if ($result['errors'] > 0) {
                $this->dispatch('toast', 
                    type: 'warning', 
                    message: "⚠️ {$result['errors']} قیمت با خطا مواجه شد."
                );
            }

            $this->showUpdateAllConfirm = false;
            $this->calculateAffectedPrices();

        } catch (\Exception $e) {
            \Log::error('Error updating all prices: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->dispatch('toast', type: 'error', message: 'خطا در بروزرسانی قیمت‌ها: ' . $e->getMessage());
            $this->showUpdateAllConfirm = false;
        }
    }

    /**
     * لغو modal
     */
    public function cancelModal()
    {
        $this->showUpdateAllConfirm = false;
    }

    /**
     * دریافت لیست متدهای رُند
     */
    public function getRoundingMethodsProperty()
    {
        return SupplierPriceRoundingRange::ROUNDING_METHODS;
    }

    /**
     * دریافت لیست مبالغ مرجع مجاز
     */
    public function getAllowedStepsProperty()
    {
        return SupplierPriceRoundingRange::ALLOWED_STEPS;
    }

    public function render()
    {
        return view('livewire.supplier.settings.manage-price-rounding-ranges');
    }
}
