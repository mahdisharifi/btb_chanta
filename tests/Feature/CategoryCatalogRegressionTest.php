<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\CategoryCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryCatalogRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // This suite never migrates, truncates or connects to the project's MySQL database.
        config(['database.default' => 'category_regression', 'database.connections.category_regression' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array']);
        DB::purge('category_regression');
        Schema::create('categories', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('parent_id')->nullable(); $t->string('name'); $t->string('slug')->unique();
            $t->string('icon')->nullable(); $t->string('image_path')->nullable(); $t->text('description')->nullable();
            $t->string('seo_title')->nullable(); $t->string('seo_description')->nullable();
            $t->boolean('is_active')->default(true); $t->integer('sort_order')->default(0); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('category_id'); $t->string('title')->default('Product'); $t->string('slug')->nullable();
            $t->boolean('is_active')->default(true); $t->string('status')->default('active'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('category_slug_redirects', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('category_id'); $t->string('old_slug')->unique(); $t->timestamps();
        });
        Cache::flush();
    }

    private function category(string $slug, ?int $parent = null, bool $active = true): Category
    {
        return Category::create(['name' => $slug, 'slug' => $slug, 'parent_id' => $parent, 'is_active' => $active]);
    }

    public function test_full_depth_counts_are_cached_without_accessor_queries(): void
    {
        $root = $this->category('root'); $leaf = $root;
        for ($depth = 1; $depth <= 5; $depth++) $leaf = $this->category('level-'.$depth, $leaf->id);
        DB::table('products')->insert([
            ['category_id' => $leaf->id, 'is_active' => true, 'status' => 'active'],
            ['category_id' => $leaf->id, 'is_active' => false, 'status' => 'active'],
            ['category_id' => $root->id, 'is_active' => true, 'status' => 'draft'],
        ]);
        $catalog = app(CategoryCatalog::class);
        $this->assertSame(1, $catalog->tree()->first()->total_products_count);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame(1, $catalog->tree()->sum('total_products_count'));
        $this->assertCount(6, $catalog->flatten());
        $this->assertSame([], DB::getQueryLog());
        $this->assertCount(5, $root->getAllDescendantIds());
    }

    public function test_inactive_and_deleted_ancestors_prune_the_whole_branch_and_restore_invalidates(): void
    {
        $root = $this->category('root'); $child = $this->category('child', $root->id);
        $catalog = app(CategoryCatalog::class);
        $this->assertCount(2, $catalog->flatten());
        $root->update(['is_active' => false]);
        $this->assertCount(0, $catalog->flatten());
        $root->update(['is_active' => true]); $root->delete();
        $this->assertCount(0, $catalog->flatten());
        $root->restore();
        $this->assertCount(2, $catalog->flatten());
        $this->assertTrue($catalog->flatten()->has($child->id));
    }

    public function test_rollback_does_not_publish_or_rotate_the_catalog(): void
    {
        $root = $this->category('root'); app(CategoryCatalog::class)->tree();
        $generation = Cache::get(CategoryCatalog::GENERATION_KEY);
        DB::beginTransaction();
        $root->update(['is_active' => false]);
        DB::rollBack();
        $this->assertSame($generation, Cache::get(CategoryCatalog::GENERATION_KEY));
        $this->assertCount(1, app(CategoryCatalog::class)->tree());
    }

    public function test_deep_cycle_is_rejected_and_database_is_unchanged(): void
    {
        $root = $this->category('root'); $child = $this->category('child', $root->id); $leaf = $this->category('leaf', $child->id);
        try {
            $root->update(['parent_id' => $leaf->id]);
            $this->fail('Cycle was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }
        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_existing_corruption_does_not_loop_in_helpers_or_public_tree(): void
    {
        $a = $this->category('a'); $b = $this->category('b', $a->id);
        DB::table('categories')->where('id', $a->id)->update(['parent_id' => $b->id]);
        CategoryCatalog::invalidate();
        $this->assertCount(0, app(CategoryCatalog::class)->tree());
        $this->assertSame(1, $a->fresh()->depth_level);
        $this->assertCount(2, $a->fresh()->getAllDescendantIdsRecursive());
    }

    public function test_slug_history_is_atomic_reserved_and_points_to_the_current_category(): void
    {
        $category = $this->category('first');
        $category->update(['slug' => 'second']); $category->update(['slug' => 'third']);
        $this->assertSame([$category->id, $category->id], DB::table('category_slug_redirects')->orderBy('id')->pluck('category_id')->all());
        $this->assertSame(['first', 'second'], DB::table('category_slug_redirects')->orderBy('id')->pluck('old_slug')->all());
        try {
            $this->category('first'); $this->fail('Historical slug reused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }
        $this->assertSame(1, Category::count());
        DB::beginTransaction(); $category->update(['slug' => 'fourth']); DB::rollBack();
        $this->assertSame('third', $category->fresh()->slug);
        $this->assertSame(2, DB::table('category_slug_redirects')->count());
    }
}
