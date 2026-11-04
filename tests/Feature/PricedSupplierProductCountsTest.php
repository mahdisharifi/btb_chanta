<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\ProductController;
use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PricedSupplierProductCountsTest extends TestCase
{
    public function test_only_suppliers_with_current_prices_are_counted_once_per_product_family(): void
    {
        config(['database.default' => 'priced_counts', 'database.connections.priced_counts' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('priced_counts');
        Schema::create('products', function (Blueprint $t) {
            $t->id(); $t->integer('supplier_id')->nullable(); $t->integer('family_product_id'); $t->boolean('is_active')->default(true);
            $t->string('status')->default('active'); $t->softDeletes();
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id(); $t->integer('parent_supplier_id')->nullable(); $t->softDeletes();
        });
        Schema::create('product_supplier', function (Blueprint $t) {
            $t->id(); $t->integer('product_id'); $t->integer('supplier_id');
            $t->boolean('is_active')->default(true); $t->softDeletes();
        });
        Schema::create('product_prices', function (Blueprint $t) {
            $t->id(); $t->integer('product_supplier_id'); $t->integer('price')->default(100);
            $t->boolean('is_active')->default(true); $t->date('valid_from')->nullable(); $t->date('valid_until')->nullable();
        });
        DB::table('products')->insert([
            ['id' => 1, 'family_product_id' => 1], ['id' => 2, 'family_product_id' => 1],
            ['id' => 3, 'family_product_id' => 3],
        ]);
        foreach (range(1, 7) as $id) {
            DB::table('product_supplier')->insert([
                'id' => $id, 'product_id' => $id === 2 ? 2 : 1,
                'supplier_id' => $id === 2 ? 1 : $id,
            ]);
        }
        DB::table('product_supplier')->insert(['id' => 8, 'product_id' => 3, 'supplier_id' => 1]);
        DB::table('product_prices')->insert([
            ['product_supplier_id' => 1], ['product_supplier_id' => 1],
            ['product_supplier_id' => 2], ['product_supplier_id' => 3],
            ['product_supplier_id' => 4], ['product_supplier_id' => 5],
            ['product_supplier_id' => 6], ['product_supplier_id' => 8],
        ]);
        DB::table('product_prices')->where('product_supplier_id', 3)->update(['valid_until' => now()->subDay()->toDateString()]);
        DB::table('product_prices')->where('product_supplier_id', 4)->update(['valid_from' => now()->addDay()->toDateString()]);
        DB::table('product_prices')->where('product_supplier_id', 5)->update(['is_active' => false]);
        DB::table('product_supplier')->where('id', 6)->update(['deleted_at' => now()]);

        DB::table('suppliers')->insert(['id' => 1]);
        $controller = app(ProductController::class);
        $method = new \ReflectionMethod($controller, 'countPricedSupplierProducts');
        $query = Product::query()->whereColumn('products.id', 'products.family_product_id');
        $this->assertSame([1 => 2], $method->invoke($controller, $query));
        $supplierFilter = new \ReflectionMethod($controller, 'applySupplierFilter');
        $filtered = $supplierFilter->invoke($controller, clone $query, new \Illuminate\Http\Request(['supplier_id' => [1]]));
        $this->assertSame([1, 3], $filtered->orderBy('products.id')->pluck('products.id')->all());
        DB::table('product_supplier')->where('supplier_id', 1)->update(['is_active' => false]);
        $this->assertSame(0, $filtered->count());
        DB::table('product_supplier')->where('supplier_id', 1)->update(['is_active' => true]);
        $this->assertSame([1 => 1], $method->invoke($controller, (clone $query)->where('products.id', 1)));
    }
}
