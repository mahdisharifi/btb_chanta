<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Category extends Model
{
    use HasFactory, SoftDeletes, HasSlug, HasRecursiveRelationships;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'image_path',
        'sort_order',
        'is_active',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order'  => 'integer',
        'parent_id'  => 'integer',
    ];

    // ══════════════════════════════════════════════════════════
    //  SLUGGABLE CONFIG
    // ══════════════════════════════════════════════════════════

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * برای بسته staudenmeir/laravel-adjacency-list
     */
    public function getParentKeyName(): string
    {
        return 'parent_id';
    }

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS
    // ══════════════════════════════════════════════════════════

    /**
     * دسته‌بندی والد
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * فرزندان مستقیم فعال (مرتب‌شده)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
                    ->where('is_active', true)
                    ->orderBy('sort_order');
    }

    /**
     * تمام فرزندان مستقیم (بدون فیلتر active - برای ادمین)
     */
    public function allChildren(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
                    ->orderBy('sort_order');
    }

    /**
     * فرزندان بازگشتی (درختی) - فقط فعال‌ها
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * فرزندان بازگشتی - تمام (برای ادمین)
     */
    public function allChildrenRecursive(): HasMany
    {
        return $this->allChildren()->with('allChildrenRecursive');
    }

    /**
     * محصولات مستقیم این دسته‌بندی
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * فقط محصولات فعال مستقیم این دسته‌بندی
     */
    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class)
                    ->where('is_active', true)
                    ->where('status', \App\Enums\ProductStatus::ACTIVE);
    }

    // ══════════════════════════════════════════════════════════
    //  SCOPES
    // ══════════════════════════════════════════════════════════

    /**
     * فقط دسته‌بندی‌های فعال
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * فقط دسته‌بندی‌های ریشه (بدون والد)
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * مرتب‌سازی بر اساس sort_order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * دسته‌بندی‌هایی که حداقل یک محصول فعال دارند (مستقیم یا از طریق فرزندان)
     */
    public function scopeHasActiveProducts(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereHas('activeProducts')
              ->orWhereHas('children', function ($childQuery) {
                  $childQuery->where('is_active', true)
                             ->whereHas('activeProducts');
              });
        });
    }

    // ══════════════════════════════════════════════════════════
    //  ACCESSORS
    // ══════════════════════════════════════════════════════════

    /**
     * تعداد کل محصولات فعال (شامل تمام زیرمجموعه‌ها)
     * این accessor مهم‌ترین بخش برای نمایش صحیح تعداد در sidebar است
     */
    public function getTotalProductsCountAttribute(): int
    {
        // اگر از withCount لود شده
        if (array_key_exists('active_products_count', $this->attributes)) {
            $count = (int) $this->attributes['active_products_count'];
        } else {
            $count = $this->activeProducts()->count();
        }

        // اضافه‌کردن تعداد محصولات فرزندان
        if ($this->relationLoaded('children')) {
            foreach ($this->children as $child) {
                $count += $child->total_products_count;
            }
        } else {
            // fallback: جمع‌آوری id تمام فرزندان و شمارش
            $descendantIds = $this->getAllDescendantIds();
            if ($descendantIds->isNotEmpty()) {
                $count += Product::whereIn('category_id', $descendantIds)
                                 ->where('is_active', true)
                                 ->where('status', \App\Enums\ProductStatus::ACTIVE)
                                 ->count();
            }
        }

        return $count;
    }


    public function getAllDescendantIdsRecursive(): Collection
    {
        $ids = collect([$this->id]);

        $children = $this->allChildren;
        foreach ($children as $child) {
            $ids = $ids->merge($child->getAllDescendantIdsRecursive());
        }

        return $ids;
    }
    public function getHierarchicalNameAttribute(): string
    {
        $depth = $this->depth_level;
        $indent = str_repeat('— ', $depth);
        return $indent . $this->name;
    }


    /**
     * آدرس تصویر دسته‌بندی
     */
    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }
        return null;
    }

    /**
     * آدرس آیکون دسته‌بندی
     */
    public function getIconUrlAttribute(): ?string
    {
        if ($this->icon && str_starts_with($this->icon, '/')) {
            return asset('storage/' . ltrim($this->icon, '/'));
        }
        return $this->icon; // ممکن است نام کلاس آیکون باشد (مثلاً heroicon)
    }

    /**
     * عمق دسته‌بندی در درخت (ریشه = 0)
     */
    public function getDepthLevelAttribute(): int
    {
        $depth = 0;
        $current = $this;

        while ($current->parent_id !== null) {
            $depth++;
            if ($this->relationLoaded('parent') || $this->relationLoaded('ancestors')) {
                $current = $current->parent;
                if (!$current) break;
            } else {
                $current = Category::find($current->parent_id);
                if (!$current) break;
            }
        }

        return $depth;
    }

    /**
     * نام کامل مسیر (مثلاً: الکترونیک > موبایل > سامسونگ)
     */
    public function getFullPathAttribute(): string
    {
        $ancestors = $this->getAncestors();
        $names = $ancestors->pluck('name')->toArray();
        $names[] = $this->name;
        return implode(' > ', $names);
    }

    // ══════════════════════════════════════════════════════════
    //  HELPER METHODS
    // ══════════════════════════════════════════════════════════

    /**
     * آیا فرزند دارد؟
     */
    public function hasChildren(): bool
    {
        // اگر withCount('children') لود شده
        if (array_key_exists('children_count', $this->attributes)) {
            return (int) $this->attributes['children_count'] > 0;
        }

        // اگر رابطه لود شده
        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }

        return $this->children()->exists();
    }

    /**
     * آیا دسته‌بندی ریشه است؟
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * آیا برگ است (فرزندی ندارد)؟
     */
    public function isLeaf(): bool
    {
        return !$this->hasChildren();
    }

    /**
     * جمع‌آوری تمام ID فرزندان بازگشتی
     */
    public function getAllDescendantIds(): Collection
    {
        $ids = collect();

        // سطح ۱: فرزندان مستقیم
        $childIds = self::where('parent_id', $this->id)
                        ->where('is_active', true)
                        ->pluck('id');
        $ids = $ids->merge($childIds);

        // سطح ۲: نوه‌ها
        if ($childIds->isNotEmpty()) {
            $grandChildIds = self::whereIn('parent_id', $childIds)
                                 ->where('is_active', true)
                                 ->pluck('id');
            $ids = $ids->merge($grandChildIds);

            // سطح ۳: نتیجه‌ها (در صورت نیاز)
            if ($grandChildIds->isNotEmpty()) {
                $greatGrandChildIds = self::whereIn('parent_id', $grandChildIds)
                                          ->where('is_active', true)
                                          ->pluck('id');
                $ids = $ids->merge($greatGrandChildIds);
            }
        }

        return $ids;
    }

    /**
     * دریافت نیاکان برای breadcrumb
     */
    public function getAncestors(): Collection
    {
        $ancestors = collect();
        $current = $this;

        // حداکثر ۱۰ سطح عمق برای جلوگیری از حلقه بی‌نهایت
        $maxDepth = 10;
        $depth = 0;

        while ($current->parent_id !== null && $depth < $maxDepth) {
            // استفاده از رابطه لود شده اگر موجود باشد
            if ($current->relationLoaded('parent')) {
                $current = $current->parent;
            } else {
                $current = self::find($current->parent_id);
            }

            if (!$current) break;

            $ancestors->prepend($current);
            $depth++;
        }

        return $ancestors;
    }

    /**
     * آرایه breadcrumb برای نمایش
     */
    public function getBreadcrumb(): array
    {
        $breadcrumb = [];
        $ancestors = $this->getAncestors();

        foreach ($ancestors as $ancestor) {
            $breadcrumb[] = [
                'name' => $ancestor->name,
                'slug' => $ancestor->slug,
                'url'  => route('products.index', ['category' => $ancestor->slug]),
            ];
        }

        // آخرین آیتم: خود دسته‌بندی فعلی (بدون لینک)
        $breadcrumb[] = [
            'name' => $this->name,
            'slug' => $this->slug,
            'url'  => null,
        ];

        return $breadcrumb;
    }

    /**
     * دریافت درخت کامل دسته‌بندی‌ها (static - برای sidebar/filter)
     */
    public static function getTreeWithProductCounts(): Collection
    {
        return Cache::remember('category_tree_with_counts', now()->addDay(), function () {
            return static::query()
                ->active()
                ->root()
                ->ordered()
                ->with([
                    'children' => function ($q) {
                        $q->active()
                            ->ordered()
                            ->withCount(['activeProducts'])
                            ->with([
                                'children' => function ($q2) {
                                    $q2->active()
                                        ->ordered()
                                        ->withCount(['activeProducts']);
                                }
                            ]);
                    }
                ])
                ->withCount(['activeProducts'])
                ->get();
        });
    }


    /**
     * دریافت لیست مسطح (flat) برای dropdown
     */
    public static function getFlatList(string $separator = ' — '): Collection
    {
        $result = collect();
        $roots = static::active()->root()->ordered()->get();

        foreach ($roots as $root) {
            $result->push([
                'id'    => $root->id,
                'name'  => $root->name,
                'label' => $root->name,
                'depth' => 0,
            ]);

            $children = $root->children()->get();
            foreach ($children as $child) {
                $result->push([
                    'id'    => $child->id,
                    'name'  => $child->name,
                    'label' => $separator . $child->name,
                    'depth' => 1,
                ]);
                $grandChildren = $child->children()->get();
                foreach ($grandChildren as $grandChild) {
                    $result->push([
                        'id'    => $grandChild->id,
                        'name'  => $grandChild->name,
                        'label' => $separator . $separator . $grandChild->name,
                        'depth' => 2,
                    ]);
                }
            }
        }
        return $result;
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class);
    }

    /**
     * دوره‌های چک مرتبط با این دسته‌بندی برای تامین‌کنندگان مختلف
     */
    public function checkPeriods()
    {
        return $this->belongsToMany(CheckPeriod::class, 'category_check_period')
            ->withPivot(['supplier_id', 'percentage_increase', 'is_active'])
            ->withTimestamps();
    }

    /**
     * دریافت درصد افزایش برای یک دوره چک خاص و یک تامین‌کننده خاص
     * 
     * @param int $checkPeriodId
     * @param int $supplierId
     * @return float|null
     */
    public function getCheckPercentageForSupplier(int $checkPeriodId, int $supplierId): ?float
    {
        $pivot = \DB::table('category_check_period')
            ->where('category_id', $this->id)
            ->where('check_period_id', $checkPeriodId)
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->first();

        return $pivot ? (float)$pivot->percentage_increase : null;
    }

    /**
     * آیا این دسته‌بندی برای تامین‌کننده خاص تنظیمات دوره چک دارد؟
     * 
     * @param int $supplierId
     * @return bool
     */
    public function hasCheckPeriodsForSupplier(int $supplierId): bool
    {
        return \DB::table('category_check_period')
            ->where('category_id', $this->id)
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * دریافت تمام تنظیمات دوره چک برای یک تامین‌کننده خاص
     * 
     * @param int $supplierId
     * @return \Illuminate\Support\Collection
     */
    public function getCheckPeriodsForSupplier(int $supplierId): \Illuminate\Support\Collection
    {
        return \DB::table('category_check_period')
            ->join('check_periods', 'category_check_period.check_period_id', '=', 'check_periods.id')
            ->where('category_check_period.category_id', $this->id)
            ->where('category_check_period.supplier_id', $supplierId)
            ->where('category_check_period.is_active', true)
            ->select([
                'check_periods.id',
                'check_periods.name',
                'check_periods.days',
                'category_check_period.percentage_increase'
            ])
            ->orderBy('check_periods.days')
            ->get();
    }

}
