<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model برای تنظیمات درصد اختصاصی تیراژ نقدی برای دسته‌بندی
 * 
 * این مدل مشابه category_check_period است
 */
class CategoryTierDiscount extends Model
{
    use HasFactory;

    protected $table = 'category_tier_discount';

    protected $fillable = [
        'category_id',
        'supplier_id',
        'tier_index',
        'percentage_change',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // ══════════════════════════════════════════════════════════
    //  STATIC METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * دریافت درصد اختصاصی برای یک دسته‌بندی، تامین‌کننده و تیراژ
     * 
     * @param int $categoryId
     * @param int $supplierId
     * @param int $tierIndex
     * @return float|null
     */
    public static function getPercentage(int $categoryId, int $supplierId, int $tierIndex): ?float
    {
        $record = static::where('category_id', $categoryId)
            ->where('supplier_id', $supplierId)
            ->where('tier_index', $tierIndex)
            ->where('is_active', true)
            ->first();
        
        return $record ? $record->percentage_change : null;
    }

    /**
     * دریافت تمام تنظیمات یک دسته‌بندی برای تامین‌کننده
     * 
     * @param int $categoryId
     * @param int $supplierId
     * @return \Illuminate\Support\Collection
     */
    public static function getAllForCategoryAndSupplier(int $categoryId, int $supplierId): \Illuminate\Support\Collection
    {
        return static::where('category_id', $categoryId)
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->orderBy('tier_index')
            ->get();
    }
}
