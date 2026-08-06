<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - پنل مدیریت</title>

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles

    <style>
        /* استفاده از فونت محلی که در فایل css شما تعریف شده است */
        body { font-family: 'Vazir', Tahoma, Arial, sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased" x-data="{ sidebarOpen: false, profileOpen: false }">

<!-- Mobile Sidebar Overlay -->
<div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 bg-gray-900/80 backdrop-blur-sm lg:hidden transition-opacity"
     x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="sidebarOpen = false"></div>

<!-- Sidebar -->
<aside class="fixed inset-y-0 right-0 z-50 w-64 bg-white border-l border-gray-200 shadow-sm flex flex-col transition-transform duration-300 lg:translate-x-0"
       :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full'">

    <!-- Sidebar Header (Logo) -->
    <div class="flex items-center justify-between h-16 px-6 bg-white border-b border-gray-100">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 font-bold text-xl text-indigo-600">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            <span>پنل مدیریت</span>
        </a>
        <!-- Close Sidebar Mobile -->
        <button @click="sidebarOpen = false" class="lg:hidden text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    <x-admin-sidebar-link href="{{ route('admin.flowcharts.index') }}">
        مدیریت فلوچارت‌ها
    </x-admin-sidebar-link>
    <!-- Sidebar Links -->
    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
        @php
        $navLinks = [
        ['route' => 'admin.dashboard', 'label' => 'داشبورد', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['route' => 'admin.users.index', 'label' => 'مدیریت کاربران', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['route' => 'admin.geography.index', 'label' => 'مدیریت شهرها', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['route' => 'admin.categories.index', 'label' => 'دسته‌بندی‌ها', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
        ['route' => 'admin.attributes.index', 'label' => 'ویژگی‌ها', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ['route' => 'admin.products.index', 'label' => 'محصولات', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        ['route' => 'admin.catalogs.manage', 'label' => 'مدیریت کاتالوگ‌ها', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['route' => 'admin.users.pending', 'label' => 'تایید کاربران جدید', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['route' => 'admin.suppliers.index', 'label' => 'تامین‌کنندگان', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['route' => 'admin.subscription-plans.index', 'label' => 'پنل های فروش', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['route' => 'admin.system.terminal', 'label' => 'دستورات سیستمی', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ];

        // لینک اطلاعات فروشگاه (برای ادمین که supplier هم هست)
        $supplierEditLink = null;
        if (auth()->user()?->supplier) {
            $supplierEditLink = [
                'route' => 'admin.suppliers.edit',
                'params' => auth()->user()->supplier->id,
                'label' => 'اطلاعات فروشگاه',
                'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            ];
        }
        @endphp

        @foreach ($navLinks as $link)
        <a href="{{ route($link['route']) }}"
           class="flex items-center px-3 py-2.5 rounded-lg transition-colors duration-200 group {{ request()->routeIs($link['route'].'*') ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
            <svg class="w-5 h-5 ml-3 {{ request()->routeIs($link['route'].'*') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"></path>
            </svg>
            {{ $link['label'] }}
        </a>
        @endforeach

        {{-- لینک اطلاعات فروشگاه برای ادمین --}}
        @if($supplierEditLink)
        <a href="{{ route($supplierEditLink['route'], $supplierEditLink['params']) }}"
           class="flex items-center px-3 py-2.5 rounded-lg transition-colors duration-200 group {{ request()->routeIs($supplierEditLink['route'].'*') ? 'bg-green-50 text-green-600 font-semibold' : 'text-gray-600 hover:bg-green-50 hover:text-green-700' }}">
            <svg class="w-5 h-5 ml-3 {{ request()->routeIs($supplierEditLink['route'].'*') ? 'text-green-600' : 'text-gray-400 group-hover:text-green-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $supplierEditLink['icon'] }}"></path>
            </svg>
            {{ $supplierEditLink['label'] }}
        </a>
        @endif

        {- ابزار حساس: ورود بدون پسورد — فقط برای مدیر ارشد --}}
        @can('super_admin')
        @if (\Illuminate\Support\Facades\Route::has('dev.force-login'))
        <a href="{{ route('dev.force-login') }}"
           class="flex items-center px-3 py-2.5 rounded-lg transition-colors duration-200 group {{ request()->routeIs('dev.force-login*') ? 'bg-amber-50 text-amber-700 font-semibold' : 'text-gray-600 hover:bg-amber-50 hover:text-amber-700' }}">
            <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5 ml-3 {{ request()->routeIs('dev.force-login*') ? 'text-amber-600' : 'text-gray-400 group-hover:text-amber-500' }}" />
            ورود بدون پسورد
        </a>
        @endif
        @endcan

    </nav>
</aside>

<!-- Main Wrapper -->
<div class="flex flex-col flex-1 lg:pr-64 min-h-screen">

    <!-- Top Navbar -->
    <header class="sticky top-0 z-30 flex items-center justify-between h-16 px-4 bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm sm:px-6 lg:px-8">
        <div class="flex items-center gap-4 lg:hidden">
            <button @click="sidebarOpen = true" class="p-2 text-gray-500 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
        </div>

        <div class="flex-1 flex justify-end items-center gap-4">
            <!-- Notifications -->
            <button class="p-2 text-gray-400 rounded-full hover:text-gray-500 hover:bg-gray-100 relative">
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            </button>

            <!-- Profile Dropdown -->
            <div class="relative">
                <button @click="profileOpen = !profileOpen" @click.away="profileOpen = false" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                    <!-- Local SVG Avatar -->
                    <div class="w-8 h-8 rounded-full border border-gray-200 bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <span class="hidden text-sm font-medium text-gray-700 md:block">{{ auth()->user()->name ?? 'مدیر سیستم' }}</span>
                    <svg class="w-4 h-4 text-gray-400 hidden md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>

                <!-- Dropdown Menu -->
                <div x-cloak x-show="profileOpen"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute left-0 mt-2 w-48 bg-white border border-gray-100 rounded-xl shadow-lg py-1 z-50">
                    <div class="px-4 py-2 border-b border-gray-50">
                        <p class="text-sm text-gray-500">وارد شده به عنوان</p>
                        <p class="text-sm font-bold text-gray-900 truncate">{{ auth()->user()->email ?? 'admin@example.com' }}</p>
                    </div>
                    <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600">تنظیمات حساب</a>
                    <form method="POST" action="{{ route('logout') }}" class="block w-full">
                        @csrf
                        <button type="submit" class="w-full text-right px-4 py-2 text-sm text-red-600 hover:bg-red-50">خروج از سیستم</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto">
        <!-- Page Header -->
        @if (isset($header))
        <div class="mb-6 pb-4 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-900">{{ $header }}</h1>
        </div>
        @endif

        <!-- Main Content Slot -->
        <div class="animate-fade-in-up">
            {{ $slot }}
        </div>
    </main>

    <!-- Footer (Optional) -->
    <footer class="mt-auto py-4 text-center text-sm text-gray-500 border-t border-gray-200 bg-white">
        &copy; {{ date('Y') }} پنل مدیریت. تمامی حقوق محفوظ است.
    </footer>
</div>
@stack('scripts')
@livewireScripts
</body>
</html>
