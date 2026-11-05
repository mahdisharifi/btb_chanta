<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\ProductController;
use App\Models\Product;
use App\Services\PriceVisibilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCatalogPriceOrderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'catalog_ordering', 'database.connections.catalog_ordering' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('catalog_ordering');
        Schema::create('products', function (Blueprint $t) {
            $t->id(); $t->integer('family_product_id'); $t->integer('supplier_id')->nullable();
            $t->integer('category_id')->nullable(); $t->string('title'); $t->string('sku')->nullable();
            $t->text('short_description')->nullable(); $t->boolean('is_active')->default(true);
            $t->string('status')->default('active'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id(); $t->string('company_name')->nullable(); $t->string('brand_name')->nullable();
            $t->boolean('is_active')->default(true); $t->boolean('is_verified')->default(true); $t->softDeletes();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes();
        });
        Schema::create('product_supplier', function (Blueprint $t) {
            $t->id(); $t->integer('product_id'); $t->integer('supplier_id');
            $t->boolean('is_active')->default(true); $t->softDeletes();
        });
        Schema::create('product_prices', function (Blueprint $t) {
            $t->id(); $t->integer('product_supplier_id'); $t->integer('price')->default(100);
            $t->boolean('is_active')->default(true); $t->date('valid_from')->nullable(); $t->date('valid_until')->nullable();
        });
        foreach (range(1, 8) as $id) {
            DB::table('products')->insert([
                'id' => $id, 'family_product_id' => $id === 8 ? 3 : $id,
                'title' => $id === 2 ? 'Other title' : 'Matching product',
                'short_description' => 'Matching description', 'created_at' => '2026-01-01 00:00:00',
            ]);
        }
        DB::table('suppliers')->insert([['id' => 1, 'is_verified' => true], ['id' => 2, 'is_verified' => false]]);
        foreach ([2, 4, 5, 6, 7, 8] as $id) {
            DB::table('product_supplier')->insert(['id' => $id, 'product_id' => $id, 'supplier_id' => $id === 6 ? 2 : 1]);
            DB::table('product_prices')->insert(['product_supplier_id' => $id]);
        }
        DB::table('product_prices')->where('product_supplier_id', 4)->update(['valid_until' => now()->subDay()->toDateString()]);
        DB::table('product_prices')->where('product_supplier_id', 5)->update(['valid_from' => now()->addDay()->toDateString()]);
        DB::table('product_prices')->where('product_supplier_id', 7)->update(['is_active' => false]);
    }

    private function results(array $parameters = []): \Illuminate\Database\Eloquent\Builder
    {
        $controller = app(ProductController::class);
        $request = new Request($parameters);
        $query = Product::query()->canonical();
        foreach (['applySearch', 'applyPriceFilter', 'applySorting'] as $method) {
            $query = (new \ReflectionMethod($controller, $method))->invoke($controller, $query, $request);
        }

        return $query;
    }

    public function test_default_catalog_keeps_unpriced_products_after_current_verified_family_prices(): void
    {
        $this->assertSame([3, 2, 7, 6, 5, 4, 1], $this->results()->pluck('products.id')->all());
        $this->assertSame([2, 3, 7, 6, 5, 4, 1], $this->results(['sort' => 'name_desc'])->pluck('products.id')->all());
    }

    public function test_search_groups_by_price_before_relevance_and_preserves_both_groups_across_pages(): void
    {
        $query = $this->results(['q' => 'Matching']);
        $this->assertSame([3, 2, 7, 6, 5, 4, 1], $query->pluck('products.id')->all());
        $first = (clone $query)->paginate(2, ['products.*'], 'page', 1);
        $second = (clone $query)->paginate(2, ['products.*'], 'page', 2);
        $this->assertSame(7, $first->total());
        $this->assertSame([3, 2], $first->getCollection()->pluck('id')->all());
        $this->assertSame([7, 6], $second->getCollection()->pluck('id')->all());
    }

    public function test_explicit_price_filter_can_exclude_unpriced_search_matches(): void
    {
        $this->mock(PriceVisibilityService::class)->shouldReceive('canFilterByPrice')->once()->andReturn(true);
        $ids = $this->results(['q' => 'Matching', 'min_price' => 50])->pluck('products.id')->all();
        $this->assertNotContains(1, $ids);
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
    }
}
