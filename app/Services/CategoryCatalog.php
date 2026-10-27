<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CategoryCatalog
{
    public const GENERATION_KEY = 'categories.catalog.v2.generation';

    public function tree(): EloquentCollection
    {
        // A stable initial generation needs no competing initialization writes.
        $generation = Cache::get(self::GENERATION_KEY, 'initial');

        return Cache::remember('categories.catalog.v2.'.$generation, 3600, function () {
            $nodes = Category::query()->active()->withCount('activeProducts')
                ->orderBy('sort_order')->orderBy('id')->get();
            $groups = $nodes->groupBy(fn (Category $category) => $category->parent_id ?? 'root');
            $build = function ($parent, array $path = []) use (&$build, $groups) {
                $result = new EloquentCollection;
                foreach ($groups->get($parent, collect()) as $node) {
                    if (isset($path[$node->id])) {
                        continue;
                    }
                    $children = $build($node->id, $path + [$node->id => true]);
                    $node->setRelation('children', $children);
                    $node->setAttribute('total_products_count', (int) $node->active_products_count + $children->sum('total_products_count'));
                    $result->push($node);
                }
                return $result;
            };

            return $build('root');
        });
    }

    public function flatten(?Collection $nodes = null): Collection
    {
        $result = collect();
        foreach ($nodes ?? $this->tree() as $node) {
            $result->put($node->id, $node);
            $result = $result->union($this->flatten($node->getRelation('children')));
        }
        return $result;
    }

    public static function invalidate(): void
    {
        // In-flight readers can only populate the previous generation, never this one.
        Cache::forever(self::GENERATION_KEY, (string) Str::uuid());
        foreach (['categories.index.tree', 'category_tree_with_counts', 'sitemap_categories', 'sitemap_index'] as $key) {
            Cache::forget($key);
        }
    }
}
