<?php

namespace App\Models;

use App\Enums\PriceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ProductPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_supplier_id',
        'price_type',
        'price',
        'min_quantity',
        'max_quantity',
        'warranty_months',
        'check_months',
        'description',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'price_type'      => PriceType::class,
        'price'           => 'integer',
        'min_quantity'     => 'integer',
        'max_quantity'     => 'integer',
        'warranty_months'  => 'integer',
        'check_months'     => 'integer',
        'is_active'        => 'boolean',
        'valid_from'       => 'date',
        'valid_until'      => 'date',
    ];

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
     * ارتباط با جدول واسط محصول-تأمین‌کننده
     */
    public function productSupplier(): BelongsTo
    {
        return $this->belongsTo(ProductSupplier::class, 'product_supplier_id');
    }

    /**
     * دسترسی به تأمین‌کننده از طریق جدول واسط
     * (به صورت Accessor - برای دسترسی مستقیم بدون eager load)
     */
    public function getSupplierAttribute(): ?Supplier
    {
        return $this->productSupplier?->supplier;
    }

    // ══════════════════════════════════════════════════════════
    //  SCOPES
    // ══════════════════════════════════════════════════════════

    /**
     * فقط قیمت‌های فعال
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * فقط قیمت‌هایی که از نظر تاریخ معتبرند
     * - valid_from باید قبل از یا برابر امروز باشد (یا null)
     * - valid_until باید بعد از یا برابر امروز باشد (یا null)
     */
    public function scopeValid(Builder $query): Builder
    {
        $now = Carbon::now()->startOfDay();

        return $query->where(function (Builder $q) use ($now) {
            $q->whereNull('valid_from')
              ->orWhere('valid_from', '<=', $now);
        })->where(function (Builder $q) use ($now) {
            $q->whereNull('valid_until')
              ->orWhere('valid_until', '>=', $now);
        });
    }

    /**
     * ترکیب فعال + معتبر (پرکاربردترین فیلتر)
     */
    public function scopeActiveAndValid(Builder $query): Builder
    {
        return $query->active()->valid();
    }

    /**
     * فیلتر بر اساس نوع قیمت
     */
    public function scopeByType(Builder $query, PriceType $type): Builder
    {
        return $query->where('price_type', $type);
    }

    /**
     * فیلتر بر اساس چند نوع قیمت
     */
    public function scopeByTypes(Builder $query, array $types): Builder
    {
        return $query->whereIn('price_type', $types);
    }

    /**
     * فیلتر بر اساس محصول
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * فیلتر بر اساس تأمین‌کننده (از طریق product_supplier)
     */
    public function scopeBySupplier(Builder $query, int $supplierId): Builder
    {
        return $query->whereHas('productSupplier', function (Builder $q) use ($supplierId) {
            $q->where('supplier_id', $supplierId);
        });
    }

    /**
     * فیلتر بر اساس product_supplier_id مستقیم
     */
    public function scopeByProductSupplier(Builder $query, int $productSupplierId): Builder
    {
        return $query->where('product_supplier_id', $productSupplierId);
    }

    /**
     * ارزان‌ترین قیمت (مرتب‌سازی صعودی)
     */
    public function scopeCheapestFirst(Builder $query): Builder
    {
        return $query->orderBy('price', 'asc');
    }

    /**
     * گران‌ترین قیمت (مرتب‌سازی نزولی)
     */
    public function scopeMostExpensiveFirst(Builder $query): Builder
    {
        return $query->orderBy('price', 'desc');
    }

    /**
     * فقط قیمت‌های نقدی (غیرچکی)
     */
    public function scopeCashOnly(Builder $query): Builder
    {
        return $query->whereNotIn('price_type', [
            PriceType::CHECK_1_MONTH,
            PriceType::CHECK_2_MONTH,
            PriceType::CHECK_3_MONTH,
        ]);
    }

    /**
     * فقط قیمت‌های چکی
     */
    public function scopeCheckOnly(Builder $query): Builder
    {
        return $query->whereIn('price_type', [
            PriceType::CHECK_1_MONTH,
            PriceType::CHECK_2_MONTH,
            PriceType::CHECK_3_MONTH,
        ]);
    }

    /**
     * فقط قیمت‌های گارانتی‌دار
     */
    public function scopeWithWarranty(Builder $query): Builder
    {
        return $query->where('price_type', PriceType::WARRANTY);
    }

    /**
     * قیمت‌هایی در محدوده مشخص (تومان)
     */
    public function scopeInPriceRange(Builder $query, ?int $minPrice = null, ?int $maxPrice = null): Builder
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }
        return $query;
    }

    // ══════════════════════════════════════════════════════════
    //  ACCESSORS
    // ══════════════════════════════════════════════════════════

    /**
     * قیمت فرمت‌شده با تومان
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price) . ' تومان';
    }

    /**
     * قیمت به ریال (اگر ذخیره‌سازی به تومان است × ۱۰)
     * توجه: بر اساس پرامپت پروژه، ذخیره‌سازی به ریال و نمایش به تومان است
     */
    public function getPriceInRialAttribute(): int
    {
        return $this->price;
    }

    /**
     * قیمت به تومان (ریال ÷ ۱۰)
     * اگر ذخیره‌سازی به ریال باشد، این مقدار تومان نمایش می‌دهد
     * اگر ذخیره‌سازی مستقیم تومان باشد، همان مقدار برگردانده می‌شود
     *
     * ⚠️ مطابق db schema پروژه (decimal 12,0) و prompt "stored as integers (Rial)"
     *    در صورت تغییر، فقط این accessor را به‌روز کنید
     */
    public function getPriceInTomanAttribute(): int
    {
        // اگر ذخیره‌سازی ریال است:
        // return (int) ($this->price / 10);

        // اگر ذخیره‌سازی مستقیم تومان است (مانند seed فعلی):
        return $this->price;
    }

    /**
     * قیمت فرمت‌شده تومان (با جداکننده هزارتایی)
     */
    public function getFormattedTomanAttribute(): string
    {
        return number_format($this->price_in_toman) . ' تومان';
    }

    /**
     * قیمت فرمت‌شده ریال
     */
    public function getFormattedRialAttribute(): string
    {
        return number_format($this->price_in_rial) . ' ریال';
    }

    /**
     * برچسب فارسی نوع قیمت
     */
    public function getTypeLabelAttribute(): string
    {
        $label = $this->price_type->label();

        // افزودن جزئیات اضافی بر اساس نوع
        if ($this->price_type === PriceType::WARRANTY && $this->warranty_months) {
            $label .= " ({$this->warranty_months} ماهه)";
        }

        if (in_array($this->price_type, [
            PriceType::CHECK_1_MONTH,
            PriceType::CHECK_2_MONTH,
            PriceType::CHECK_3_MONTH,
        ]) && $this->check_months) {
            $label .= " ({$this->check_months} ماهه)";
        }

        return $label;
    }

    /**
     * توضیح کامل قیمت (نوع + جزئیات + تعداد)
     */
    public function getFullDescriptionAttribute(): string
    {
        $parts = [$this->type_label];

        if ($this->min_quantity && $this->min_quantity > 1) {
            $parts[] = "حداقل {$this->min_quantity} عدد";
        }

        if ($this->max_quantity) {
            $parts[] = "حداکثر {$this->max_quantity} عدد";
        }

        if ($this->description) {
            $parts[] = $this->description;
        }

        return implode(' | ', $parts);
    }

    /**
     * وضعیت اعتبار زمانی
     */
    public function getValidityStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'غیرفعال';
        }

        if (!$this->isValid()) {
            return 'منقضی';
        }

        if ($this->valid_until) {
            $daysLeft = Carbon::now()->diffInDays($this->valid_until, false);
            if ($daysLeft <= 7 && $daysLeft > 0) {
                return "در حال انقضا ({$daysLeft} روز مانده)";
            }
        }

        return 'معتبر';
    }

    /**
     * آیکون وضعیت اعتبار (برای نمایش در UI)
     */
    public function getValidityBadgeClassAttribute(): string
    {
        return match ($this->validity_status) {
            'معتبر'    => 'bg-green-100 text-green-800',
            'غیرفعال'  => 'bg-gray-100 text-gray-800',
            'منقضی'    => 'bg-red-100 text-red-800',
            default    => 'bg-yellow-100 text-yellow-800', // در حال انقضا
        };
    }

    /**
     * محدوده تعداد به صورت فرمت‌شده
     */
    public function getQuantityRangeAttribute(): string
    {
        $min = $this->min_quantity ?? 1;
        $max = $this->max_quantity;

        if ($max) {
            return "{$min} تا {$max} عدد";
        }

        if ($min > 1) {
            return "حداقل {$min} عدد";
        }

        return 'بدون محدودیت';
    }

    // ══════════════════════════════════════════════════════════
    //  HELPER METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * آیا قیمت در حال حاضر معتبر است؟ (فعال + در بازه زمانی)
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now()->startOfDay();

        if ($this->valid_from && $this->valid_from->gt($now)) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->lt($now)) {
            return false;
        }

        return true;
    }

    /**
     * آیا قیمت نقدی است؟
     */
    public function isCash(): bool
    {
        return !in_array($this->price_type, [
            PriceType::CHECK_1_MONTH,
            PriceType::CHECK_2_MONTH,
            PriceType::CHECK_3_MONTH,
        ]);
    }

    /**
     * آیا قیمت چکی است؟
     */
    public function isCheck(): bool
    {
        return in_array($this->price_type, [
            PriceType::CHECK_1_MONTH,
            PriceType::CHECK_2_MONTH,
            PriceType::CHECK_3_MONTH,
        ]);
    }

    /**
     * آیا قیمت گارانتی‌دار است؟
     */
    public function isWarranty(): bool
    {
        return $this->price_type === PriceType::WARRANTY;
    }

    /**
     * محاسبه حاشیه سود نسبت به قیمت خرده‌فروشی
     * @param int $retailPrice قیمت خرده‌فروشی (تومان)
     * @return array{amount: int, percentage: float}
     */
    public function calculateProfitMargin(int $retailPrice): array
    {
        $wholesalePrice = $this->price_in_toman;

        if ($wholesalePrice <= 0) {
            return ['amount' => 0, 'percentage' => 0.0];
        }

        $profitAmount = $retailPrice - $wholesalePrice;
        $profitPercentage = ($profitAmount / $wholesalePrice) * 100;

        return [
            'amount'              => $profitAmount,
            'percentage'          => round($profitPercentage, 2),
            'formatted_amount'    => number_format($profitAmount) . ' تومان',
            'formatted_percentage' => round($profitPercentage, 1) . '%',
        ];
    }

    /**
     * مقایسه قیمت با قیمت دیگر
     * @return array{difference: int, percentage: float, direction: string}
     */
    public function compareTo(self $otherPrice): array
    {
        $diff = $this->price - $otherPrice->price;
        $percentage = $otherPrice->price > 0
            ? round(($diff / $otherPrice->price) * 100, 2)
            : 0;

        return [
            'difference'           => $diff,
            'percentage'           => $percentage,
            'direction'            => $diff > 0 ? 'increase' : ($diff < 0 ? 'decrease' : 'same'),
            'formatted_difference' => number_format(abs($diff)) . ' تومان',
            'formatted_percentage' => abs($percentage) . '%',
            'arrow'                => $diff > 0 ? '↑' : ($diff < 0 ? '↓' : '→'),
            'color_class'          => $diff > 0 ? 'text-red-600' : ($diff < 0 ? 'text-green-600' : 'text-gray-500'),
        ];
    }

    /**
     * دریافت ارزان‌ترین قیمت فعال و معتبر برای یک محصول
     */
    public static function getLowestPriceForProduct(int $productId): ?self
    {
        return static::forProduct($productId)
                      ->activeAndValid()
                      ->cheapestFirst()
                      ->first();
    }

    /**
     * دریافت گران‌ترین قیمت فعال و معتبر برای یک محصول
     */
    public static function getHighestPriceForProduct(int $productId): ?self
    {
        return static::forProduct($productId)
                      ->activeAndValid()
                      ->mostExpensiveFirst()
                      ->first();
    }

    /**
     * دریافت محدوده قیمت فعال و معتبر برای یک محصول
     * @return array{min: int|null, max: int|null, formatted_min: string, formatted_max: string, formatted_range: string}
     */
    public static function getPriceRangeForProduct(int $productId): array
    {
        $prices = static::forProduct($productId)
                        ->activeAndValid()
                        ->pluck('price');

        if ($prices->isEmpty()) {
            return [
                'min'             => null,
                'max'             => null,
                'formatted_min'   => 'نامشخص',
                'formatted_max'   => 'نامشخص',
                'formatted_range' => 'تماس بگیرید',
                'count'           => 0,
            ];
        }

        $min = $prices->min();
        $max = $prices->max();

        return [
            'min'             => $min,
            'max'             => $max,
            'formatted_min'   => number_format($min) . ' تومان',
            'formatted_max'   => number_format($max) . ' تومان',
            'formatted_range' => $min === $max
                ? number_format($min) . ' تومان'
                : number_format($min) . ' ~ ' . number_format($max) . ' تومان',
            'count'           => $prices->count(),
        ];
    }

    /**
     * دریافت تمام قیمت‌های فعال یک محصول گروه‌بندی‌شده بر اساس تأمین‌کننده
     */
    public static function getGroupedBySupplier(int $productId): \Illuminate\Support\Collection
    {
        return static::forProduct($productId)
                      ->activeAndValid()
                      ->with(['productSupplier.supplier'])
                      ->cheapestFirst()
                      ->get()
                      ->groupBy('product_supplier_id');
    }
}
