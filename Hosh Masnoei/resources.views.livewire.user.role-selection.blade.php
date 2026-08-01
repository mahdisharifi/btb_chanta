<div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 flex items-center justify-center px-4 py-12">
    <div class="max-w-5xl w-full">
        {{-- هدر --}}
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-3">
                نقش خود را انتخاب کنید
            </h1>
            <p class="text-lg text-gray-600">
                برای ادامه، لطفاً نوع کسب‌وکار خود را مشخص کنید
            </p>
        </div>

        {{-- کارت‌های انتخاب --}}
        <div class="grid md:grid-cols-2 gap-8">

            {{-- کارت تامین‌کننده --}}
            <button
                wire:click="selectSupplier"
                class="group relative bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden border-2 border-transparent hover:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-200"
            >
                <div class="p-8 text-center">
                    {{-- آیکن --}}
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-indigo-100 text-indigo-600 mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                        <x-heroicon-o-building-storefront class="w-10 h-10" />
                    </div>

                    {{-- عنوان --}}
                    <h2 class="text-2xl font-bold text-gray-900 mb-3">
                        تامین‌کننده
                    </h2>

                    {{-- توضیحات --}}
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        من تولیدکننده یا توزیع‌کننده کالا هستم و می‌خواهم محصولاتم را به خرده‌فروشان عرضه کنم
                    </p>

                    {{-- ویژگی‌ها --}}
                    <ul class="text-right space-y-3 mb-6">
                        <li class="flex items-start text-sm text-gray-700">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 ml-2 flex-shrink-0 mt-0.5" />
                            <span>مدیریت محصولات و قیمت‌گذاری</span>
                        </li>
                        <li class="flex items-start text-sm text-gray-700">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 ml-2 flex-shrink-0 mt-0.5" />
                            <span>ایجاد کاتالوگ دیجیتال</span>
                        </li>
                        <li class="flex items-start text-sm text-gray-700">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 ml-2 flex-shrink-0 mt-0.5" />
                            <span>دسترسی به شبکه خرده‌فروشان</span>
                        </li>
                        <li class="flex items-start text-sm text-gray-700">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 ml-2 flex-shrink-0 mt-0.5" />
                            <span>گزارش‌گیری و تحلیل فروش</span>
                        </li>
                    </ul>

                    {{-- دکمه --}}
                    <div class="inline-flex items-center text-indigo-600 font-semibold group-hover:text-indigo-700">
                        <span class="ml-2">ادامه به عنوان تامین‌کننده</span>
                        <x-heroicon-m-arrow-left class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                    </div>
                </div>

                {{-- افکت نور --}}
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-purple-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            </button>

            {{-- کارت خرده‌فروش --}}
            <button
                wire:click="selectRetailer"
                class="group relative bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden border-2 border-transparent hover:border-purple-500 focus:outline-none focus:ring-4 focus:ring-purple-200"
            >
                <div class="p-8 text-center">
                    {{-- آیکن --}}
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-purple-100 text-purple-600 mb-6 group-hover:bg-purple-600 group-hover:text-white transition-colors duration-300">
                        <x-heroicon-o-shopping-bag class="w-10 h-10" />
                    </div>

                    {{-- عنوان --}}
                    <h2 class="text-2xl font-bold text-gray-900 mb-3">
                        خرده‌فروش
                    </h2>

                    {{-- توضیحات --}}
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        من صاحب فروشگاه یا خرده‌فروش هستم و می‌خواهم از تامین‌کنندگان کالا تهیه کنم
                    </p>

                    {{-- ویژگی‌ها --}}
                    <ul class="text-right space-y-3 mb-6">
                        <li class="flex items-start text-sm text-gray-700">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 ml-2 flex-shrink-0 mt-0.5" />
                            <span>دسترسی به هزاران محصول</span>
                        </li>
                        <li class="flex items-start text-sm text-gray-700">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 ml-2 flex-shrink-0 mt-0.5" />
                            <span>مقایسه قیمت تامین‌کنندگان</span>
                        </li>
                        <li class="flex items-start text-sm text-gray-700">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 ml-2 flex-shrink-0 mt-0.5" />
                            <span>مدیریت لیست قیمت فروشگاه</span>
                        </li>
                        <li class="flex items-start text-sm text-gray-700">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 ml-2 flex-shrink-0 mt-0.5" />
                            <span>ارتباط مستقیم با تامین‌کنندگان</span>
                        </li>
                    </ul>

                    {{-- دکمه --}}
                    <div class="inline-flex items-center text-purple-600 font-semibold group-hover:text-purple-700">
                        <span class="ml-2">ادامه به عنوان خرده‌فروش</span>
                        <x-heroicon-m-arrow-left class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                    </div>
                </div>

                {{-- افکت نور --}}
                <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-pink-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            </button>

        </div>

        {{-- راهنما --}}
        <div class="mt-12 text-center">
            <p class="text-sm text-gray-500">
                <x-heroicon-m-information-circle class="w-5 h-5 inline-block ml-1" />
                پس از انتخاب، فرم تکمیل اطلاعات کسب‌وکار نمایش داده می‌شود
            </p>
        </div>

        {{-- دکمه بازگشت (اختیاری) --}}
        <div class="mt-8 text-center">
            <a
                href=""
                class="inline-flex items-center text-gray-600 hover:text-gray-900 transition-colors"
            >
                <x-heroicon-m-arrow-right class="w-4 h-4 ml-1" />
                <span>بازگشت به صفحه اصلی</span>
            </a>
        </div>
    </div>
</div>
