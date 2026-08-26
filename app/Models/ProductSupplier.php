<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ProductSupplier extends Model
{
    use HasFactory, SoftDeletes;

    // ──────────────────────────────────────────────────────────
    //  TABLE & CONSTANTS
    // ──────────────────────────────────────────────────────────

    protected $table = 'product_supplier';

    /** وضعیت‌های ارتباط تأمین‌کننده-محصول */
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_REJECTED,
    ];

    /** برچسب‌های فارسی وضعیت */
    public const STATUS_LABELS = [
        self::STATUS_PENDING  => 'در انتظار بررسی',
        self::STATUS_ACTIVE   => 'فعال',
        self::STATUS_INACTIVE => 'غیرفعال',
        self::STATUS_REJECTED => 'رد شده',
    ];

    /** رنگ‌های بج وضعیت (Tailwind) */
    public const STATUS_COLORS = [
        self::STATUS_PENDING  => 'yellow',
        self::STATUS_ACTIVE   => 'green',
        self::STATUS_INACTIVE => 'gray',
        self::STATUS_REJECTED => 'red',
    ];

    /** حداکثر تعداد تأمین‌کنندگان هر محصول */
    public const MAX_SUPPLIERS_PER_PRODUCT = 10;

    /** حداقل تعداد نوع قیمت مورد نیاز */
    public const MIN_PRICE_TYPES_REQUIRED = 1;

    // ──────────────────────────────────────────────────────────
    //  FILLABLE & CASTS
    // ──────────────────────────────────────────────────────────

    protected $fillable = [
        'product_id',
        'supplier_id',
        'sku',
        'is_primary',
        'is_active',
        'status',
        'sort_order',
        'cost_price',
        'commission_rate',
        'stock_quantity',
        'min_order_quantity',
        'max_order_quantity',
        'lead_time_days',
        'warranty_info',
        'warranty_type',          // نوع محاسبه هزینه (none/percentage/fixed/custom)
        'warranty_value',         // مقدار درصد یا مبلغ
        'has_warranty',           // آیا محصول با گارانتی است؟
        'warranty_provider',      // ارائه‌دهنده گارانتی
        'warranty_coverage_type', // نوع پوشش گارانتی
        'warranty_type_coverage', // ✅ جدید: نوع گارانتی (test/limited/lifetime)
        'warranty_duration_days', // مدت گارانتی
        'notes',
        'activated_at',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary'              => 'boolean',
            'is_active'               => 'boolean',
            'sort_order'              => 'integer',
            'cost_price'              => 'integer',
            'commission_rate'         => 'decimal:2',
            'stock_quantity'          => 'integer',
            'min_order_quantity'      => 'integer',
            'max_order_quantity'      => 'integer',
            'lead_time_days'          => 'integer',
            'warranty_value'          => 'decimal:2',
            'has_warranty'            => 'boolean',     // ✅
            'warranty_duration_days'  => 'integer',     // ✅
            'activated_at'            => 'datetime',
            'deactivated_at'          => 'datetime',
        ];
    }

    protected $appends = [
        'lowest_price',
        'highest_price',
        'formatted_price_range',
        'supplier_name',
        'status_label',
    ];

    // ──────────────────────────────────────────────────────────
    //  BOOT (Model Events)
    // ──────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        /**
         * هنگام ایجاد: اگر sort_order ست نشده، محاسبه خودکار
         */
        static::creating(function (self $productSupplier) {
            if (is_null($productSupplier->sort_order)) {
                $maxOrder = static::where('product_id', $productSupplier->product_id)
                                  ->max('sort_order');
                $productSupplier->sort_order = ($maxOrder ?? 0) + 1;
            }

            if (is_null($productSupplier->status)) {
                $productSupplier->status = self::STATUS_PENDING;
            }
        });

        /**
         * هنگام غیرفعال‌سازی: قیمت‌ها هم غیرفعال شوند
         */
        static::updating(function (self $productSupplier) {
            if ($productSupplier->isDirty('is_active') && !$productSupplier->is_active) {
                $productSupplier->deactivated_at = now();
                // غیرفعال‌سازی تمام قیمت‌های مرتبط
                $productSupplier->prices()->update(['is_active' => false]);
            }

            if ($productSupplier->isDirty('is_active') && $productSupplier->is_active) {
                $productSupplier->activated_at = now();
                $productSupplier->deactivated_at = null;
            }
        });

        /**
         * هنگام حذف قطعی: قیمت‌ها هم حذف شوند
         */
        static::forceDeleting(function (self $productSupplier) {
            $productSupplier->prices()->forceDelete();
        });
    }

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS
    // ══════════════════════════════════════════════════════════

    /**
     * محصول مرتبط
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * تأمین‌کننده مرتبط
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * تمام قیمت‌های این تأمین‌کننده برای این محصول
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_supplier_id');
    }

    /**
     * فقط قیمت‌های فعال
     */
    public function activePrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_supplier_id')
                    ->where('is_active', true);
    }

    /**
     * فقط قیمت‌های معتبر (فعال + در بازه زمانی)
     */
    public function validPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_supplier_id')
                    ->where('is_active', true)
                    ->where(function (Builder $query) {
                        $query->whereNull('valid_from')
                              ->orWhere('valid_from', '<=', now());
                    })
                    ->where(function (Builder $query) {
                        $query->whereNull('valid_until')
                              ->orWhere('valid_until', '>=', now());
                    });
    }

    /**
     * قیمت‌های خرده‌فروشی مرتبط با این محصول
     */
    public function retailPrices(): HasMany
    {
        return $this->hasMany(RetailPrice::class, 'product_id', 'product_id');
    }

    // ══════════════════════════════════════════════════════════
    //  SCOPES
    // ══════════════════════════════════════════════════════════

    /**
     * فقط فعال‌ها
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * فقط اصلی‌ها
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * فیلتر بر اساس محصول
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * فیلتر بر اساس تأمین‌کننده
     */
    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * فیلتر بر اساس وضعیت
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * فقط تأمین‌کنندگان تأیید شده
     */
    public function scopeVerifiedSupplier(Builder $query): Builder
    {
        return $query->whereHas('supplier', function (Builder $q) {
            $q->where('is_verified', true)
              ->where('is_active', true);
        });
    }

    /**
     * مرتب‌سازی بر اساس sort_order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * مرتب‌سازی: اصلی اول، سپس بر اساس sort_order
     */
    public function scopeOrderedWithPrimaryFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_primary')
                     ->orderBy('sort_order')
                     ->orderBy('id');
    }

    /**
     * دارای موجودی
     */
    public function scopeWithStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('stock_quantity')
              ->orWhere('stock_quantity', '>', 0);
        });
    }

    /**
     * بدون موجودی
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', 0);
    }

    /**
     * دارای قیمت فعال
     */
    public function scopeHasActivePrices(Builder $query): Builder
    {
        return $query->whereHas('activePrices');
    }

    /**
     * بدون قیمت فعال
     */
    public function scopeWithoutActivePrices(Builder $query): Builder
    {
        return $query->whereDoesntHave('activePrices');
    }

    /**
     * Eager load قیمت‌ها و تأمین‌کننده
     */
    public function scopeWithPricesAndSupplier(Builder $query): Builder
    {
        return $query->with(['activePrices', 'supplier:id,company_name,brand_name,logo_path,is_verified']);
    }

    /**
     * Eager load کامل (برای صفحه جزئیات)
     */
    public function scopeWithFullDetails(Builder $query): Builder
    {
        return $query->with([
            'activePrices' => fn($q) => $q->orderBy('price'),
            'validPrices'  => fn($q) => $q->orderBy('price'),
            'supplier'     => fn($q) => $q->select([
                'id', 'user_id', 'company_name', 'company_name_en',
                'brand_name', 'logo_path', 'phone', 'mobile',
                'email', 'city', 'province', 'is_verified', 'is_active',
            ]),
        ]);
    }

    /**
     * فیلتر بر اساس بازه قیمت (پایین‌ترین قیمت فعال)
     */
    public function scopeInPriceRange(Builder $query, ?int $minPrice, ?int $maxPrice): Builder
    {
        return $query->whereHas('activePrices', function (Builder $q) use ($minPrice, $maxPrice) {
            if ($minPrice !== null) {
                $q->where('price', '>=', $minPrice);
            }
            if ($maxPrice !== null) {
                $q->where('price', '<=', $maxPrice);
            }
        });
    }

    /**
     * جستجو بر اساس SKU
     */
    public function scopeBySku(Builder $query, string $sku): Builder
    {
        return $query->where('sku', $sku);
    }

    /**
     * اخیراً اضافه‌شده
     */
    public function scopeRecentlyAdded(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ══════════════════════════════════════════════════════════
    //  ACCESSORS
    // ══════════════════════════════════════════════════════════

    /**
     * کمترین قیمت این تأمین‌کننده برای این محصول (ریال)
     */
    public function getLowestPriceAttribute(): ?int
    {
        // اگر eager load شده باشد، از collection استفاده کن (بدون query اضافی)
        if ($this->relationLoaded('activePrices')) {
            $prices = $this->activePrices->pluck('price')->filter();
            return $prices->isNotEmpty() ? (int) $prices->min() : null;
        }

        return $this->activePrices()->min('price');
    }

    /**
     * بالاترین قیمت (ریال)
     */
    public function getHighestPriceAttribute(): ?int
    {
        if ($this->relationLoaded('activePrices')) {
            $prices = $this->activePrices->pluck('price')->filter();
            return $prices->isNotEmpty() ? (int) $prices->max() : null;
        }

        return $this->activePrices()->max('price');
    }

    /**
     * کمترین قیمت به تومان
     */
    public function getLowestPriceTomanAttribute(): ?int
    {
        $price = $this->lowest_price;
        return $price !== null ? (int) round($price / 10) : null;
    }

    /**
     * بالاترین قیمت به تومان
     */
    public function getHighestPriceTomanAttribute(): ?int
    {
        $price = $this->highest_price;
        return $price !== null ? (int) round($price / 10) : null;
    }

    /**
     * کمترین قیمت فرمت‌شده (تومان)
     */
    public function getFormattedLowestPriceAttribute(): string
    {
        $price = $this->lowest_price_toman;
        return $price !== null ? number_format($price) . ' تومان' : 'قیمت ثبت نشده';
    }

    /**
     * بالاترین قیمت فرمت‌شده (تومان)
     */
    public function getFormattedHighestPriceAttribute(): string
    {
        $price = $this->highest_price_toman;
        return $price !== null ? number_format($price) . ' تومان' : 'قیمت ثبت نشده';
    }

    /**
     * محدوده قیمت فرمت‌شده (تومان)
     */
    public function getFormattedPriceRangeAttribute(): string
    {
        $lowest  = $this->lowest_price_toman;
        $highest = $this->highest_price_toman;

        if ($lowest === null) {
            return 'قیمت ثبت نشده';
        }

        if ($lowest === $highest) {
            return number_format($lowest) . ' تومان';
        }

        return number_format($lowest) . ' ~ ' . number_format($highest) . ' تومان';
    }

    /**
     * نام تأمین‌کننده (بدون query اضافی اگر eager load شده باشد)
     */
    public function getSupplierNameAttribute(): string
    {
        return $this->supplier?->company_name ?? 'نامشخص';
    }

    /**
     * نام انگلیسی تأمین‌کننده
     */
    public function getSupplierNameEnAttribute(): string
    {
        return $this->supplier?->company_name_en ?? '';
    }

    /**
     * نام برند تأمین‌کننده
     */
    public function getSupplierBrandAttribute(): string
    {
        return $this->supplier?->brand_name ?? '';
    }

    /**
     * لوگوی تأمین‌کننده
     */
    public function getSupplierLogoUrlAttribute(): string
    {
        if ($this->supplier?->logo_path) {
            return asset('storage/' . $this->supplier->logo_path);
        }

        return asset('images/placeholders/supplier-default.png');
    }

    /**
     * عنوان محصول
     */
    public function getProductTitleAttribute(): string
    {
        return $this->product?->title ?? 'نامشخص';
    }

    /**
     * اسلاگ محصول
     */
    public function getProductSlugAttribute(): string
    {
        return $this->product?->slug ?? '';
    }

    /**
     * برچسب فارسی وضعیت
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'نامشخص';
    }

    /**
     * رنگ بج وضعیت (Tailwind)
     */
    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    /**
     * آیا تأمین‌کننده تأیید شده است؟
     */
    public function getIsVerifiedSupplierAttribute(): bool
    {
        return $this->supplier?->is_verified ?? false;
    }

    /**
     * نرخ کمیسیون فرمت‌شده
     */
    public function getFormattedCommissionRateAttribute(): string
    {
        if ($this->commission_rate === null) {
            return 'تعیین نشده';
        }

        return number_format($this->commission_rate, 1) . '%';
    }

    /**
     * قیمت تمام‌شده فرمت‌شده (تومان)
     */
    public function getFormattedCostPriceAttribute(): string
    {
        if ($this->cost_price === null) {
            return 'ثبت نشده';
        }

        return number_format((int) round($this->cost_price / 10)) . ' تومان';
    }

    /**
     * وضعیت موجودی
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->stock_quantity === null) {
            return 'نامحدود';
        }

        if ($this->stock_quantity <= 0) {
            return 'ناموجود';
        }

        if ($this->stock_quantity <= 5) {
            return 'تعداد محدود';
        }

        return 'موجود';
    }

    /**
     * رنگ وضعیت موجودی (Tailwind)
     */
    public function getStockStatusColorAttribute(): string
    {
        if ($this->stock_quantity === null) {
            return 'blue';
        }

        if ($this->stock_quantity <= 0) {
            return 'red';
        }

        if ($this->stock_quantity <= 5) {
            return 'yellow';
        }

        return 'green';
    }

    /**
     * مدت زمان تحویل فرمت‌شده
     */
    public function getFormattedLeadTimeAttribute(): string
    {
        if ($this->lead_time_days === null) {
            return 'مشخص نشده';
        }

        if ($this->lead_time_days === 0) {
            return 'آماده ارسال';
        }

        if ($this->lead_time_days === 1) {
            return '۱ روز کاری';
        }

        return $this->lead_time_days . ' روز کاری';
    }

    /**
     * تعداد انواع قیمت فعال
     */
    public function getActivePriceTypesCountAttribute(): int
    {
        if ($this->relationLoaded('activePrices')) {
            return $this->activePrices->count();
        }

        return $this->activePrices()->count();
    }

    /**
     * آیا حداقل یک قیمت فعال دارد؟
     */
    public function getHasActivePricesAttribute(): bool
    {
        return $this->active_price_types_count > 0;
    }

    /**
     * خلاصه اطلاعات (برای tooltip یا card)
     */
    public function getSummaryAttribute(): array
    {
        return [
            'supplier'    => $this->supplier_name,
            'brand'       => $this->supplier_brand,
            'sku'         => $this->sku,
            'price_range' => $this->formatted_price_range,
            'stock'       => $this->stock_status,
            'lead_time'   => $this->formatted_lead_time,
            'is_primary'  => $this->is_primary,
            'is_verified' => $this->is_verified_supplier,
        ];
    }

    /**
     * محاسبه قیمت نهایی با احتساب هزینه گارانتی
     * 
     * @param int|float $basePrice قیمت پایه (بدون گارانتی)
     * @return int|float قیمت نهایی (با گارانتی)
     */
    public function calculatePriceWithWarranty($basePrice)
    {
        if ($this->warranty_type === 'none' || empty($this->warranty_value)) {
            return $basePrice;
        }

        if ($this->warranty_type === 'percentage') {
            return $basePrice * (1 + $this->warranty_value / 100);
        }

        if ($this->warranty_type === 'fixed') {
            return $basePrice + $this->warranty_value;
        }

        // custom: هزینه گارانتی در جدول product_supplier_prices است
        return $basePrice;
    }

    /**
     * دریافت توضیح گارانتی
     */
    public function getWarrantyDescriptionAttribute(): ?string
    {
        if (!$this->has_warranty) {
            return 'بدون گارانتی';
        }

        $parts = [];
        
        // نوع گارانتی
        $parts[] = match($this->warranty_provider) {
            'manufacturer' => 'گارانتی شرکتی',
            'seller' => 'گارانتی فروشنده',
            default => 'گارانتی',
        };
        
        // نوع پوشش
        if ($this->warranty_coverage_type) {
            $coverageLabel = match($this->warranty_coverage_type) {
                'full' => 'کامل',
                'limited' => 'محدود',
                'parts_only' => 'فقط قطعات',
                'service_only' => 'فقط خدمات',
                default => '',
            };
            if ($coverageLabel) {
                $parts[] = $coverageLabel;
            }
        }
        
        // ✅ نوع گارانتی (تست، مدت‌دار، مادام‌العمر)
        if ($this->warranty_type_coverage) {
            $typeLabel = match($this->warranty_type_coverage) {
                'test' => 'تست',
                'limited' => 'مدت‌دار',
                'lifetime' => 'مادام‌العمر',
                default => '',
            };
            if ($typeLabel) {
                $parts[] = $typeLabel;
            }
        }
        
        // مدت گارانتی (فقط برای تست و مدت‌دار)
        if ($this->warranty_duration_days && in_array($this->warranty_type_coverage, ['test', 'limited'])) {
            if ($this->warranty_duration_days < 30) {
                $parts[] = "{$this->warranty_duration_days} روز";
            } elseif ($this->warranty_duration_days < 365) {
                $months = round($this->warranty_duration_days / 30);
                $parts[] = "{$months} ماه";
            } else {
                $years = round($this->warranty_duration_days / 365, 1);
                $parts[] = "{$years} سال";
            }
        }
        
        $description = implode(' - ', $parts);
        
        // هزینه گارانتی (به عنوان آپشن اضافی)
        if ($this->warranty_type === 'percentage' && $this->warranty_value) {
            $description .= " | آپشن گارانتی: +{$this->warranty_value}%";
        } elseif ($this->warranty_type === 'fixed' && $this->warranty_value) {
            $description .= " | آپشن گارانتی: +" . number_format($this->warranty_value) . " تومان";
        } elseif ($this->warranty_type === 'none') {
            $description .= " | آپشن گارانتی: بدون هزینه اضافی";
        }
        
        return $description;
    }

    // ══════════════════════════════════════════════════════════
    //  ACTION METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * تعیین به عنوان تأمین‌کننده اصلی (با Transaction)
     */
    public function makePrimary(): bool
    {
        return DB::transaction(function () {
            // حذف primary از سایر تأمین‌کنندگان این محصول
            static::where('product_id', $this->product_id)
                  ->where('id', '!=', $this->id)
                  ->where('is_primary', true)
                  ->update(['is_primary' => false]);

            return $this->update(['is_primary' => true]);
        });
    }

    /**
     * حذف از حالت اصلی
     */
    public function removePrimary(): bool
    {
        return $this->update(['is_primary' => false]);
    }

    /**
     * فعال‌سازی
     */
    public function activate(): bool
    {
        return $this->update([
            'is_active'    => true,
            'status'       => self::STATUS_ACTIVE,
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);
    }

    /**
     * غیرفعال‌سازی (قیمت‌ها هم خودکار غیرفعال می‌شوند — در boot)
     */
    public function deactivate(): bool
    {
        return $this->update([
            'is_active'      => false,
            'status'         => self::STATUS_INACTIVE,
            'deactivated_at' => now(),
        ]);
    }

    /**
     * رد کردن
     */
    public function reject(?string $reason = null): bool
    {
        return $this->update([
            'is_active' => false,
            'status'    => self::STATUS_REJECTED,
            'notes'     => $reason,
        ]);
    }

    /**
     * تأیید و فعال‌سازی
     */
    public function approve(): bool
    {
        return $this->activate();
    }

    /**
     * غیرفعال‌سازی تمام قیمت‌ها
     */
    public function deactivateAllPrices(): int
    {
        return $this->prices()->update(['is_active' => false]);
    }

    /**
     * فعال‌سازی تمام قیمت‌ها
     */
    public function activateAllPrices(): int
    {
        return $this->prices()->update(['is_active' => true]);
    }

    /**
     * بروزرسانی موجودی
     */
    public function updateStock(int $quantity): bool
    {
        return $this->update(['stock_quantity' => max(0, $quantity)]);
    }

    /**
     * کاهش موجودی
     */
    public function decrementStock(int $amount = 1): bool
    {
        $newQuantity = max(0, ($this->stock_quantity ?? 0) - $amount);
        return $this->update(['stock_quantity' => $newQuantity]);
    }

    /**
     * افزایش موجودی
     */
    public function incrementStock(int $amount = 1): bool
    {
        $newQuantity = ($this->stock_quantity ?? 0) + $amount;
        return $this->update(['stock_quantity' => $newQuantity]);
    }

    // ══════════════════════════════════════════════════════════
    //  PRICE QUERY METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * دریافت قیمت بر اساس نوع
     */
    public function getPriceByType(string $priceType): ?ProductPrice
    {
        if ($this->relationLoaded('activePrices')) {
            return $this->activePrices
                        ->where('price_type', $priceType)
                        ->first();
        }

        return $this->activePrices()
                     ->where('price_type', $priceType)
                     ->first();
    }

    /**
     * آیا نوع قیمت خاصی وجود دارد؟
     */
    public function hasPriceOfType(string $priceType): bool
    {
        return $this->getPriceByType($priceType) !== null;
    }

    /**
     * دریافت تمام انواع قیمت‌های فعال (به صورت آرایه برچسب‌دار)
     */
    public function getPriceList(): array
    {
        $prices = $this->relationLoaded('activePrices')
            ? $this->activePrices
            : $this->activePrices()->orderBy('price')->get();

        return $prices->map(function (ProductPrice $price) {
            return [
                'id'              => $price->id,
                'type'            => $price->price_type,
                'type_label'      => $price->type_label ?? $price->price_type,
                'price_rial'      => $price->price,
                'price_toman'     => (int) round($price->price / 10),
                'formatted_price' => number_format((int) round($price->price / 10)) . ' تومان',
                'min_quantity'    => $price->min_quantity,
                'max_quantity'    => $price->max_quantity,
                'warranty_months' => $price->warranty_months,
                'check_months'    => $price->check_months,
                'description'     => $price->description,
                'is_active'       => $price->is_active,
            ];
        })->toArray();
    }

    /**
     * جدول قیمت مقایسه‌ای (برای UI)
     */
    public function getComparisonPriceTable(): array
    {
        return [
            'supplier_name'  => $this->supplier_name,
            'supplier_brand' => $this->supplier_brand,
            'is_verified'    => $this->is_verified_supplier,
            'is_primary'     => $this->is_primary,
            'sku'            => $this->sku,
            'stock_status'   => $this->stock_status,
            'lead_time'      => $this->formatted_lead_time,
            'price_range'    => $this->formatted_price_range,
            'prices'         => $this->getPriceList(),
        ];
    }

    // ══════════════════════════════════════════════════════════
    //  STATIC METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * دریافت تأمین‌کننده اصلی یک محصول
     */
    public static function getPrimaryForProduct(int $productId): ?self
    {
        return static::forProduct($productId)
                      ->active()
                      ->primary()
                      ->withPricesAndSupplier()
                      ->first()
            ?? static::forProduct($productId)
                      ->active()
                      ->withPricesAndSupplier()
                      ->ordered()
                      ->first();
    }

    /**
     * دریافت تمام تأمین‌کنندگان فعال یک محصول
     */
    public static function getActiveForProduct(int $productId): \Illuminate\Database\Eloquent\Collection
    {
        return static::forProduct($productId)
                      ->active()
                      ->verifiedSupplier()
                      ->withPricesAndSupplier()
                      ->orderedWithPrimaryFirst()
                      ->get();
    }

    /**
     * ارزان‌ترین تأمین‌کننده برای یک محصول
     */
    public static function getCheapestForProduct(int $productId): ?self
    {
        $suppliers = static::getActiveForProduct($productId);

        return $suppliers->sortBy(function (self $ps) {
            return $ps->lowest_price ?? PHP_INT_MAX;
        })->first();
    }

    /**
     * آیا محصول ظرفیت پذیرش تأمین‌کننده جدید دارد؟
     */
    public static function canAddSupplier(int $productId): bool
    {
        $currentCount = static::forProduct($productId)->count();
        return $currentCount < self::MAX_SUPPLIERS_PER_PRODUCT;
    }

    /**
     * آیا تأمین‌کننده قبلاً به محصول اضافه شده؟
     */
    public static function exists(int $productId, int $supplierId): bool
    {
        return static::forProduct($productId)
                      ->forSupplier($supplierId)
                      ->exists();
    }

    /**
     * تعداد تأمین‌کنندگان فعال یک محصول
     */
    public static function countActiveForProduct(int $productId): int
    {
        return static::forProduct($productId)->active()->count();
    }

    /**
     * آمار تأمین‌کنندگان یک محصول
     */
    public static function getStatsForProduct(int $productId): array
    {
        $query = static::forProduct($productId);

        return [
            'total_count'        => (clone $query)->count(),
            'active_count'       => (clone $query)->active()->count(),
            'verified_count'     => (clone $query)->active()->verifiedSupplier()->count(),
            'has_primary'        => (clone $query)->primary()->exists(),
            'with_prices_count'  => (clone $query)->active()->hasActivePrices()->count(),
            'out_of_stock_count' => (clone $query)->active()->outOfStock()->count(),
            'can_add_more'       => self::canAddSupplier($productId),
            'remaining_slots'    => max(0, self::MAX_SUPPLIERS_PER_PRODUCT - (clone $query)->count()),
        ];
    }

    /**
     * آمار محصولات یک تأمین‌کننده
     */
    public static function getStatsForSupplier(int $supplierId): array
    {
        $query = static::forSupplier($supplierId);

        return [
            'total_products'      => (clone $query)->count(),
            'active_products'     => (clone $query)->active()->count(),
            'pending_products'    => (clone $query)->byStatus(self::STATUS_PENDING)->count(),
            'primary_products'    => (clone $query)->primary()->count(),
            'out_of_stock'        => (clone $query)->active()->outOfStock()->count(),
            'without_prices'      => (clone $query)->active()->withoutActivePrices()->count(),
            'recently_added'      => (clone $query)->recentlyAdded(7)->count(),
        ];
    }

    /**
     * بروزرسانی دسته‌ای قیمت‌ها با درصد (افزایش یا کاهش)
     */
    public static function bulkUpdatePricesByPercentage(
        int $supplierId,
        float $percentage,
        ?string $priceType = null,
        ?array $productIds = null
    ): int {
        return DB::transaction(function () use ($supplierId, $percentage, $priceType, $productIds) {
            $query = ProductPrice::whereHas('productSupplier', function (Builder $q) use ($supplierId, $productIds) {
                $q->forSupplier($supplierId)->active();
                if ($productIds) {
                    $q->whereIn('product_id', $productIds);
                }
            })->where('is_active', true);

            if ($priceType) {
                $query->where('price_type', $priceType);
            }

            $prices = $query->get();

            $updatedCount = 0;
            foreach ($prices as $price) {
                $multiplier = 1 + ($percentage / 100);
                $newPrice = (int) round($price->price * $multiplier);
                $newPrice = max(0, $newPrice); // قیمت نمی‌تواند منفی باشد

                if ($price->update(['price' => $newPrice])) {
                    $updatedCount++;
                }
            }

            return $updatedCount;
        });
    }

    /**
     * بازسازی ترتیب تأمین‌کنندگان یک محصول
     */
    public static function reindexSortOrder(int $productId): void
    {
        $items = static::forProduct($productId)
                       ->orderedWithPrimaryFirst()
                       ->get();

        foreach ($items as $index => $item) {
            $item->updateQuietly(['sort_order' => $index + 1]);
        }
    }

    /**
     * دریافت جدول مقایسه‌ای قیمت‌ها (برای صفحه جزئیات محصول)
     */
    public static function getComparisonTableForProduct(int $productId): array
    {
        $suppliers = static::getActiveForProduct($productId);

        return $suppliers->map(function (self $ps) {
            return $ps->getComparisonPriceTable();
        })->toArray();
    }
}
