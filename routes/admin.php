<?php
use App\Livewire\Admin\System\ArtisanRunner;

use Illuminate\Support\Facades\Route;
// کلاس‌های Livewire پنل ادمین
use App\Livewire\Admin\Flowcharts\Index;
use App\Livewire\Admin\Flowcharts\Form;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Admin\Users\Create as UsersCreate;
use App\Livewire\Admin\Users\Edit as UsersEdit;
use App\Livewire\Admin\Products\Index as AdminProductIndex;
use App\Livewire\Admin\Products\Create as AdminProductCreate;
use App\Livewire\Admin\Products\Show as AdminProductShow;
use App\Livewire\Admin\Categories\Index as CategoriesIndex;
use App\Livewire\Admin\Categories\Create as CategoryCreate;
use App\Livewire\Admin\Categories\Edit as CategoryEdit;
use App\Livewire\Admin\Suppliers\Index as SupplierIndex;
use App\Livewire\Admin\Suppliers\Create as SupplierCreate;
use App\Livewire\Admin\Suppliers\Edit as SupplierEdit;

use App\Livewire\Admin\Suppliers\Index as SuppliersIndex;
use App\Livewire\Admin\Seo\Index as SeoIndex;
use App\Livewire\Admin\Geography\Manager as GeographyManager;
use App\Livewire\Admin\Categories\Create;

use App\Livewire\Supplier\Catalog\Preview as CatalogPreview;

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    // استفاده از همان کامپوننت تامین‌کننده برای ادمین
    Route::get('/catalogs/manage', \App\Livewire\Supplier\Catalog\Index::class)
        ->name('catalogs.manage');
    
    Route::get('/catalogs/{catalog}/preview', CatalogPreview::class)
        ->name('catalogs.preview');
});


Route::middleware(['auth', 'role:super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // داشبورد
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        // کاربران
        Route::get('/users', UsersIndex::class)->name('users.index');
        Route::get('/users/create', UsersCreate::class)->name('users.create');
        Route::get('/users/{user}/edit', UsersEdit::class)->name('users.edit');

        // محصولات
        Route::get('products', AdminProductIndex::class)->name('products.index');
        Route::get('products/create', AdminProductCreate::class)->name('products.create');
        Route::get('products/{product:id}/edit', AdminProductCreate::class)->name('products.edit');
        Route::get('products/{product:id}/show', AdminProductShow::class)->name('products.show');

        // دسته‌بندی‌ها
        Route::get('/categories', CategoriesIndex::class)->name('categories.index');
//        Route::get('/categories/create', CategoryCreate::class)->name('categories.create');
//        Route::get('/categories/{category:id}/edit', CategoryEdit::class)->name('categories.edit');
        Route::get('/categories/create', Create::class)
            ->name('categories.create');

        Route::get('/categories/{category:id}/edit', Create::class)
            ->name('categories.edit');


        // تامین‌کنندگان
        Route::get('/suppliers', SupplierIndex::class)->name('suppliers.index');
        Route::get('/suppliers/create', SupplierCreate::class)->name('suppliers.create');
        
        // ویرایش تامین‌کننده (ادمین + تامین‌کننده خودش)
        Route::middleware(['auth', 'role:super_admin|supplier|regular_supplier'])
            ->get('/suppliers/{supplier:id}/edit', SupplierEdit::class)
            ->name('suppliers.edit');
//        Route::get('/suppliers/pending', \App\Livewire\Admin\Suppliers\Pending::class)
//            ->name('suppliers.pending');
//        Route::get('/suppliers/pending', \App\Livewire\Admin\Suppliers\Pending::class)
//            ->name('users.show');

        Route::get('/users/pending', \App\Livewire\Admin\Users\Pending::class)->name('users.pending');
        Route::get('/users/show', \App\Livewire\Admin\Users\Pending::class)->name('users.show');



        // سئو

        Route::get('/seo', SeoIndex::class)->name('seo.index');

        Route::prefix('attributes')->name('attributes.')->group(function () {
            Route::get('/', App\Livewire\Admin\Attributes\Index::class)->name('index');
            Route::get('/create', App\Livewire\Admin\Attributes\Create::class)->name('create');
            Route::get('/{attribute}/edit', \App\Livewire\Admin\Attributes\Create::class)->name('edit');

        });
        Route::get('/subscription-plans', App\Livewire\Admin\SubscriptionPlans\Index::class)->name('subscription-plans.index');

        Route::prefix('flowcharts')->name('flowcharts.')->group(function () {
            Route::get('/', Index::class)->name('index');
            Route::get('/create', Form::class)->name('create');
            Route::get('/{flowchart}/edit', Form::class)->name('edit');
        });
        Route::get('/system/terminal', ArtisanRunner::class)->name('system.terminal');


        Route::get('/geography', GeographyManager::class)->name('geography.index');

        // تنظیمات دسترسی نمایش قیمت
        Route::get('/settings/price-visibility', \App\Livewire\Admin\Settings\ManagePriceVisibility::class)
            ->name('settings.price-visibility');

    });

