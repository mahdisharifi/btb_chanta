<?php

namespace App\Livewire\Supplier\Settings;

use App\Helpers\PriceRoundingHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.supplier')]
class ManagePriceRounding extends Component
{
    public string $price_rounding_type = 'nearest_1';
    public array $roundingSamples = [];
    public bool $showConfirmModal = false;
    public array $affectedPricesCount = [];

    public function mount()
    {
        $supplier = Auth::user()->supplier;
        $this->price_rounding_type = $supplier->price_rounding_type ?? 'nearest_1';
        $this->updateSamples();
        $this->calculateAffectedPrices();
    }

    /**
     * بروزرسانی نمونه‌های رُند
     */
    public function updateSamples()
    {
        // قیمت‌های نمونه مختلف
        $samplePrices = [12345.67, 9876.54, 5432.10, 15678.90];
        
        $this->roundingSamples = [];
        
        foreach ($samplePrices as $price) {
            $this->roundingSamples[] = [
                'original' => $price,
                'rounded' => PriceRoundingHelper::roundByType($price, $this->price_rounding_type),
            ];
        }
    }

    /**
     * محاسبه تعداد قیمت‌های تحت تأثیر
     */
    public function calculateAffectedPrices()
    {
        $supplierId = Auth::user()->supplier->id;

        // تعداد قیمت‌های default
        $defaultCount = DB::table('product_supplier_prices as psp')
            ->join('product_suppliers as ps', 'psp.product_supplier_id', '=', 'ps.id')
            ->join('price_types as pt', 'psp.price_type_id', '=', 'pt.id')
            ->where('ps.supplier_id', $supplierId)
            ->where('pt.code', 'check')
            ->whereNotNull('psp.check_period_id')
            ->whereNull('psp.percentage_override')
            ->count();

        // تعداد قیمت‌های percentage
        $percentageCount = DB::table('product_supplier_prices as psp')
            ->join('product_suppliers as ps', 'psp.product_supplier_id', '=', 'ps.id')
            ->join('price_types as pt', 'psp.price_type_id', '=', 'pt.id')
            ->where('ps.supplier_id', $supplierId)
            ->where('pt.code', 'check')
            ->whereNotNull('psp.check_period_id')
            ->whereNotNull('psp.percentage_override')
            ->count();

        $this->affectedPricesCount = [
            'default' => $defaultCount,
            'percentage' => $percentageCount,
            'total' => $defaultCount + $percentageCount,
        ];
    }

    /**
     * وقتی نوع rounding تغییر می‌کند
     */
    public function updatedPriceRoundingType($value)
    {
        $this->updateSamples();
    }

    /**
     * نمایش modal تایید
     */
    public function confirmUpdate()
    {
        $this->showConfirmModal = true;
    }

    /**
     * لغو بروزرسانی
     */
    public function cancelUpdate()
    {
        $this->showConfirmModal = false;
        
        // بازگشت به مقدار قبلی
        $supplier = Auth::user()->supplier;
        $this->price_rounding_type = $supplier->price_rounding_type ?? 'nearest_1';
        $this->updateSamples();
    }

    /**
     * ذخیره و بروزرسانی قیمت‌ها
     */
    public function save()
    {
        $supplier = Auth::user()->supplier;
        $oldRoundingType = $supplier->price_rounding_type;

        // اگر تغییری نکرده
        if ($oldRoundingType === $this->price_rounding_type) {
            $this->dispatch('notify', 
                message: 'تنظیمات بدون تغییر است.',
                type: 'info'
            );
            return;
        }

        DB::transaction(function () use ($supplier, $oldRoundingType) {
            // 1. بروزرسانی تنظیمات
            $supplier->update([
                'price_rounding_type' => $this->price_rounding_type,
            ]);

            // 2. بروزرسانی قیمت‌های موجود
            $result = PriceRoundingHelper::updateSupplierPrices(
                $supplier->id, 
                $this->price_rounding_type
            );

            // Log
            \Log::info('Price rounding updated', [
                'supplier_id' => $supplier->id,
                'old_type' => $oldRoundingType,
                'new_type' => $this->price_rounding_type,
                'updated_prices' => $result['updated'],
                'errors' => $result['errors'],
            ]);

            $this->showConfirmModal = false;

            if ($result['errors'] > 0) {
                $this->dispatch('notify', 
                    message: "تنظیمات ذخیره شد. {$result['updated']} قیمت بروز شد، {$result['errors']} خطا.",
                    type: 'warning'
                );
            } else {
                $this->dispatch('notify', 
                    message: "تنظیمات با موفقیت ذخیره شد و {$result['updated']} قیمت بروزرسانی شد.",
                    type: 'success'
                );
            }
        });

        // بارگذاری مجدد
        $this->calculateAffectedPrices();
    }

    public function render()
    {
        return view('livewire.supplier.settings.manage-price-rounding', [
            'roundingTypes' => PriceRoundingHelper::getRoundingTypes(),
        ]);
    }
}
