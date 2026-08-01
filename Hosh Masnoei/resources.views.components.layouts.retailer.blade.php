<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - پنل کاسبان</title>

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-vazir antialiased bg-gray-50 text-gray-800">

<div class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-white shadow-lg flex flex-col hidden md:flex z-10">
        <div class="h-16 flex items-center justify-center border-b border-gray-100 px-4">
            <span class="text-xl font-bold text-blue-600">سامانه عمده‌فروشی</span>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="{{ route('retailer.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                <x-heroicon-o-home class="w-5 h-5" />
                <span class="font-medium">داشبورد</span>
            </a>

            <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                <x-heroicon-o-shopping-bag class="w-5 h-5" />
                <span class="font-medium">جستجوی کالا</span>
            </a>

            <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                <x-heroicon-o-users class="w-5 h-5" />
                <span class="font-medium">تامین‌کنندگان</span>
            </a>

            <a href="{{ route('retailer.subscriptions.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                <x-heroicon-o-star class="w-5 h-5" />
                <span class="font-medium">خرید اشتراک (VIP)</span>
            </a>


        </nav>

        <div class="p-4 border-t border-gray-100">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <a href="{{ route('logout') }}"
                   onclick="event.preventDefault(); this.closest('form').submit();"
                   class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700">
                    <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5"/>
                    خروج
                </a>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col relative h-screen">
        <!-- Header -->
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-0">
            <div class="flex items-center gap-4">
                <button class="md:hidden text-gray-500 hover:text-gray-700">
                    <x-heroicon-o-bars-3 class="w-6 h-6" />
                </button>
                <h1 class="text-xl font-semibold text-gray-800">
                    @yield('header', 'پنل کاربری کاسب')
                </h1>
            </div>

            <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500 flex items-center gap-1">
                        <x-heroicon-o-calendar class="w-4 h-4" />
                        {{ jdate()->format('Y/m/d') }}
                    </span>
                <div class="flex items-center gap-2 border-r pr-4 border-gray-200">
                    <x-heroicon-o-user-circle class="w-8 h-8 text-gray-400" />
                    <span class="text-sm font-medium text-gray-700">{{ auth()->user()->name }}</span>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>
    </div>
</div>

@livewireScripts
</body>
</html>
