<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceUpdateItem extends Model
{
    protected $fillable = [
        'price_update_batch_id',
        'product_supplier_id',
        'old_prices',
        'new_prices',
        'old_stock',
        'new_stock',
        'old_min_order_quantity',
        'new_min_order_quantity',
        'old_is_active',
        'new_is_active',
        'status',
        'error_message',
    ];

    protected $casts = [
        'old_prices' => 'array',
        'new_prices' => 'array',
        'old_stock' => 'integer',
        'new_stock' => 'integer',
        'old_min_order_quantity' => 'integer',
        'new_min_order_quantity' => 'integer',
        'old_is_active' => 'boolean',
        'new_is_active' => 'boolean',
    ];

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS
    // ══════════════════════════════════════════════════════════

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PriceUpdateBatch::class, 'price_update_batch_id');
    }

    public function productSupplier(): BelongsTo
    {
        return $this->belongsTo(ProductSupplier::class);
    }

    // ══════════════════════════════════════════════════════════
    //  HELPER METHODS - ماتریس قیمت جدید
    // ══════════════════════════════════════════════════════════

    /**
     * دریافت تغییرات قیمت به صورت قابل خواندن
     * فرمت ماتریس: ['T0|PT1|CP2' => ['price' => 1000, 'tier_index' => 0, ...]]
     * 
     * @return array
     */
    public function getPriceChanges(): array
    {
        $oldPrices = $this->old_prices ?? [];
        $newPrices = $this->new_prices ?? [];
        
        $changes = [];
        
        // قیمت‌های جدید یا تغییر یافته
        foreach ($newPrices as $key => $newData) {
            $oldPrice = isset($oldPrices[$key]) ? $oldPrices[$key]['price'] : null;
            $newPrice = $newData['price'];
            
            if ($oldPrice !== $newPrice) {
                $changes[$key] = [
                    'tier_index' => $newData['tier_index'],
                    'price_type_id' => $newData['price_type_id'],
                    'check_period_id' => $newData['check_period_id'] ?? null,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'change_amount' => $newPrice - ($oldPrice ?? 0),
                    'change_percent' => $oldPrice ? round((($newPrice - $oldPrice) / $oldPrice) * 100, 2) : null,
                    'is_new' => $oldPrice === null,
                ];
            }
        }
        
        // قیمت‌های حذف شده (در old هست اما در new نیست)
        foreach ($oldPrices as $key => $oldData) {
            if (!isset($newPrices[$key])) {
                $changes[$key] = [
                    'tier_index' => $oldData['tier_index'],
                    'price_type_id' => $oldData['price_type_id'],
                    'check_period_id' => $oldData['check_period_id'] ?? null,
                    'old_price' => $oldData['price'],
                    'new_price' => null,
                    'change_amount' => -$oldData['price'],
                    'change_percent' => -100,
                    'is_deleted' => true,
                ];
            }
        }
        
        return $changes;
    }

    /**
     * بررسی اینکه آیا تغییری وجود دارد یا خیر
     * 
     * @return bool
     */
    public function hasAnyChanges(): bool
    {
        return !empty($this->getPriceChanges())
            || $this->old_stock !== $this->new_stock
            || $this->old_min_order_quantity !== $this->new_min_order_quantity
            || $this->old_is_active !== $this->new_is_active;
    }

    /**
     * تعداد قیمت‌های تغییر یافته
     * 
     * @return int
     */
    public function getPriceChangeCount(): int
    {
        return count($this->getPriceChanges());
    }

    /**
     * دریافت خلاصه تغییرات به صورت متنی
     * 
     * @return string
     */
    public function getChangesSummary(): string
    {
        $parts = [];
        
        $priceChanges = $this->getPriceChanges();
        if (!empty($priceChanges)) {
            $parts[] = count($priceChanges) . ' قیمت';
        }
        
        if ($this->new_stock !== null && $this->old_stock !== $this->new_stock) {
            $parts[] = 'موجودی';
        }
        
        if ($this->new_min_order_quantity !== null && $this->old_min_order_quantity !== $this->new_min_order_quantity) {
            $parts[] = 'تعداد حداقل';
        }
        
        if ($this->new_is_active !== null && $this->old_is_active !== $this->new_is_active) {
            $parts[] = 'وضعیت';
        }
        
        return empty($parts) ? 'بدون تغییر' : implode('، ', $parts);
    }

    /**
     * دریافت قیمت خاص از ماتریس
     * 
     * @param int $tierIndex
     * @param int $priceTypeId
     * @param int|null $checkPeriodId
     * @param string $source 'old' or 'new'
     * @return int|null
     */
    public function getPrice(int $tierIndex, int $priceTypeId, ?int $checkPeriodId = null, string $source = 'new'): ?int
    {
        $key = sprintf('T%d|PT%d|CP%d', $tierIndex, $priceTypeId, $checkPeriodId ?? 0);
        $prices = $source === 'old' ? ($this->old_prices ?? []) : ($this->new_prices ?? []);
        
        return $prices[$key]['price'] ?? null;
    }

    /**
     * تبدیل فرمت قدیمی قیمت‌ها (اگر لازم باشد) به فرمت جدید
     * این متد برای سازگاری با داده‌های قدیمی است
     * 
     * @param array $oldFormatPrices فرمت: ['cash' => 1000, 'check' => 1100]
     * @return array فرمت جدید: ['T0|PT1|CP0' => ['price' => 1000, ...]]
     */
    public static function convertLegacyPriceFormat(array $oldFormatPrices): array
    {
        $newFormat = [];
        
        // نقشه تبدیل (فرض: تیراژ 0، بدون دوره چک)
        $priceTypeMap = [
            'cash' => 1,      // فرض: PriceType با ID=1 برای نقدی
            'check' => 2,     // فرض: PriceType با ID=2 برای چکی
            'warranty' => 3,  // فرض: PriceType با ID=3 برای گارانتی
        ];
        
        foreach ($oldFormatPrices as $code => $price) {
            if (isset($priceTypeMap[$code])) {
                $key = sprintf('T0|PT%d|CP0', $priceTypeMap[$code]);
                $newFormat[$key] = [
                    'price' => (int)$price,
                    'tier_index' => 0,
                    'price_type_id' => $priceTypeMap[$code],
                    'check_period_id' => null,
                ];
            }
        }
        
        return $newFormat;
    }

    /**
     * بررسی اینکه آیا این آیتم از فرمت قدیمی استفاده می‌کند
     * 
     * @return bool
     */
    public function isLegacyFormat(): bool
    {
        $oldPrices = $this->old_prices ?? [];
        
        // اگر خالی باشد یا کلیدهای آن عددی یا رشته‌ای ساده باشد (مثل 'cash')
        if (empty($oldPrices)) {
            return false;
        }
        
        $firstKey = array_key_first($oldPrices);
        
        // فرمت جدید: 'T0|PT1|CP2'
        // فرمت قدیمی: 'cash' یا 'check'
        return !str_contains((string)$firstKey, '|');
    }
}
