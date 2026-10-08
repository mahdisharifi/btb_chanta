<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Events\CheckPercentageChanged;
use App\Listeners\UpdateAffectedCheckPrices;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\ProductCompletionCommission;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionSubmission;
use App\Models\ProductCompletionTask;
use App\Models\RetailPrice;
use App\Models\SeoPage;
use App\Models\Supplier;
use App\Models\User;
use App\Observers\SupplierObserver;
use App\Policies\CategoryPolicy;
use App\Policies\CommissionRulePolicy;
use App\Policies\ProductCompletionCommissionPolicy;
use App\Policies\ProductCompletionMediaPolicy;
use App\Policies\ProductCompletionSubmissionPolicy;
use App\Policies\ProductCompletionTaskPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RetailPricePolicy;
use App\Policies\SeoPagePolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use App\Services\PriceVisibilityService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ProductCompletionTask::class, ProductCompletionTaskPolicy::class);
        Gate::policy(ProductCompletionSubmission::class, ProductCompletionSubmissionPolicy::class);
        Gate::policy(ProductCompletionMedia::class, ProductCompletionMediaPolicy::class);
        Gate::policy(ProductCompletionCommission::class, ProductCompletionCommissionPolicy::class);
        Gate::policy(CommissionRule::class, CommissionRulePolicy::class);

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
        | Register Observers
        |--------------------------------------------------------------------------
        */
        Supplier::observe(SupplierObserver::class);

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
