<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>پنل مدیریت | @yield('title', 'داشبورد')</title>

    <!-- فراخوانی Tailwind CSS و فایل‌های جاوااسکریپت -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- در لایو‌وایر ۳ نیازی به فراخوانی استایل‌ها نیست، اما برای اطمینان افزوده می‌شود -->
    @livewireStyles
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-900">

<div class="flex h-screen overflow-hidden">

    <!-- Sidebar (منوی کناری) -->
    <aside class="w-64 bg-gray-800 text-white flex flex-col transition-all duration-300 z-20">
        <div class="h-16 flex items-center justify-center border-b border-gray-700">
            <span class="text-xl font-bold">پنل مدیریت</span>
        </div>

        <nav class="flex-1 overflow-y-auto py-4">
            <ul class="space-y-1 px-2">
                <li>
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-gray-700 text-white' : '' }}">
                        داشبورد
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.users.index') }}" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-gray-700 text-white' : '' }}">
                        کاربران
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.categories.index') }}" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors {{ request()->routeIs('admin.categories.*') ? 'bg-gray-700 text-white' : '' }}">
                        دسته‌بندی‌ها
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.products.index') }}" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors {{ request()->routeIs('admin.products.*') ? 'bg-gray-700 text-white' : '' }}">
                        محصولات
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.suppliers.index') }}" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors {{ request()->routeIs('admin.suppliers.*') ? 'bg-gray-700 text-white' : '' }}">
                        تامین‌کنندگان
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.seo.index') }}" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition-colors {{ request()->routeIs('admin.seo.*') ? 'bg-gray-700 text-white' : '' }}">
                        تنظیمات سئو
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content (بخش اصلی محتوا) -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden">

        <!-- Header (هدر بالای صفحه) -->
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10">
            <div class="flex items-center">
                <!-- دکمه منوی موبایل (در صورت نیاز به توسعه برای موبایل) -->
                <button class="text-gray-500 hover:text-gray-700 focus:outline-none md:hidden">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h1 class="text-xl font-semibold text-gray-800 mr-4">@yield('title', 'داشبورد')</h1>
            </div>

            <div class="flex items-center">
                <span class="text-sm text-gray-600 ml-4">{{ auth()->user()->name ?? 'مدیر سیستم' }}</span>
                <!-- دکمه خروج -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 hover:text-red-800 transition-colors">خروج</button>
                </form>
            </div>
        </header>

        <!-- Page Content (محتوای متغیر صفحات) -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <!-- برای پشتیبانی از بلیدهای معمولی -->
            @yield('content')

            <!-- برای پشتیبانی از کامپوننت‌های تمام صفحه لایو‌وایر -->
            @if(isset($slot))
            {{ $slot }}
            @endif
        </main>
    </div>
</div>

@livewireScripts
</body>
</html>
