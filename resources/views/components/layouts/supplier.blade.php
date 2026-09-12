<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'پنل تامین‌کننده' }}</title>

    <!-- Scripts / Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200" x-data="{ sidebarOpen: false }">

<div class="min-h-screen flex">
    <!-- پس‌زمینه تیره برای منوی موبایل -->
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-20 bg-gray-900/50 lg:hidden" @click="sidebarOpen = false" style="display: none;"></div>

    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'" class="fixed inset-y-0 right-0 z-30 w-64 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 shadow-sm transition-transform duration-300 ease-in-out lg:static lg:block flex flex-col">

        <!-- هدر سایدبار -->
        <div class="h-16 flex items-center justify-between lg:justify-center px-4 lg:px-0 border-b border-gray-200 dark:border-gray-700">
            <h1 class="text-xl font-black text-indigo-600 dark:text-indigo-400">پنل تامین‌کننده</h1>
            <button @click="sidebarOpen = false" class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none">
                <x-heroicon-o-x-mark class="w-6 h-6" />
            </button>
        </div>

        <!-- لینک‌های منو -->
        <nav class="flex-1 px-2 py-4 space-y-2">
            <!-- داشبورد -->
            <a href="{{ route('supplier.dashboard') }}"
               class="{{ request()->routeIs('supplier.dashboard') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <x-heroicon-o-home class="{{ request()->routeIs('supplier.dashboard') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                داشبورد
            </a>

            <!-- اطلاعات فروشگاه -->
            @if(auth()->user()?->supplier)
            <a href="{{ route('supplier.profile.edit') }}"
               class="{{ request()->routeIs('admin.suppliers.edit') || request()->routeIs('supplier.profile.edit') ? 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400 hover:bg-green-50 dark:hover:bg-green-800/50 hover:text-green-700 dark:hover:text-green-300' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <x-heroicon-o-building-storefront class="{{ request()->routeIs('admin.suppliers.edit') || request()->routeIs('supplier.profile.edit') ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-green-500 dark:group-hover:text-green-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                اطلاعات فروشگاه
            </a>
            @endif

            <!-- انتخاب محصول (قیمت گذاری محصول) -->
            <a href="{{ route('supplier.product-selection') }}"
               class="{{ request()->routeIs('supplier.product-selection') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <x-heroicon-o-tag class="{{ request()->routeIs('supplier.product-selection') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                قیمت گذاری محصول
            </a>

            <!-- قیمت‌گذاری سریع -->
            <a href="{{ route('supplier.fast-pricing.import') }}"
               class="{{ request()->routeIs('supplier.fast-pricing.*') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <x-heroicon-o-bolt class="{{ request()->routeIs('supplier.fast-pricing.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                قیمت‌گذاری سریع
            </a>

            <!-- محصولات پیشنهادی -->
            <a href="{{ route('supplier.products.index') }}"
               class="{{ request()->routeIs('supplier.products.*') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <x-heroicon-o-shopping-bag class="{{ request()->routeIs('supplier.products.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                محصولات پیشنهادی
            </a>

            <!-- کاتالوگ PDF -->
            <a href="{{ route('supplier.catalog.index') }}"
               class="{{ request()->routeIs('supplier.catalog.*') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <x-heroicon-o-book-open class="{{ request()->routeIs('supplier.catalog.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                کاتالوگ PDF
            </a>

            <!-- گزارشات -->
            <a href="{{ route('supplier.reports') }}"
               class="{{ request()->routeIs('supplier.reports') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <x-heroicon-o-chart-bar class="{{ request()->routeIs('supplier.reports') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                گزارشات
            </a>

            <!-- تنظیمات -->
            <div class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                <p class="px-3 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">تنظیمات</p>
                
                <a href="{{ route('supplier.settings.tier-discounts') }}"
                   class="{{ request()->routeIs('supplier.settings.tier-discounts') || request()->routeIs('supplier.settings.category-tier-discounts') ? 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <x-heroicon-o-banknotes class="{{ request()->routeIs('supplier.settings.tier-discounts') || request()->routeIs('supplier.settings.category-tier-discounts') ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                    درصد تیراژ نقدی
                </a>
                
                <a href="{{ route('supplier.settings.check-periods') }}"
                   class="{{ request()->routeIs('supplier.settings.check-periods') ? 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <x-heroicon-o-calendar-days class="{{ request()->routeIs('supplier.settings.check-periods') ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                    دوره‌های چکی
                </a>

                <a href="{{ route('supplier.settings.price-rounding') }}"
                   class="{{ request()->routeIs('supplier.settings.price-rounding') ? 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-gray-200' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <x-heroicon-o-calculator class="{{ request()->routeIs('supplier.settings.price-rounding') ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }} ml-3 flex-shrink-0 h-6 w-6 transition-colors" />
                    رُند کردن قیمت‌ها
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden">

        <!-- Topbar -->
        <header class="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between px-4 sm:px-6 z-10">

            <!-- سمت راست (دکمه موبایل و عنوان صفحه) -->
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = true" class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none">
                    <x-heroicon-o-bars-3-bottom-right class="w-6 h-6" />
                </button>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ $header ?? '' }}</h2>
            </div>

            <!-- سمت چپ (پروفایل و خروج) -->
            <div class="flex items-center gap-3 sm:gap-5">

                <!-- اطلاعات پروفایل -->
                <div class="flex items-center gap-3 border-l border-gray-200 dark:border-gray-700 pl-3 sm:pl-5">
                    <div class="hidden sm:flex flex-col text-left">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ auth()->user()->name ?? 'کاربر تامین‌کننده' }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">تامین‌کننده</span>
                    </div>
                    <!-- آواتار حرف اول اسم -->
                    <div class="w-9 h-9 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-bold border border-indigo-200 dark:border-indigo-800">
                        {{ mb_substr(auth()->user()->name ?? 'U', 0, 1) }}
                    </div>
                </div>

                <!-- فرم خروج -->
                <form method="POST" action="{{ route('logout') }}" class="m-0 flex items-center">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-sm text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium transition-colors p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20" title="خروج">
                        <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5" />
                        <span class="hidden sm:inline">خروج</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Page Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            @if (session()->has('info'))
            <div class="p-4 mb-4 text-sm text-blue-700 bg-blue-100 rounded-lg">
                {{ session('info') }}
            </div>
            @endif

            {{ $slot }}
        </div>

    </main>
</div>

@livewireScripts
</body>
</html>
