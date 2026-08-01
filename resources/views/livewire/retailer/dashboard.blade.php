<div>
    <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 p-6 mt-6">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <x-heroicon-o-document-duplicate class="w-6 h-6 text-indigo-500" />
                    امکانات جانبی: خروجی کاتالوگ PDF
                </h3>
                <p class="text-sm text-gray-500 mt-1">با فعالسازی این امکان می‌توانید محصولات خود را به صورت کاتالوگ حرفه‌ای خروجی بگیرید.</p>
            </div>

            @if(auth()->user()->hasFeature('catalog-pdf'))
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                <x-heroicon-m-check-circle class="w-4 h-4 ml-1" />
                فعال
            </span>
            @else
            <!-- شناسه 1 جهت تست (آیدی ویژگی مربوطه در دیتابیس) -->
            <a href="{{ auth()->user()->isSupplier() ? route('supplier.features.buy', 1) : route('retailer.features.buy', 1) }}" class="inline-flex items-center px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-lg font-bold text-sm text-indigo-700 hover:bg-indigo-100 transition-colors">
                خرید و فعالسازی
                <x-heroicon-m-chevron-left class="w-4 h-4 mr-1" />
            </a>
            @endif
        </div>
    </div>

    @section('header', 'داشبورد کاربری')

    <!-- Welcome & Subscription Status -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-gray-100 flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 mb-1">خوش آمدید، {{ auth()->user()->name }} عزیز</h2>
            <p class="text-gray-500">خلاصه وضعیت حساب کاربری شما در سیستم عمده‌فروشی</p>
        </div>

        @if($isVip)
        <div class="flex items-center gap-3 bg-green-50 border border-green-100 p-4 rounded-lg">
            <div class="bg-green-100 p-2 rounded-full text-green-600">
                <x-heroicon-s-check-badge class="w-8 h-8" />
            </div>
            <div>
                <p class="text-sm text-green-800 font-semibold">اشتراک VIP شما فعال است</p>
                <p class="text-xs text-green-600 mt-1">اعتبار تا: {{ jdate($activeSubscription->ends_at)->format('Y/m/d H:i') }}</p>
            </div>
        </div>
        @else
        <div class="flex items-center gap-3 bg-yellow-50 border border-yellow-100 p-4 rounded-lg">
            <div class="bg-yellow-100 p-2 rounded-full text-yellow-600">
                <x-heroicon-s-exclamation-triangle class="w-8 h-8" />
            </div>
            <div>
                <p class="text-sm text-yellow-800 font-semibold">حساب شما به صورت پایه است</p>
                <a href="{{ route('retailer.subscriptions.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium mt-1 inline-block underline">ارتقا به VIP برای مشاهده قیمت‌ها</a>
            </div>
        </div>
        @endif
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="bg-blue-50 p-3 rounded-lg text-blue-600">
                <x-heroicon-o-shopping-cart class="w-8 h-8" />
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">کالاهای جدید امروز</p>
                <p class="text-2xl font-bold text-gray-800">14</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="bg-purple-50 p-3 rounded-lg text-purple-600">
                <x-heroicon-o-users class="w-8 h-8" />
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">تامین‌کنندگان فعال</p>
                <p class="text-2xl font-bold text-gray-800">58</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="bg-orange-50 p-3 rounded-lg text-orange-600">
                <x-heroicon-o-star class="w-8 h-8" />
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">وضعیت حساب</p>
                <p class="text-xl font-bold {{ $isVip ? 'text-green-600' : 'text-gray-800' }}">
                    {{ $isVip ? 'پریمیوم (VIP)' : 'عادی (پایه)' }}
                </p>
            </div>
        </div>
    </div>
</div>
