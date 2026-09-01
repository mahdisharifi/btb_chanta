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
                    foreach ($item->new_prices as $key => $priceData) {
                        // Parse key: T0|PT1|CP2
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
                                    'price' => $cleanPrice,
                                    'is_active' => true,
                                ]
                            );
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
