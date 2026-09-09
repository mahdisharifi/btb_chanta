<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierTierPercentage extends Model
{
    protected $fillable = [
        'supplier_id',
        'product_id',
        'tier_index',
        'tier_label',
        'percentage',
        'is_reference',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'tier_index' => 'integer',
        'percentage' => 'decimal:2',
        'is_reference' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relation: تیراژ متعلق به Supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relation: تیراژ متعلق به Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope: فقط تیراژهای فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: مرتب‌سازی بر اساس sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('tier_index', 'asc');
    }

    /**
     * Scope: برای یک supplier خاص
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope: دریافت تیراژ مرجع
     */
    public function scopeReference($query)
    {
        return $query->where('is_reference', true);
    }

    /**
     * محاسبه قیمت بر اساس درصد
     * 
     * @param float $referencePrice قیمت تیراژ مرجع (کارتن)
     * @return float قیمت محاسبه شده
     */
    public function calculatePrice(float $referencePrice): float
    {
        if ($this->is_reference) {
            return $referencePrice;
        }
        
        // percentage > 0 = افزایش (گرانتر)
        // percentage < 0 = کاهش (ارزانتر/تخفیف)
        return $referencePrice * (1 + ($this->percentage / 100));
    }

    /**
     * دریافت label نمایشی
     */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->tier_label) {
            return $this->tier_label;
        }
        
        return 'تیراژ ' . ($this->tier_index + 1);
    }

    /**
     * دریافت متن درصد با علامت
     */
    public function getPercentageTextAttribute(): string
    {
        if ($this->is_reference) {
            return 'مرجع (0%)';
        }
        
        $sign = $this->percentage >= 0 ? '+' : '';
        return $sign . number_format($this->percentage, 1) . '%';
    }

    /**
     * دریافت توضیح نوع تغییر
     */
    public function getChangeTypeAttribute(): string
    {
        if ($this->is_reference) {
            return 'مرجع';
        }
        
        if ($this->percentage > 0) {
            return 'افزایش';
        } elseif ($this->percentage < 0) {
            return 'تخفیف';
        }
        
        return 'بدون تغییر';
    }

    /**
     * دریافت تیراژ مرجع برای یک تامین‌کننده
     */
    public static function getReferenceForSupplier(int $supplierId): ?self
    {
        return static::forSupplier($supplierId)
            ->reference()
            ->active()
            ->first();
    }

    /**
     * محاسبه قیمت یک تیراژ بر اساس قیمت مرجع
     */
    public static function calculateTierPrice(int $supplierId, int $tierIndex, float $referencePrice): float
    {
        $tierSetting = static::forSupplier($supplierId)
            ->where('tier_index', $tierIndex)
            ->active()
            ->first();
        
        if (!$tierSetting) {
            // اگر تنظیمی وجود ندارد، قیمت مرجع برگردانده می‌شود
            return $referencePrice;
        }
        
        return $tierSetting->calculatePrice($referencePrice);
    }

    /**
     * بررسی اینکه آیا تنظیمات کامل است (حداقل یک تیراژ مرجع وجود دارد)
     */
    public static function isConfiguredForSupplier(int $supplierId): bool
    {
        return static::forSupplier($supplierId)
            ->reference()
            ->active()
            ->exists();
    }

    /**
     * دریافت validation rules
     */
    public static function validationRules(): array
    {
        return [
            'tier_index' => ['required', 'integer', 'min:0', 'max:10'],
            'tier_label' => ['nullable', 'string', 'max:100'],
            'percentage' => ['required', 'numeric', 'min:-100', 'max:500'],
            'is_reference' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * دریافت validation messages
     */
    public static function validationMessages(): array
    {
        return [
            'tier_index.required' => 'شماره تیراژ الزامی است.',
            'tier_index.min' => 'شماره تیراژ نمی‌تواند منفی باشد.',
            'tier_index.max' => 'حداکثر 10 تیراژ مجاز است.',
            'tier_label.max' => 'نام تیراژ نمی‌تواند بیشتر از 100 کاراکتر باشد.',
            'percentage.required' => 'درصد الزامی است.',
            'percentage.numeric' => 'درصد باید عدد باشد.',
            'percentage.min' => 'درصد نمی‌تواند کمتر از -100 باشد.',
            'percentage.max' => 'درصد نمی‌تواند بیشتر از 500 باشد.',
        ];
    }
}
