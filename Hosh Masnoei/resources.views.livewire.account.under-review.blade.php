<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-sky-50 to-indigo-50 px-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-xl p-8 text-center border-t-4 border-indigo-500">
            <!-- آیکن -->
            <div class="mb-6">
                <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto ring-8 ring-indigo-50">
                    <x-heroicon-o-arrow-path class="w-10 h-10 text-indigo-600 animate-spin" style="animation-duration: 3s;" />
                </div>
            </div>

            <!-- محتوا -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">تغییرات شما در حال بازبینی است</h1>
            <p class="text-gray-600 leading-relaxed mb-8">
                اطلاعات جدیدی که ثبت کرده‌اید با موفقیت ذخیره شد و هم‌اکنون توسط کارشناسان ما در حال بررسی است. تا پایان بازبینی، امکان ویرایش مجدد اطلاعات وجود ندارد و پس از تایید نهایی، تغییرات شما اعمال خواهد شد.
            </p>

            <!-- راهنمایی -->
            <div class="bg-indigo-50 rounded-xl p-4 mb-8 text-sm text-indigo-700 flex items-start gap-3 text-right">
                <x-heroicon-o-information-circle class="w-5 h-5 flex-shrink-0" />
                <p>این فرآیند معمولاً بین ۲۴ تا ۴۸ ساعت کاری زمان می‌برد. نیازی به ثبت مجدد اطلاعات نیست.</p>
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
