<?php

namespace App\Models;

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
        'price_type_id',
        'tier_index',
        'check_period_id',
        'price',
        'warranty_type',
        'warranty_duration',
        'warranty_price',
        'description',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'tier_index'       => 'integer',
        'price'            => 'integer',
        'warranty_duration' => 'integer',
        'warranty_price'   => 'integer',
        'is_active'        => 'boolean',
        'valid_from'       => 'date',
        'valid_until'      => 'date',
    ];

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS
    // ══════════════════════════════════════════════════════════

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productSupplier(): BelongsTo
    {
        return $this->belongsTo(ProductSupplier::class, 'product_supplier_id');
    }

    public function priceType(): BelongsTo
    {
        return $this->belongsTo(PriceType::class);
    }

    public function checkPeriod(): BelongsTo
    {
        return $this->belongsTo(CheckPeriod::class);
    }

    public function getSupplierAttribute(): ?Supplier
    {
        return $this->productSupplier?->supplier;
    }

    // ══════════════════════════════════════════════════════════
    //  SCOPES
    // ══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query): Builder
    {
        $now = Carbon::now()->startOfDay();
        return $query->where(function (Builder $q) use ($now) {
            $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
        })->where(function (Builder $q) use ($now) {
            $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
        });
    }

    public function scopeActiveAndValid(Builder $query): Builder
    {
        return $query->active()->valid();
    }

    public function scopeByPriceType(Builder $query, int $priceTypeId): Builder
    {
        return $query->where('price_type_id', $priceTypeId);
    }

    public function scopeCashOnly(Builder $query): Builder
    {
        return $query->whereHas('priceType', fn(Builder $q) => $q->where('name', PriceType::CASH));
    }

    public function scopeCheckOnly(Builder $query): Builder
    {
        return $query->whereHas('priceType', fn(Builder $q) => $q->where('name', PriceType::CHECK));
    }

    public function scopeWithWarranty(Builder $query): Builder
    {
        return $query->whereHas('priceType', fn(Builder $q) => $q->where('name', PriceType::WARRANTY));
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForProductSupplier(Builder $query, int $productSupplierId): Builder
    {
        return $query->where('product_supplier_id', $productSupplierId);
    }

    public function scopeForTierIndex(Builder $query, int $tierIndex): Builder
    {
        return $query->where('tier_index', $tierIndex);
    }

    public function scopeCheapestFirst(Builder $query): Builder
    {
        return $query->orderBy('price', 'asc');
    }

    public function scopeMostExpensiveFirst(Builder $query): Builder
    {
        return $query->orderBy('price', 'desc');
    }

    // ══════════════════════════════════════════════════════════
    //  ACCESSORS
    // ══════════════════════════════════════════════════════════

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price) . ' تومان';
    }

    public function getPriceInTomanAttribute(): int
    {
        return $this->price;
    }

    public function getFullDescriptionAttribute(): string
    {
        $parts = [];
        
        if ($this->priceType) {
            $parts[] = $this->priceType->label_fa;
        }
        
        $product = $this->product;
        if ($product && $product->quantity_tiers) {
            $tiers = is_string($product->quantity_tiers) 
                ? json_decode($product->quantity_tiers, true) 
                : $product->quantity_tiers;
            $tier = $tiers[$this->tier_index] ?? null;
            if ($tier) {
                $parts[] = $tier['name'] ?? "تیراژ " . ($this->tier_index + 1);
            }
        }
        
        if ($this->checkPeriod) {
            $parts[] = $this->checkPeriod->display_label;
        }
        
        if ($this->warranty_type) {
            $warrantyTypes = [
                'test' => 'تست',
                'limited' => 'مدت‌دار',
                'lifetime' => 'مادام‌العمر',
            ];
            $parts[] = $warrantyTypes[$this->warranty_type] ?? $this->warranty_type;
            if ($this->warranty_duration) {
                $parts[count($parts) - 1] .= " {$this->warranty_duration} ماه";
            }
        }
        
        if ($this->description) {
            $parts[] = $this->description;
        }

        return implode(' | ', $parts);
    }

    public function getValidityStatusAttribute(): string
    {
        if (!$this->is_active) return 'غیرفعال';
        if (!$this->isValid()) return 'منقضی';
        
        if ($this->valid_until) {
            $daysLeft = Carbon::now()->diffInDays($this->valid_until, false);
            if ($daysLeft <= 7 && $daysLeft > 0) {
                return "در حال انقضا ({$daysLeft} روز مانده)";
            }
        }
        
        return 'معتبر';
    }

    // ══════════════════════════════════════════════════════════
    //  HELPER METHODS
    // ══════════════════════════════════════════════════════════

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        
        $now = Carbon::now()->startOfDay();
        if ($this->valid_from && $this->valid_from->gt($now)) return false;
        if ($this->valid_until && $this->valid_until->lt($now)) return false;
        
        return true;
    }

    public static function getLowestPriceForProduct(int $productId): ?self
    {
        return static::forProduct($productId)->activeAndValid()->cheapestFirst()->first();
    }

    public static function getHighestPriceForProduct(int $productId): ?self
    {
        return static::forProduct($productId)->activeAndValid()->mostExpensiveFirst()->first();
    }

    public static function getPriceRangeForProduct(int $productId): array
    {
        $prices = static::forProduct($productId)->activeAndValid()->pluck('price');

        if ($prices->isEmpty()) {
            return [
                'min' => null,
                'max' => null,
                'formatted_range' => 'تماس بگیرید',
            ];
        }

        $min = $prices->min();
        $max = $prices->max();

        return [
            'min' => $min,
            'max' => $max,
            'formatted_range' => $min === $max
                ? number_format($min) . ' تومان'
                : number_format($min) . ' ~ ' . number_format($max) . ' تومان',
        ];
    }
}
