<?php
// app/Models/Product.php

namespace App\Models;
use Illuminate\Support\Str; // این خط را در بالای فایل اضافه کنید
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use App\Exceptions\ProductSlugRedirectException;
use App\Models\ProductSlugRedirect;
use Illuminate\Support\Collection;

class Product extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'category_id',
        'title',
        'slug',
        'sku',
        'short_description',
        'full_description',
        'specifications',
        'weight',
        'dimensions',
        'quantity_tiers', // تیراژهای محصول
        'packaging_unit_type', // ✅ نوع واحد فروش
        'quantity_per_packaging', // ✅ تعداد در هر واحد
        'is_active',
        'is_featured',
        'status',
        'view_count',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_canonical_url',
        'legacy_id',
    ];

    protected $casts = [
        'specifications' => 'array',
        'dimensions'     => 'array',
        'quantity_tiers' => 'array', // تیراژها به صورت JSON
        'quantity_per_packaging' => 'integer', // ✅ تعداد در هر واحد
        'is_active'      => 'boolean',
        'is_featured'    => 'boolean',
        'status'         => ProductStatus::class,
        'view_count'     => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }


    public function resolveRouteBinding($value, $field = null)
    {
        $product = $this->where($field ?? $this->getRouteKeyName(), $value)->first();

        if ($product) {
            return $product;
        }

        $redirect = ProductSlugRedirect::where('old_slug', $value)
            ->with('product')
            ->first();

        if ($redirect?->product) {
            throw new ProductSlugRedirectException($redirect->product->slug);
        }

        return null;
    }

    public function slugRedirects(): HasMany
    {
        return $this->hasMany(ProductSlugRedirect::class);
    }
    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS — اصلی (سازگار با ساختار فعلی)
    // ══════════════════════════════════════════════════════════

    /**
     * تأمین‌کننده اصلی (از ستون supplier_id)
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
            // فیلدهای ناموجود مثل price حذف شدند
            ->withPivot('id', 'sku', 'is_primary', 'is_active', 'sort_order')
            ->withTimestamps();
    }



    /**
     * دسته‌بندی محصول
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * تمام قیمت‌های عمده (از جدول product_prices)
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * فقط قیمت‌های فعال عمده
     */
    public function activePrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('valid_until')
                          ->orWhere('valid_until', '>=', now());
                    });
    }

    /**
     * قیمت‌های خرده‌فروشی (تعیین‌شده توسط کاسب‌ها)
     */
    public function retailPrices(): HasMany
    {
        return $this->hasMany(RetailPrice::class);
    }

    /**
     * گالری تصاویر و ویدئوها
     */
    public function galleries(): HasMany
    {
        return $this->hasMany(ProductGallery::class)->orderBy('sort_order');
    }

    /**
     * تصویر اصلی محصول
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductGallery::class)
                    ->where('is_primary', true)
                    ->where('file_type', 'image');
    }

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS — چند تأمین‌کننده (جدول واسط product_supplier)
    // ══════════════════════════════════════════════════════════

    /**
     * تمام تأمین‌کنندگان این محصول (Many-to-Many)
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
                    ->withPivot(['sku', 'is_primary', 'is_active', 'sort_order'])
                    ->withTimestamps()
                    ->orderByPivot('sort_order');
    }

    /**
     * فقط تأمین‌کنندگان فعال
     */
    public function activeSuppliers(): BelongsToMany
    {
        return $this->suppliers()->wherePivot('is_active', true);
    }

    /**
     * رکوردهای جدول واسط product_supplier (دسترسی مستقیم)
     * اگر مدل ProductSupplier وجود ندارد، این رابطه غیرفعال شود
     */
    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    /**
     * تأمین‌کننده اصلی از جدول واسط
     */
    public function primaryProductSupplier(): HasOne
    {
        return $this->hasOne(ProductSupplier::class)->where('is_primary', true);
    }

    // ══════════════════════════════════════════════════════════
    //  SCOPES
    // ══════════════════════════════════════════════════════════

    /**
     * فقط محصولات فعال و تأیید شده
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
                     ->where('status', ProductStatus::ACTIVE);
    }

    /**
     * محصولات ویژه
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * فیلتر بر اساس دسته‌بندی (شامل زیرمجموعه‌ها)
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        // ابتدا خود دسته‌بندی و تمام فرزندان بازگشتی آن را پیدا می‌کنیم
        $categoryIds = Category::where('id', $categoryId)
            ->orWhere('parent_id', $categoryId)
            ->pluck('id')
            ->toArray();

        // اگر زیرمجموعه‌های عمیق‌تر هم بخواهیم (سطح ۳):
        $deeperIds = Category::whereIn('parent_id', $categoryIds)->pluck('id')->toArray();
        $allIds = array_unique(array_merge([$categoryId], $categoryIds, $deeperIds));

        return $query->whereIn('category_id', $allIds);
    }

    /**
     * فیلتر بر اساس تأمین‌کننده اصلی
     */
    public function scopeBySupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * محصولاتی که چند تأمین‌کننده دارند
     */
    public function scopeMultiSupplier(Builder $query): Builder
    {
        return $query->has('activeSuppliers', '>', 1);
    }

    /**
     * فیلتر بر اساس بازه قیمت
     */
    public function scopePriceRange(Builder $query, ?int $minPrice, ?int $maxPrice): Builder
    {
        return $query->whereHas('prices', function ($q) use ($minPrice, $maxPrice) {
            $q->where('is_active', true);
            if ($minPrice !== null) {
                $q->where('price', '>=', $minPrice);
            }
            if ($maxPrice !== null) {
                $q->where('price', '<=', $maxPrice);
            }
        });
    }

    /**
     * جستجو در عنوان، SKU و توضیحات
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
              ->orWhere('sku', 'LIKE', "%{$term}%")
              ->orWhere('short_description', 'LIKE', "%{$term}%");
        });
    }

    // ══════════════════════════════════════════════════════════
    //  ACCESSORS
    // ══════════════════════════════════════════════════════════

    /**
     * تعداد تأمین‌کنندگان فعال (با بهره‌گیری از withCount یا محاسبه)
     */
    public function getActiveSuppliersCountAttribute(): int
    {
        // اگر withCount('activeSuppliers') استفاده شده باشد:
        if (array_key_exists('active_suppliers_count', $this->attributes)) {
            return (int) $this->attributes['active_suppliers_count'];
        }

        // fallback: مستقیم شمارش
        return $this->activeSuppliers()->count();
    }

    /**
     * کمترین قیمت فعال (عمده)
     */
    public function getLowestPriceAttribute(): ?int
    {
        // اگر از subquery لود شده:
        if (array_key_exists('lowest_price', $this->attributes)) {
            return $this->attributes['lowest_price'] ? (int) $this->attributes['lowest_price'] : null;
        }

        return $this->activePrices()->min('price');
    }

    /**
     * بالاترین قیمت فعال (عمده)
     */
    public function getHighestPriceAttribute(): ?int
    {
        if (array_key_exists('highest_price', $this->attributes)) {
            return $this->attributes['highest_price'] ? (int) $this->attributes['highest_price'] : null;
        }

        return $this->activePrices()->max('price');
    }

    /**
     * کمترین قیمت فرمت‌شده (تومان)
     */
    public function getFormattedLowestPriceAttribute(): string
    {
        $price = $this->lowest_price;
        if (!$price) {
            return 'بدون قیمت';
        }
        return number_format($price) . ' تومان';
    }

    /**
     * آخرین قیمت خرده‌فروشی منتشرشده
     */
    public function getLatestRetailPriceAttribute(): ?int
    {
        return $this->retailPrices()
                    ->where('is_published', true)
                    ->latest()
                    ->value('retail_price');
    }

    /**
     * قیمت خرده‌فروشی فرمت‌شده
     */
    public function getFormattedRetailPriceAttribute(): string
    {
        $price = $this->latest_retail_price;
        if (!$price) {
            return 'بدون قیمت';
        }
        return number_format($price) . ' تومان';
    }

    /**
     * آیا چند تأمین‌کننده دارد؟
     */
    public function getHasMultipleSuppliersAttribute(): bool
    {
        if (array_key_exists('active_suppliers_count', $this->attributes)) {
            return (int) $this->attributes['active_suppliers_count'] > 1;
        }

        return $this->activeSuppliers()->count() > 1;
    }

    /**
     * آدرس تصویر اصلی (برای کارت محصول)
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        // اگر رابطه eager load شده:
        if ($this->relationLoaded('primaryImage') && $this->primaryImage) {
            return asset('storage/' . $this->primaryImage->file_path);
        }

        // اگر galleries لود شده:
        if ($this->relationLoaded('galleries') && $this->galleries->isNotEmpty()) {
            $primary = $this->galleries->firstWhere('is_primary', true);
            if ($primary) {
                return asset('storage/' . $primary->file_path);
            }
            return asset('storage/' . $this->galleries->first()->file_path);
        }

        return null; // بدون تصویر
    }

    /**
     * بازه قیمت عمده برای نمایش
     */
    public function getPriceRangeTextAttribute(): string
    {
        $low = $this->lowest_price;
        $high = $this->highest_price;

        if (!$low) {
            return 'بدون قیمت';
        }

        if ($low === $high || !$high) {
            return number_format($low) . ' تومان';
        }

        return number_format($low) . ' ~ ' . number_format($high) . ' تومان';
    }
    public static function generateUniqueSku(): string
    {
        do {
            // تولید شناسه با پیشوند PRD و 8 کاراکتر تصادفی حروف و عدد
            $sku = 'PRD-' . strtoupper(Str::random(8));
        } while (self::where('sku', $sku)->exists());

        return $sku;
    }

    // ... سایر کدهای موجود در مدل ...

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class)
            ->withPivot('value')
            ->withTimestamps();
    }
    public function attributeValues(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_product')
            ->withPivot('value') // برای مواردی که مقدار خاص (سفارشی) دارید
            ->withTimestamps();
    }



    /**
     * تمام تصاویر گالری با ترتیب نمایش
     */
    public function sortedGalleries(): HasMany
    {
        return $this->hasMany(ProductGallery::class)
            ->where('file_type', 'image')
            ->orderBy('sort_order')
            ->orderByDesc('is_primary');
    }

    /**
     * تمام مدیای محصول (images + videos) - مرتب شده
     */
    public function allMediaSorted(): HasMany
    {
        return $this->hasMany(ProductGallery::class)
            ->orderBy('sort_order')
            ->orderByDesc('is_primary');
    }

    /**
     * دریافت تمام تصاویر در سایز مشخص (xs|sm|md|lg|xl|main) با سورت
     */
    public function imagesBySize(string $size = 'md'): Collection
    {
        return $this->sortedGalleries
            ->map(fn (ProductGallery $gallery) => [
                'id'       => $gallery->id,
                'url'      => $gallery->getVersionUrl($size),
                'alt_text' => $gallery->alt_text,
                'title'    => $gallery->title,
            ]);
    }
    
    /**
     * ✅ برچسب فارسی نوع واحد بسته‌بندی
     */
    public function getPackagingUnitLabelAttribute(): string
    {
        $labels = [
            'carton'  => 'کارتن',
            'jean'    => 'جین',
            'jowab'   => 'جورب',
            'package' => 'بسته',
            'bundle'  => 'شیرینگ',
            'roll'    => 'طاقه',
            'piece'   => 'عدد',
        ];

        return $labels[$this->packaging_unit_type] ?? $this->packaging_unit_type;
    }

    /**
     * ✅ نمایش کامل واحد بسته‌بندی (مثلاً: "4 عدد در کارتن")
     */
    public function getPackagingDisplayAttribute(): string
    {
        if (!$this->quantity_per_packaging || $this->quantity_per_packaging <= 1) {
            return '';
        }

        $unitLabel = $this->packaging_unit_label;
        $quantity = $this->quantity_per_packaging;

        // برای واحد‌هایی که متر یا جفت دارند
        $unitContents = [
            'roll' => 'متر',   // طاقه = متر
            'jowab' => 'جفت',  // جورب = جفت
        ];

        $contentUnit = $unitContents[$this->packaging_unit_type] ?? 'عدد';

        return "{$quantity} {$contentUnit} در {$unitLabel}";
    }
}
