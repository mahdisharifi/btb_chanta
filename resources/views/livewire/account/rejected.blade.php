<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-red-50 to-rose-50 px-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-xl p-8 text-center border-t-4 border-red-500">
            <!-- آیکن -->
            <div class="mb-6">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto ring-8 ring-red-50">
                    <x-heroicon-o-x-circle class="w-10 h-10 text-red-600" />
                </div>
            </div>

            <!-- محتوا -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">حساب کاربری شما تایید نشد</h1>
            <p class="text-gray-600 leading-relaxed mb-4">
                متأسفانه ثبت‌نام شما در حال حاضر مورد تأیید قرار نگرفته است.
            </p>

            @if($rejectionReason)
            <div class="bg-red-50 rounded-xl p-5 mb-8 text-right border border-red-100">
                <span class="text-xs font-bold text-red-700 mb-1 block uppercase tracking-wider">علت عدم تایید:</span>
                <p class="text-red-800 text-sm leading-relaxed">{{ $rejectionReason }}</p>
            </div>
            @endif

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


            <p class="text-sm text-gray-500 mb-8 leading-relaxed">
                برای رفع مشکل یا اطلاعات بیشتر می‌توانید با پشتیبانی تماس بگیرید.
            </p>

            <!-- دکمه خروج -->
            <button wire:click="logout" class="inline-flex items-center gap-2 text-gray-500 hover:text-red-600 transition-colors duration-200 font-medium">
                <x-heroicon-o-arrow-left-on-rectangle class="w-5 h-5" />
                خروج از حساب کاربری
            </button>
        </div>
    </div>
</div>
