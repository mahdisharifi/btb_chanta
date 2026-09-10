<?php

namespace App\Livewire\Supplier\FastPricing;

use App\Models\PriceUpdateBatch;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.supplier')]
class Review extends Component
{
    use WithPagination;

    public $batchId;
    public $batch;

    public function mount($batchId)
    {
        $this->batchId = $batchId;
        $this->batch = $this->getBatch();
    }

    protected function getBatch()
    {
        return PriceUpdateBatch::where('id', $this->batchId)
            ->where('supplier_id', Auth::user()->supplier->id)
            ->where('status', 'pending')
            ->firstOrFail();
    }

    public function commitChanges()
    {
        $batch = $this->getBatch();

        DB::beginTransaction();
        try {
            $validItems = $batch->items()->where('status', 'valid')->get();

            foreach ($validItems as $item) {
                $productSupplier = ProductSupplier::find($item->product_supplier_id);
                if (!$productSupplier) {
                    continue;
                }

                // ═══════════════════════════════════════════════════════════
                // ۱. بروزرسانی قیمت‌ها با ساختار ماتریس جدید
                // ═══════════════════════════════════════════════════════════
                if (is_array($item->new_prices) && !empty($item->new_prices)) {
                    // ✅ دریافت تنظیمات auto-calculate
                    $autoCalculate = $productSupplier->auto_calculate_tiers ?? false;
                    $tierPercentages = $productSupplier->tier_percentages ?? [];
                    
                    // ✅ Step 1: ذخیره قیمت‌های نقدی (manual) که کاربر وارد کرده
                    $cashPrices = []; // [tier_index] => price
                    
                    foreach ($item->new_prices as $key => $priceData) {
                        if (!is_array($priceData) || !isset($priceData['price'])) {
                            continue;
                        }

                        $tierIndex = $priceData['tier_index'];
                        $priceTypeId = $priceData['price_type_id'];
                        $checkPeriodId = $priceData['check_period_id'] ?? null;
                        $priceValue = $priceData['price'];

                        // اگر قیمت خالی است، رد شو
                        if (is_null($priceValue) || $priceValue === '') {
                            continue;
                        }

                        // پاکسازی قیمت از کاراکترهای احتمالی
                        $cleanPrice = (int)str_replace([',', '،', ' '], '', (string)$priceValue);

                        // ✅ شناسایی قیمت نقدی (price_type_id = 1 معمولاً نقدی است)
                        // بهتر است با code چک کنیم
                        $priceType = \App\Models\PriceType::find($priceTypeId);
                        if ($priceType && $priceType->code === 'cash' && $cleanPrice > 0) {
                            $cashPrices[$tierIndex] = $cleanPrice;
                        }

                        if ($cleanPrice <= 0) {
                            // اگر کاربر صراحتاً 0 وارد کرده بود، قیمت را غیرفعال کن
                            ProductPrice::where('product_supplier_id', $productSupplier->id)
                                ->where('tier_index', $tierIndex)
                                ->where('price_type_id', $priceTypeId)
                                ->where(function($q) use ($checkPeriodId) {
                                    if ($checkPeriodId) {
                                        $q->where('check_period_id', $checkPeriodId);
                                    } else {
                                        $q->whereNull('check_period_id');
                                    }
                                })
                                ->update(['is_active' => false, 'price' => 0]);
                        } else {
                            // ✅ رند کردن قیمت manual هم
                            $roundedManualPrice = \App\Helpers\PriceRoundingHelper::round($cleanPrice, $productSupplier->supplier_id);
                            
                            // ثبت یا بروزرسانی قیمت معتبر
                            ProductPrice::updateOrCreate(
                                [
                                    'product_supplier_id' => $productSupplier->id,
                                    'tier_index' => (int)$tierIndex,
                                    'price_type_id' => (int)$priceTypeId,
                                    'check_period_id' => $checkPeriodId,
                                ],
                                [
                                    'product_id' => $productSupplier->product_id,
                                    'price' => (int)$roundedManualPrice, // ✅ قیمت رند شده
                                    'is_active' => true,
                                ]
                            );
                        }
                    }
                    
                    // ✅ Step 2: اگر auto_calculate فعال است، قیمت‌های چکی را محاسبه کن
                    if ($autoCalculate && !empty($cashPrices)) {
                        Log::info('Auto-calculating check prices', [
                            'product_supplier_id' => $productSupplier->id,
                            'cash_prices' => $cashPrices,
                        ]);
                        
                        // دریافت تمام price types چکی
                        $checkPriceTypes = \App\Models\PriceType::where('code', 'check')
                            ->where('is_active', true)
                            ->get();
                        
                        // ✅ فقط دوره‌های چکی فعال شده توسط supplier با اطلاعات pivot
                        $checkPeriods = \App\Models\CheckPeriod::whereHas('suppliers', function($query) use ($productSupplier) {
                                $query->where('supplier_id', $productSupplier->supplier_id)
                                      ->where('supplier_check_period.is_active', true);
                            })
                            ->with(['suppliers' => function($query) use ($productSupplier) {
                                $query->where('supplier_id', $productSupplier->supplier_id);
                            }])
                            ->active()
                            ->get();
                        
                        // ✅ دریافت دوره‌های چکی که قبلاً برای این محصول در دیتابیس وجود دارند
                        $existingCheckPrices = ProductPrice::where('product_supplier_id', $productSupplier->id)
                            ->whereNotNull('check_period_id')
                            ->get()
                            ->keyBy(function($price) {
                                return $price->tier_index . '_' . $price->check_period_id;
                            });
                        
                        foreach ($cashPrices as $tierIndex => $cashPrice) {
                            // محاسبه قیمت برای هر دوره چک
                            foreach ($checkPriceTypes as $checkPriceType) {
                                foreach ($checkPeriods as $checkPeriod) {
                                    $checkKey = $tierIndex . '_' . $checkPeriod->id;
                                    
                                    // فقط قیمت‌هایی که کاربر manually وارد نکرده
                                    $existingInUpload = collect($item->new_prices)->first(function($p) use ($tierIndex, $checkPriceType, $checkPeriod) {
                                        return isset($p['tier_index']) && $p['tier_index'] == $tierIndex
                                            && isset($p['price_type_id']) && $p['price_type_id'] == $checkPriceType->id
                                            && isset($p['check_period_id']) && $p['check_period_id'] == $checkPeriod->id;
                                    });
                                    
                                    // اگر کاربر این قیمت را manual وارد کرده، skip
                                    if ($existingInUpload && !empty($existingInUpload['price'])) {
                                        continue;
                                    }
                                    
                                    // ✅ بررسی: آیا این دوره چک برای این تیراژ قبلاً فعال بوده؟
                                    // اگر رکوردی در دیتابیس وجود داشته باشد، یعنی فعال بوده
                                    // اگر رکوردی در آپلود باشد، یعنی کاربر می‌خواهد فعال شود
                                    // اگر هیچکدام نباشد، یعنی کاربر آن را غیرفعال کرده → skip
                                    $wasEnabledBefore = $existingCheckPrices->has($checkKey);
                                    $isEnabledInUpload = $existingInUpload !== null;
                                    
                                    if (!$wasEnabledBefore && !$isEnabledInUpload) {
                                        // این دوره چک برای این تیراژ غیرفعال است → skip
                                        continue;
                                    }
                                    
                                    // ✅ دریافت درصد اختصاصی این دوره چک از pivot
                                    $checkPercentage = 0;
                                    if ($checkPeriod->suppliers->isNotEmpty()) {
                                        $pivotData = $checkPeriod->suppliers->first()->pivot;
                                        $checkPercentage = $pivotData->percentage_increase ?? 0;
                                    }
                                    
                                    // ✅ فرمول صحیح: قیمت چک = قیمت نقدی همان تیراژ × (1 + درصد دوره چک)
                                    $calculatedPrice = $cashPrice * (1 + $checkPercentage / 100);
                                    
                                    // ✅ رند کردن قیمت بر اساس تنظیمات supplier
                                    $roundedPrice = \App\Helpers\PriceRoundingHelper::round($calculatedPrice, $productSupplier->supplier_id);
                                    
                                    // محاسبه و ثبت
                                    ProductPrice::updateOrCreate(
                                        [
                                            'product_supplier_id' => $productSupplier->id,
                                            'tier_index' => (int)$tierIndex,
                                            'price_type_id' => (int)$checkPriceType->id,
                                            'check_period_id' => (int)$checkPeriod->id,
                                        ],
                                        [
                                            'product_id' => $productSupplier->product_id,
                                            'price' => (int)$roundedPrice,
                                            'is_active' => true,
                                        ]
                                    );
                                }
                            }
                        }
                    }
                }

                // ═══════════════════════════════════════════════════════════
                // ۲. بروزرسانی موجودی (فقط در صورت داشتن مقدار)
                // ═══════════════════════════════════════════════════════════
                if ($item->new_stock !== null && $item->new_stock !== '') {
                    $productSupplier->stock_quantity = (int) $item->new_stock;
                }

                // ═══════════════════════════════════════════════════════════
                // ۳. بروزرسانی حداقل تعداد سفارش (فقط در صورت داشتن مقدار)
                // ═══════════════════════════════════════════════════════════
                if ($item->new_min_order_quantity !== null && $item->new_min_order_quantity !== '') {
                    $productSupplier->min_order_quantity = (int) $item->new_min_order_quantity;
                }

                // ═══════════════════════════════════════════════════════════
                // ۴. بروزرسانی وضعیت صریح
                // ═══════════════════════════════════════════════════════════
                if ($item->new_is_active !== null && $item->new_is_active !== '') {
                    $productSupplier->is_active = (bool) $item->new_is_active;
                }

                $productSupplier->save();
            }

            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            
            DB::commit();

            session()->flash('success', 'تغییرات با موفقیت بر روی محصولات اعمال شد.');
            return $this->redirect(route('supplier.product-selection'), navigate: true);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error committing price update (matrix): ' . $e->getMessage(), [
                'batch_id' => $batch->id,
                'trace' => $e->getTraceAsString()
            ]);
            $this->addError('commit', 'خطا در ثبت نهایی: ' . $e->getMessage());
        }
    }

    public function cancelBatch()
    {
        $batch = $this->getBatch();

        DB::beginTransaction();
        try {
            $batch->items()->delete();
            $batch->delete();
            DB::commit();

            session()->flash('success', 'عملیات بروزرسانی لغو شد.');
            return $this->redirect(route('supplier.fast-pricing.import'), navigate: true);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling price update batch: ' . $e->getMessage());
            $this->addError('commit', 'خطایی در هنگام لغو عملیات رخ داد.');
        }
    }

    public function render()
    {
        $batch = $this->getBatch();
        $items = $batch->items()
            ->with(['productSupplier.product', 'productSupplier.prices'])
            ->paginate(15);

        $totalRecords = $batch->items()->count();
        $validRecords = $batch->items()->where('status', 'valid')->count();
        $invalidRecords = $batch->items()->where('status', 'invalid')->count();

        return view('livewire.supplier.fast-pricing.review', compact('items', 'totalRecords', 'validRecords', 'invalidRecords'));
    }
}
