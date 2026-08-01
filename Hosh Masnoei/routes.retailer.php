<?php

use App\Livewire\Retailer\Dashboard;
use Illuminate\Support\Facades\Route;
use App\Livewire\Feature\BuyFeature;


// محافظت شده با میدل‌ویر احراز هویت و بررسی انقضای اشتراک
Route::middleware(['auth', 'check.subscription', 'check.approval'])->prefix('retailer')->name('retailer.')->group(function () {

// داشبورد اصلی کاسب
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

// روت مربوط به خرید اشتراک که در فاز قبل ساخته شد
    Route::get('/subscriptions', \App\Livewire\Retailer\Subscriptions\Index::class)->name('subscriptions.index');


    // این خط را در انتهای گروه Route های کاسب ویژه اضافه کنید
    Route::get('/features/{featureId}/buy', BuyFeature::class)->name('features.buy');
    Route::get('/feature/{feature}/buy', BuyFeature::class)->name('feature.buy');

});
