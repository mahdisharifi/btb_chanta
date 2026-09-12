<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model برای تنظیمات درصد پیشفرض تیراژ نقدی
 * 
 * این مدل مشابه supplier_check_period است
 * و درصد افزایش/کاهش قیمت نقدی هر تیراژ نسبت به تیراژ مرجع را مشخص می‌کند
 */
class SupplierTierDiscount extends Model
{
    use HasFactory;

    protected $table = 'supplier_tier_discount';

    protected $fillable = [
        'supplier_id',
        'tier_index',
        'percentage_change',
        'label',
        'is_active',
    ];

    protected $casts = [
        'tier_index' => 'integer',
        'percentage_change' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS
    // ══════════════════════════════════════════════════════════

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // ══════════════════════════════════════════════════════════
    //  CONSTANTS - نام‌های پیشفرض تیراژها
    // ══════════════════════════════════════════════════════════

    public const TIER_NAMES = [
        0 => 'زیر کارتن',
        1 => 'کارتن (مرجع)',
        2 => 'عمده',
        3 => 'بار',
    ];

    // ══════════════════════════════════════════════════════════
    //  ACCESSORS
    // ══════════════════════════════════════════════════════════

    public function getDefaultNameAttribute(): string
    {
        return self::TIER_NAMES[$this->tier_index] ?? "تیراژ {$this->tier_index}";
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->label ?: $this->default_name;
    }

    // ══════════════════════════════════════════════════════════
    //  STATIC METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * دریافت درصد افزایش/کاهش با سلسله مراتب:
     * 1. اولویت اول: درصد دسته‌بندی (category_tier_discount)
     * 2. اولویت دوم: درصد پیشفرض تامین‌کننده (supplier_tier_discount)
     * 
     * @param int $supplierId
     * @param int $tierIndex
     * @param int|null $categoryId
     * @return float|null
     */
    public static function getEffectivePercentage(int $supplierId, int $tierIndex, ?int $categoryId = null): ?float
    {
        // Priority 1: Category-level percentage
        if ($categoryId) {
            $categoryPercentage = \DB::table('category_tier_discount')
                ->where('category_id', $categoryId)
                ->where('supplier_id', $supplierId)
                ->where('tier_index', $tierIndex)
                ->where('is_active', true)
                ->value('percentage_change');
            
            if ($categoryPercentage !== null) {
                return (float)$categoryPercentage;
            }
        }
        
        // Priority 2: Supplier default percentage
        $supplierPercentage = static::where('supplier_id', $supplierId)
            ->where('tier_index', $tierIndex)
            ->where('is_active', true)
            ->value('percentage_change');
        
        return $supplierPercentage !== null ? (float)$supplierPercentage : null;
    }

    /**
     * محاسبه قیمت تیراژ بر اساس قیمت مرجع و درصد تغییر
     * 
     * @param float $referencePrice قیمت تیراژ مرجع (کارتن)
     * @param int $supplierId
     * @param int $tierIndex
     * @param int|null $categoryId
     * @return float|null
     */
    public static function calculateTierPrice(float $referencePrice, int $supplierId, int $tierIndex, ?int $categoryId = null): ?float
    {
        // تیراژ 1 (کارتن) خودش مرجع است
        if ($tierIndex === 1) {
            return $referencePrice;
        }
        
        $percentage = static::getEffectivePercentage($supplierId, $tierIndex, $categoryId);
        
        if ($percentage === null) {
            return null;
        }
        
        return $referencePrice * (1 + ($percentage / 100));
    }

    /**
     * دریافت تمام تنظیمات تیراژ برای یک تامین‌کننده
     * 
     * @param int $supplierId
     * @return \Illuminate\Support\Collection
     */
    public static function getAllForSupplier(int $supplierId): \Illuminate\Support\Collection
    {
        return static::where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->orderBy('tier_index')
            ->get();
    }

    /**
     * دریافت تنظیمات یک تیراژ خاص برای تامین‌کننده
     * 
     * @param int $supplierId
     * @param int $tierIndex
     * @return static|null
     */
    public static function getForSupplierAndTier(int $supplierId, int $tierIndex): ?static
    {
        return static::where('supplier_id', $supplierId)
            ->where('tier_index', $tierIndex)
            ->first();
    }
}
