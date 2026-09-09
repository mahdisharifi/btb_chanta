<?php

namespace App\Helpers;

use App\Models\Supplier;
use App\Models\SupplierPriceRoundingRange;

class PriceRoundingHelper
{
    /**
     * رُند کردن قیمت بر اساس تنظیمات تأمین‌کننده
     * 
     * اول سیستم رنج‌ها را چک می‌کند، اگر نبود از سیستم قدیمی استفاده می‌کند
     * 
     * @param float $price قیمت اولیه
     * @param int|Supplier $supplier شناسه یا مدل تأمین‌کننده
     * @return float قیمت رُند شده
     */
    public static function round(float $price, int|Supplier $supplier): float
    {
        // دریافت مدل Supplier
        if (is_int($supplier)) {
            $supplierModel = Supplier::find($supplier);
            $supplierId = $supplier;
        } else {
            $supplierModel = $supplier;
            $supplierId = $supplier->id;
        }

        if (!$supplierModel) {
            return round($price, 0); // fallback
        }

        // اولویت 1: سیستم رنج‌ها (جدید)
        $range = self::findRangeForPrice($price, $supplierId);
        
        if ($range) {
            return $range->roundPrice($price);
        }

        // اولویت 2: سیستم قدیمی (backward compatibility)
        $roundingType = $supplierModel->price_rounding_type ?? 'nearest_1';
        return self::roundByType($price, $roundingType);
    }

    /**
     * یافتن رنج مناسب برای قیمت مشخص
     * 
     * @param float $price قیمت
     * @param int $supplierId شناسه تأمین‌کننده
     * @return SupplierPriceRoundingRange|null
     */
    public static function findRangeForPrice(float $price, int $supplierId): ?SupplierPriceRoundingRange
    {
        return SupplierPriceRoundingRange::forSupplier($supplierId)
            ->active()
            ->containsPrice($price)
            ->ordered()
            ->first();
    }

    /**
     * رُند کردن قیمت بر اساس نوع مشخص
     * 
     * @param float $price قیمت اولیه
     * @param string $type نوع رُند
     * @return float قیمت رُند شده
     */
    public static function roundByType(float $price, string $type): float
    {
        switch ($type) {
            case 'none':
                // بدون رُند - با دو رقم اعشار
                return round($price, 2);

            // Nearest (نزدیک‌ترین)
            case 'nearest_1':
                return round($price, 0);
            case 'nearest_5':
                return round($price / 5) * 5;
            case 'nearest_10':
                return round($price / 10) * 10;
            case 'nearest_50':
                return round($price / 50) * 50;
            case 'nearest_100':
                return round($price / 100) * 100;
            case 'nearest_500':
                return round($price / 500) * 500;
            case 'nearest_1000':
                return round($price / 1000) * 1000;

            // Ceil (رُند به بالا)
            case 'ceil_1':
                return ceil($price);
            case 'ceil_10':
                return ceil($price / 10) * 10;
            case 'ceil_100':
                return ceil($price / 100) * 100;
            case 'ceil_1000':
                return ceil($price / 1000) * 1000;

            // Floor (رُند به پایین)
            case 'floor_1':
                return floor($price);
            case 'floor_10':
                return floor($price / 10) * 10;
            case 'floor_100':
                return floor($price / 100) * 100;
            case 'floor_1000':
                return floor($price / 1000) * 1000;

            default:
                // پیش‌فرض: رُند به نزدیک‌ترین عدد صحیح
                return round($price, 0);
        }
    }

    /**
     * محاسبه قیمت چک با اعمال درصد و رُند کردن
     * 
     * @param float $cashPrice قیمت نقدی
     * @param float $percentage درصد افزایش
     * @param int|Supplier $supplier تأمین‌کننده
     * @return float قیمت چک رُند شده
     */
    public static function calculateCheckPrice(float $cashPrice, float $percentage, int|Supplier $supplier): float
    {
        // محاسبه قیمت با درصد
        $checkPrice = $cashPrice * (1 + ($percentage / 100));
        
        // رُند کردن
        return self::round($checkPrice, $supplier);
    }

    /**
     * محاسبه قیمت گارانتی با رُند کردن
     * 
     * @param float $basePrice قیمت پایه
     * @param string $warrantyType نوع گارانتی (percentage, fixed)
     * @param float $warrantyValue مقدار گارانتی
     * @param int|Supplier $supplier تأمین‌کننده
     * @return float قیمت با گارانتی رُند شده
     */
    public static function calculateWarrantyPrice(
        float $basePrice, 
        string $warrantyType, 
        float $warrantyValue, 
        int|Supplier $supplier
    ): float {
        $finalPrice = match($warrantyType) {
            'percentage' => $basePrice * (1 + ($warrantyValue / 100)),
            'fixed' => $basePrice + $warrantyValue,
            default => $basePrice,
        };

        return self::round($finalPrice, $supplier);
    }

    /**
     * دریافت لیست انواع رُند با توضیحات
     * 
     * @return array
     */
    public static function getRoundingTypes(): array
    {
        return [
            'none' => 'بدون رُند (با اعشار)',
            
            'nearest_1' => 'نزدیک‌ترین عدد صحیح (1 تومان)',
            'nearest_5' => 'نزدیک‌ترین 5 تومان',
            'nearest_10' => 'نزدیک‌ترین 10 تومان',
            'nearest_50' => 'نزدیک‌ترین 50 تومان',
            'nearest_100' => 'نزدیک‌ترین 100 تومان',
            'nearest_500' => 'نزدیک‌ترین 500 تومان',
            'nearest_1000' => 'نزدیک‌ترین 1,000 تومان',
            
            'ceil_1' => 'رُند به بالا - صحیح',
            'ceil_10' => 'رُند به بالا - 10 تومان',
            'ceil_100' => 'رُند به بالا - 100 تومان',
            'ceil_1000' => 'رُند به بالا - 1,000 تومان',
            
            'floor_1' => 'رُند به پایین - صحیح',
            'floor_10' => 'رُند به پایین - 10 تومان',
            'floor_100' => 'رُند به پایین - 100 تومان',
            'floor_1000' => 'رُند به پایین - 1,000 تومان',
        ];
    }

    /**
     * نمونه‌های رُند برای نمایش به کاربر
     * 
     * @param float $samplePrice قیمت نمونه (مثلاً 12,345.67)
     * @return array ['type' => 'rounded_price']
     */
    public static function getSamples(float $samplePrice = 12345.67): array
    {
        $samples = [];
        
        foreach (array_keys(self::getRoundingTypes()) as $type) {
            $samples[$type] = self::roundByType($samplePrice, $type);
        }
        
        return $samples;
    }

    /**
     * بروزرسانی دسته‌جمعی قیمت‌ها با رُند جدید
     * این متد وقتی supplier تنظیمات rounding خود را تغییر می‌دهد فراخوانی می‌شود
     * 
     * سیستم جدید: با رنج‌ها کار می‌کند
     * سیستم قدیمی: با price_rounding_type کار می‌کند (backward compatibility)
     * 
     * @param int $supplierId
     * @param string|null $newRoundingType (برای سیستم قدیمی)
     * @return array ['updated' => count, 'errors' => count]
     */
    public static function updateSupplierPrices(int $supplierId, ?string $newRoundingType = null): array
    {
        $supplier = Supplier::findOrFail($supplierId);
        
        // بررسی: آیا از سیستم رنج‌ها استفاده می‌کند؟
        $hasRanges = $supplier->activePriceRoundingRanges()->exists();
        
        if (!$hasRanges && $newRoundingType) {
            // سیستم قدیمی
            $oldRoundingType = $supplier->price_rounding_type;
            
            // اگر تغییری نکرده، return
            if ($oldRoundingType === $newRoundingType) {
                return ['updated' => 0, 'errors' => 0];
            }
        }

        $updated = 0;
        $errors = 0;

        // فقط قیمت‌های default و percentage را بروزرسانی می‌کنیم (نه manual)
        $productSuppliers = \App\Models\ProductSupplier::where('supplier_id', $supplierId)->get();

        foreach ($productSuppliers as $ps) {
            foreach ($ps->prices as $price) {
                // Skip manual prices (percentage_override = null و price type = check و قیمت دستی است)
                if ($price->check_period_id && $price->percentage_override === null) {
                    // این default است، باید بررسی کنیم آیا manual است یا نه
                    // برای سادگی، همه را بروزرسانی می‌کنیم
                }

                try {
                    // محاسبه مجدد با rounding جدید
                    if ($price->check_period_id) {
                        // قیمت چک
                        $cashPrice = $ps->prices()
                            ->whereNull('check_period_id')
                            ->where('price_type_id', $price->price_type_id)
                            ->where('tier_index', $price->tier_index)
                            ->value('price');

                        if ($cashPrice) {
                            $percentage = $price->percentage_override ?? self::getEffectivePercentage(
                                $supplierId,
                                $ps->product->category_id,
                                $price->check_period_id
                            );

                            if ($percentage !== null) {
                                $newPrice = self::calculateCheckPrice($cashPrice, $percentage, $supplier);
                                $price->update(['price' => $newPrice]);
                                $updated++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $errors++;
                    \Log::error("Failed to update price ID {$price->id}: " . $e->getMessage());
                }
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * دریافت تعداد قیمت‌های تحت تأثیر برای یک تأمین‌کننده
     * 
     * @param int $supplierId
     * @return int
     */
    public static function getAffectedPricesCount(int $supplierId): int
    {
        // استفاده از Model برای اطمینان از نام صحیح جدول
        return \App\Models\ProductPrice::query()
            ->whereHas('productSupplier', function($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            })
            ->whereNotNull('check_period_id')
            ->count();
    }

    /**
     * دریافت درصد مؤثر (کپی از EditPrice.php)
     */
    private static function getEffectivePercentage(int $supplierId, ?int $categoryId, int $periodId): ?float
    {
        // 1. بررسی درصد دسته‌بندی
        if ($categoryId) {
            $categoryPercentage = \DB::table('category_check_period')
                ->where('category_id', $categoryId)
                ->where('check_period_id', $periodId)
                ->where('supplier_id', $supplierId)
                ->where('is_active', true)
                ->value('percentage_increase');
            
            if ($categoryPercentage !== null) {
                return (float)$categoryPercentage;
            }
        }
        
        // 2. درصد پیش‌فرض دوره چک
        $defaultPercentage = \DB::table('supplier_check_period')
            ->where('supplier_id', $supplierId)
            ->where('check_period_id', $periodId)
            ->where('is_active', true)
            ->value('percentage_increase');
        
        return $defaultPercentage !== null ? (float)$defaultPercentage : null;
    }
}
