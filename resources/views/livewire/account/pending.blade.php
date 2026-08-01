<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-amber-50 to-orange-50 px-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
            <!-- آیکن -->
            <div class="mb-6">
                <div class="w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mx-auto ring-8 ring-amber-50">
                    <x-heroicon-o-clock class="w-10 h-10 text-amber-600 animate-pulse" />
                </div>
            </div>

            <!-- محتوا -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">حساب شما در حال بررسی است</h1>
            <p class="text-gray-600 leading-relaxed mb-8">
                ثبت‌نام شما با موفقیت انجام شد. اطلاعات شما توسط کارشناسان ما در حال بررسی می‌باشد. پس از تایید نهایی، دسترسی شما به پنل فعال خواهد شد.
            </p>

            @if (Auth::user()->isSupplier() || Auth::user()->isRegularSupplier())
            <a href="{{ route('supplier.profile.edit') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                <x-heroicon-o-pencil class="w-5 h-5 ml-2" />
                ویرایش اطلاعات
            </a>
            @elseif (Auth::user()->isRetailerPremium() || Auth::user()->isRetailerBasic())
            <a href="{{ route('retailer.profile.edit') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                <x-heroicon-o-pencil class="w-5 h-5 ml-2" />
                ویرایش اطلاعات
            </a>
            @endif


            <!-- اطلاعات تماس یا راهنمایی -->
            <div class="bg-blue-50 rounded-xl p-4 mb-8 text-sm text-blue-700 flex items-start gap-3 text-right">
                <x-heroicon-o-information-circle class="w-5 h-5 flex-shrink-0" />
                <p>این فرآیند معمولاً بین ۲۴ تا ۴۸ ساعت کاری زمان می‌برد.</p>
            </div>

            <!-- دکمه خروج -->
            <button wire:click="logout" class="inline-flex items-center gap-2 text-gray-500 hover:text-red-600 transition-colors duration-200 font-medium">
                <x-heroicon-o-arrow-left-on-rectangle class="w-5 h-5" />
                خروج از حساب کاربری
            </button>
        </div>

        <div class="mt-8 text-center">
            <a href="/" class="text-sm text-gray-500 hover:text-gray-700">بازگشت به صفحه اصلی سایت</a>
        </div>
    </div>
</div>
