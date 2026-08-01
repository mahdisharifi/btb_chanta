<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Category;

class CategoryHelper
{
    /**
     * بررسی می‌کند که آیا دسته‌بندی فعلی یا هر یک از فرزندانش
     * (در هر عمقی) با slug فعال مطابقت دارند.
     *
     * @param  Category      $category   دسته‌بندی ریشه برای بررسی
     * @param  string|null   $activeSlug slug صفحه فعال فعلی
     * @return bool                      true اگر تطابق یافت، در غیر این صورت false
     */
    public static function isActiveOrHasActiveChild(Category $category, ?string $activeSlug): bool
    {
        if ($activeSlug === null) {
            return false;
        }

        // خود دسته
        if ($category->slug === $activeSlug) {
            return true;
        }

        // فرزندان (در صورت لود شدن — بدون ایجاد N+1)
        if ($category->relationLoaded('children')) {
            foreach ($category->children as $child) {
                if (self::isActiveOrHasActiveChild($child, $activeSlug)) {
                    return true;
                }
            }
        }

        return false;
    }
    public static function getCategoryTree()
    {
        return \App\Models\Category::whereNull('parent_id')
            ->with('childrenRecursive')
            ->orderBy('sort_order')
            ->get();
    }
}
