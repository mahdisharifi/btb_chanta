<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;              // ✅ اضافه شد
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\RetailPrice;
use App\Models\SeoPage;
use App\Policies\UserPolicy;      // ✅ قبلاً بود — اکنون استفاده می‌شود
use App\Policies\ProductPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\RetailPricePolicy;
use App\Policies\SeoPagePolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Blade;
use App\Services\PriceVisibilityService;

// ✅ Event & Listener
use App\Events\CheckPercentageChanged;
use App\Listeners\UpdateAffectedCheckPrices;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Register Policies
        |--------------------------------------------------------------------------
        */
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(RetailPrice::class, RetailPricePolicy::class);
        Gate::policy(SeoPage::class, SeoPagePolicy::class);
        Gate::policy(User::class, UserPolicy::class);   // ✅ اضافه شد

        /*
        |--------------------------------------------------------------------------
        | Super Admin bypass — has ALL permissions
        |--------------------------------------------------------------------------
        */
        Gate::before(function ($user, string $ability): ?bool {
            if ($user !== null && $user->hasRole(UserRole::SUPER_ADMIN->value)) {
                return true;
            }

            return null;
        });

        /*
        |--------------------------------------------------------------------------
        | Register Events
        |--------------------------------------------------------------------------
        */
        Event::listen(
            CheckPercentageChanged::class,
            UpdateAffectedCheckPrices::class
        );

        /*
        |--------------------------------------------------------------------------
        | Register Blade Directives for Price Visibility
        |--------------------------------------------------------------------------
        */
        $this->registerPriceVisibilityDirectives();
    }

    /**
     * ثبت Blade Directives سفارشی برای کنترل نمایش قیمت‌ها
     */
    protected function registerPriceVisibilityDirectives(): void
    {
        // @canSeeRetailPrice
        Blade::if('canSeeRetailPrice', function () {
            return app(PriceVisibilityService::class)->canSeeRetailPrice(auth()->user());
        });

        // @canSeeWholesalePrice
        Blade::if('canSeeWholesalePrice', function () {
            return app(PriceVisibilityService::class)->canSeeWholesalePrice(auth()->user());
        });

        // @canSeeSupplierInfo
        Blade::if('canSeeSupplierInfo', function () {
            return app(PriceVisibilityService::class)->canSeeSupplierInfo(auth()->user());
        });

        // @canFilterByPrice
        Blade::if('canFilterByPrice', function () {
            return app(PriceVisibilityService::class)->canFilterByPrice(auth()->user());
        });

        // @canSetRetailPrice
        Blade::if('canSetRetailPrice', function () {
            return app(PriceVisibilityService::class)->canSetRetailPrice(auth()->user());
        });

        // @canSeeAnyPrice
        Blade::if('canSeeAnyPrice', function () {
            return app(PriceVisibilityService::class)->canSeeAnyPrice(auth()->user());
        });
    }
}
