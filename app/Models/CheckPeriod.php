<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class CheckPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'supplier_id',
        'scope',
        'days',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'days' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS
    // ══════════════════════════════════════════════════════════

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * تامین‌کنندگانی که این دوره چک را فعال کرده‌اند
     * با اطلاعات درصد افزایش قیمت و عنوان اختصاصی از جدول pivot
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_check_period')
            ->withPivot('percentage_increase', 'is_active', 'label')
            ->withTimestamps();
    }

    /**
     * دسته‌بندی‌هایی که برای آن‌ها درصد اختصاصی تعیین شده
     * با اطلاعات درصد و تامین‌کننده از جدول pivot
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_check_period')
            ->withPivot(['supplier_id', 'percentage_increase', 'is_active'])
            ->withTimestamps();
    }

    // ══════════════════════════════════════════════════════════
    //  SCOPES
    // ══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('days');
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('scope', 'supplier')
                     ->where('supplier_id', $supplierId);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('scope', 'global');
    }

    // ══════════════════════════════════════════════════════════
    //  ACCESSORS
    // ══════════════════════════════════════════════════════════

    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: "چک {$this->days} روزه";
    }

    public function getFormattedDaysAttribute(): string
    {
        if ($this->days < 30) {
            return "{$this->days} روز";
        }
        
        $months = round($this->days / 30);
        return "{$months} ماه";
    }

    // ══════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════════
    //  HELPER METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * دریافت عنوان اختصاصی دوره برای یک تامین‌کننده خاص
     * اگر عنوان اختصاصی وجود نداشته باشد، از name استفاده می‌شود
     * 
     * @param int $supplierId
     * @return string|null
     */
    public function getLabelForSupplier(int $supplierId): ?string
    {
        $pivot = \DB::table('supplier_check_period')
            ->where('check_period_id', $this->id)
            ->where('supplier_id', $supplierId)
            ->first();
        
        if (!$pivot) {
            return $this->name;
        }
        
        // اگر عنوان اختصاصی وجود دارد از آن استفاده می‌شود، وگرنه از name
        return $pivot->label ?: $this->name;
    }

    /**
     * دریافت درصد افزایش با سلسله مراتب:
     * 1. اولویت اول: percentage_override در ProductPrice (اگر null نباشد)
     * 2. اولویت دوم: درصد دسته‌بندی (از جدول category_check_period)
     * 3. اولویت سوم: درصد دوره چک از supplier_check_period
     * 
     * @param int $supplierId
     * @param int|null $productId (برای بررسی category)
     * @param float|null $priceOverride (از ProductPrice اگر وجود داشته باشد)
     * @param int $checkPeriodId
     * @return float|null
     */
    public static function getEffectivePercentage(int $supplierId, ?int $productId = null, ?float $priceOverride = null, int $checkPeriodId): ?float
    {
        // Priority 1: Product-level override
        if ($priceOverride !== null) {
            return $priceOverride;
        }
        
        // Priority 2: Category-level percentage
        if ($productId) {
            $product = Product::find($productId);
            if ($product && $product->category_id) {
                $categoryPercentage = \DB::table('category_check_period')
                    ->where('category_id', $product->category_id)
                    ->where('check_period_id', $checkPeriodId)
                    ->where('supplier_id', $supplierId)
                    ->where('is_active', true)
                    ->value('percentage_increase');
                
                if ($categoryPercentage !== null) {
                    return (float)$categoryPercentage;
                }
            }
        }
        
        // Priority 3: Check period default percentage
        $checkPeriod = static::find($checkPeriodId);
        if ($checkPeriod) {
            return $checkPeriod->getPercentageForSupplier($supplierId);
        }
        
        return null;
    }

    /**
     * محاسبه قیمت چکی بر اساس قیمت نقدی و درصد افزایش با سلسله مراتب
     * 
     * @param float $cashPrice قیمت نقدی
     * @param int $supplierId
     * @param int|null $productId
     * @param float|null $priceOverride
     * @return float|null
     */
    public function calculateCheckPriceWithHierarchy(float $cashPrice, int $supplierId, ?int $productId = null, ?float $priceOverride = null): ?float
    {
        $percentage = static::getEffectivePercentage($supplierId, $productId, $priceOverride, $this->id);
        
        if ($percentage === null) {
            return null;
        }
        
        return $cashPrice * (1 + ($percentage / 100));
    }

    /**
     * دریافت درصد افزایش قیمت برای یک تامین‌کننده خاص
     * 
     * @param int $supplierId
     * @return float|null
     */
    public function getPercentageForSupplier(int $supplierId): ?float
    {
        $pivot = \DB::table('supplier_check_period')
            ->where('check_period_id', $this->id)
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->first();
        
        return $pivot ? (float)$pivot->percentage_increase : null;
    }

    /**
     * دریافت درصد افزایش برای یک دسته‌بندی و تامین‌کننده خاص
     * 
     * @param int $categoryId
     * @param int $supplierId
     * @return float|null
     */
    public function getPercentageForCategory(int $categoryId, int $supplierId): ?float
    {
        $pivot = \DB::table('category_check_period')
            ->where('check_period_id', $this->id)
            ->where('category_id', $categoryId)
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->first();
        
        return $pivot ? (float)$pivot->percentage_increase : null;
    }

    /**
     * بررسی اینکه آیا این دوره برای تامین‌کننده فعال است
     * 
     * @param int $supplierId
     * @return bool
     */
    public function isActiveForSupplier(int $supplierId): bool
    {
        return \DB::table('supplier_check_period')
            ->where('check_period_id', $this->id)
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * محاسبه قیمت چکی بر اساس قیمت نقدی و درصد افزایش
     * 
     * @param float $cashPrice قیمت نقدی
     * @param int $supplierId
     * @return float|null
     */
    public function calculateCheckPrice(float $cashPrice, int $supplierId): ?float
    {
        $percentage = $this->getPercentageForSupplier($supplierId);
        
        if ($percentage === null) {
            return null;
        }
        
        return $cashPrice * (1 + ($percentage / 100));
    }

    /**
     * دریافت تمام دوره‌های فعال برای یک تامین‌کننده
     * شامل دوره‌های global و دوره‌های خود تامین‌کننده
     * با اطلاعات label اختصاصی هر تامین‌کننده
     * 
     * @param int $supplierId
     * @return \Illuminate\Support\Collection
     */
    public static function getActiveForSupplier(int $supplierId): \Illuminate\Support\Collection
    {
        return static::query()
            ->select('check_periods.*', 'scp.percentage_increase', 'scp.is_active as pivot_is_active', 'scp.label as supplier_label')
            ->leftJoin('supplier_check_period as scp', function($join) use ($supplierId) {
                $join->on('check_periods.id', '=', 'scp.check_period_id')
                     ->where('scp.supplier_id', '=', $supplierId);
            })
            ->where('check_periods.is_active', true)
            ->where(function($q) use ($supplierId) {
                $q->where('check_periods.scope', 'global')
                  ->orWhere('check_periods.supplier_id', $supplierId);
            })
            ->orderBy('check_periods.sort_order')
            ->orderBy('check_periods.days')
            ->get()
            ->map(function($period) {
                // اضافه کردن اطلاعات pivot به شکل قابل استفاده
                $period->supplier_percentage = $period->percentage_increase;
                $period->is_enabled_for_supplier = (bool)$period->pivot_is_active;
                // استفاده از label اختصاصی اگر وجود دارد، وگرنه از name
                $period->display_name = $period->supplier_label ?: $period->name;
                return $period;
            });
    }

    public static function getOptionsForSupplier(?int $supplierId = null): array
    {
        $query = static::active()->ordered();
        
        if ($supplierId) {
            $query->where(function ($q) use ($supplierId) {
                $q->whereNull('supplier_id')
                  ->orWhere('supplier_id', $supplierId);
            });
        } else {
            $query->whereNull('supplier_id');
        }
        
        return $query->get()->pluck('display_label', 'id')->toArray();
    }
}
