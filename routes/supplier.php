<?php
use App\Livewire\Supplier\FastPricing;
use App\Livewire\Supplier\Catalog\Generate;

use Illuminate\Support\Facades\Route;
use App\Livewire\Supplier\Dashboard;
use App\Livewire\Supplier\Products\Index as ProductIndex;
use App\Livewire\Supplier\Products\Create as ProductCreate;
use App\Livewire\Supplier\Products\Edit as ProductEdit;
use App\Livewire\Supplier\Products\EditPrice;
use App\Livewire\Supplier\Settings\ManageCheckPeriods;
use App\Livewire\Supplier\ProductSelection;
use App\Livewire\Supplier\Pricing;
use App\Livewire\Supplier\MyProducts;
use App\Livewire\Supplier\Reports;
use App\Livewire\Feature\BuyFeature;


Route::middleware(['web', 'auth', 'role:supplier|regular_supplier|admin|super_admin', 'check.approval'])
    ->prefix('supplier')
    ->name('supplier.')
    ->group(function () {

        // داشبورد
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        // مدیریت محصولات تامین‌کننده
        Route::get('/products', ProductIndex::class)->name('products.index');

        Route::get('/products/create', ProductCreate::class)->name('products.create');
        Route::get('/products/{product:id}/edit', ProductEdit::class)->name('products.edit');
        Route::get('/products/{product:id}/edit-price', EditPrice::class)->name('products.edit-price');

        // تنظیمات
        Route::get('/settings/check-periods', ManageCheckPeriods::class)->name('settings.check-periods');
        Route::get('/settings/price-rounding', \App\Livewire\Supplier\Settings\ManagePriceRoundingRanges::class)->name('settings.price-rounding');
        Route::get('/settings/tier-discounts', \App\Livewire\Supplier\Settings\ManageTierDiscounts::class)->name('settings.tier-discounts');
        Route::get('/settings/category-tier-discounts', \App\Livewire\Supplier\Settings\ManageCategoryTierDiscounts::class)->name('settings.category-tier-discounts');

        Route::get('/product-selection', ProductSelection::class)->name('product-selection');
        Route::get('/pricing', Pricing::class)->name('pricing');
        Route::get('/my-products', MyProducts::class)->name('my-products');
        Route::get('/reports', Reports::class)->name('reports');
        Route::get('/fast-pricing', FastPricing::class)->name('fast-pricing');

        Route::get('/fast-pricing/import', \App\Livewire\Supplier\FastPricing\Import::class)->name('fast-pricing.import');
        Route::get('/fast-pricing/percentage-update', \App\Livewire\Supplier\FastPricing\PercentageUpdate::class)->name('fast-pricing.percentage-update');
        Route::get('/fast-pricing/review/{batchId}', \App\Livewire\Supplier\FastPricing\Review::class)->name('fast-pricing.review');

        Route::get('/catalog/index', \App\Livewire\Supplier\Catalog\Index::class)->name('catalog.index');
        Route::get('/catalogs', \App\Livewire\Supplier\Catalog\Index::class)->name('catalog.show');

        Route::get('/catalog/preview/{catalog}', \App\Livewire\Supplier\Catalog\Preview::class)->name('catalog.preview');
        
        Route::get('/catalog/{catalog}/custom-cover', \App\Livewire\Supplier\Catalog\CustomCoverSettings::class)->name('catalog.custom-cover');

        Route::get('/features/{featureId}/buy', BuyFeature::class)->name('features.buy');
        Route::get('/feature/{feature}/buy', BuyFeature::class)->name('feature.buy');

    });
