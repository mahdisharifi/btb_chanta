<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Supplier;
use App\Models\SupplierTierDiscount;
use App\Models\PriceType;

class TierPriceUpdater
{
    /**
     * بروزرسانی قیمت‌های نقدی تیراژها برای یک تامین‌کننده
     * 
     * @param int $supplierId
     * @param int|null $categoryId اگر مشخص باشد، فقط محصولات این دسته‌بندی بروز می‌شوند
     * @return array ['updated' => int, 'errors' => int]
     */
    public static function updateSupplierPrices(int $supplierId, ?int $categoryId = null): array
    {
        $updated = 0;
        $errors = 0;
        
        try {
            DB::beginTransaction();
            
            // دریافت تامین‌کننده
            $supplier = Supplier::find($supplierId);
            if (!$supplier) {
                throw new \Exception("Supplier not found: {$supplierId}");
            }
            
            // دریافت درصدهای پیشفرض
            $tierDiscounts = SupplierTierDiscount::getAllForSupplier($supplierId);
            
            if ($tierDiscounts->isEmpty()) {
                Log::info("No tier discounts found for supplier {$supplierId}");
                return ['updated' => 0, 'errors' => 0];
            }
            
            // دریافت ID نوع قیمت نقدی
            $cashPriceTypeId = PriceType::where('code', 'cash')->value('id');
            if (!$cashPriceTypeId) {
                throw new \Exception('Cash price type not found');
            }
            
            // دریافت تیراژ مرجع (کارتن - tier_index = 1)
            $referenceTierIndex = 1;
            
            // دریافت تمام product_supplier های این تامین‌کننده
            $query = DB::table('product_supplier as ps')
                ->join('products as p', 'ps.product_id', '=', 'p.id')
                ->where('ps.supplier_id', $supplierId)
                ->where('ps.is_active', true)
                ->select('ps.*', 'p.category_id');
            
            // اگر دسته‌بندی مشخص شده، فقط محصولات آن دسته‌بندی
            if ($categoryId) {
                $query->where('p.category_id', $categoryId);
            }
            
            $productSuppliers = $query->get();
            
            foreach ($productSuppliers as $productSupplier) {
                try {
                    // دریافت قیمت مرجع (تیراژ 1 - کارتن)
                    $referencePrice = DB::table('product_prices')
                        ->where('product_supplier_id', $productSupplier->id)
                        ->where('price_type_id', $cashPriceTypeId)
                        ->where('tier_index', $referenceTierIndex)
                        ->where('is_active', true)
                        ->value('price');
                    
                    if (!$referencePrice || !is_numeric($referencePrice)) {
                        continue; // محصول قیمت مرجع ندارد
                    }
                    
                    // بروزرسانی قیمت سایر تیراژها
                    foreach ($tierDiscounts as $tierDiscount) {
                        $tierIndex = $tierDiscount->tier_index;
                        
                        // تیراژ مرجع را skip کن
                        if ($tierIndex == $referenceTierIndex) {
                            continue;
                        }
                        
                        // ✅ اولویت‌بندی: دسته‌بندی > پیشفرض
                        $percentage = self::getEffectivePercentage(
                            $supplierId,
                            $productSupplier->category_id,
                            $tierIndex,
                            (float) $tierDiscount->percentage_change
                        );
                        
                        // محاسبه قیمت جدید
                        $newPrice = $referencePrice * (1 + ($percentage / 100));
                        
                        // رُند کردن
                        $roundedPrice = PriceRoundingHelper::round($newPrice, $supplier);
                        
                        // بروزرسانی یا ایجاد قیمت
                        $existingPrice = DB::table('product_prices')
                            ->where('product_supplier_id', $productSupplier->id)
                            ->where('price_type_id', $cashPriceTypeId)
                            ->where('tier_index', $tierIndex)
                            ->first();
                        
                        if ($existingPrice) {
                            // بروزرسانی
                            DB::table('product_prices')
                                ->where('id', $existingPrice->id)
                                ->update([
                                    'price' => $roundedPrice,
                                    'updated_at' => now(),
                                ]);
                            
                            $updated++;
                        } else {
                            // ایجاد قیمت جدید
                            DB::table('product_prices')->insert([
                                'product_supplier_id' => $productSupplier->id,
                                'price_type_id' => $cashPriceTypeId,
                                'tier_index' => $tierIndex,
                                'price' => $roundedPrice,
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            
                            $updated++;
                        }
                    }
                    
                } catch (\Exception $e) {
                    $errors++;
                    Log::error("Error updating prices for product_supplier {$productSupplier->id}: " . $e->getMessage());
                }
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in TierPriceUpdater: ' . $e->getMessage(), [
                'supplier_id' => $supplierId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
        
        return [
            'updated' => $updated,
            'errors' => $errors,
        ];
    }
    
    /**
     * دریافت درصد مؤثر (با اولویت‌بندی)
     * اولویت: دسته‌بندی > پیشفرض
     * 
     * @param int $supplierId
     * @param int|null $categoryId
     * @param int $tierIndex
     * @param float $defaultPercentage
     * @return float
     */
    private static function getEffectivePercentage(
        int $supplierId,
        ?int $categoryId,
        int $tierIndex,
        float $defaultPercentage
    ): float {
        // 1. بررسی درصد اختصاصی دسته‌بندی
        if ($categoryId) {
            $categoryDiscount = DB::table('category_tier_discount')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->where('tier_index', $tierIndex)
                ->where('is_active', true)
                ->value('percentage_change');
            
            if ($categoryDiscount !== null) {
                return (float) $categoryDiscount;
            }
        }
        
        // 2. استفاده از درصد پیشفرض
        return $defaultPercentage;
    }
}
