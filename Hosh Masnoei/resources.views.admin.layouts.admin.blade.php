<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت') | {{ config('app.name') }}</title>

    {{-- Tailwind + Vite --}}
    @vite(['resources/css/app.css'])

    {{-- Font: Vazirmatn (اختیاری - اگر در پروژه داری) --}}

    {{-- استایل‌های اضافی --}}
    @stack('styles')

    {{-- Alpine.js (برای تعامل‌های کلاینت) --}}
    @livewireStyles
</head>
<body class="bg-gray-100 font-sans antialiased">

<div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">

    {{-- ============================================ --}}
    {{-- سایدبار سمت راست (موبایل: overlay)         --}}
    {{-- ============================================ --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 right-0 z-50 w-64 bg-gray-900 text-white transform transition-transform duration-300 ease-in-out lg:relative lg:flex lg:flex-col lg:inset-y-auto shadow-xl"
    >
        @include('admin.layouts.partials.sidebar')
    </aside>

    {{-- Overlay برای موبایل --}}
    <div
        x-show="sidebarOpen"
        x-on:click="sidebarOpen = false"
        x-transition:enter="transition-opacity duration-300"
        x-transition:leave="transition-opacity duration-300"
        class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
    ></div>

    {{-- ============================================ --}}
    {{-- محتوای اصلی                                   --}}
    {{-- ============================================ --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- هدر بالا --}}
        @include('admin.layouts.partials.header')

        {{-- محتوای صفحه --}}
        <main class="flex-1 overflow-y-auto p-4 md:p-6 bg-gray-100">
            {{-- Breadcrumb جایگاه --}}
            @hasSection('breadcrumb')
            <nav class="mb-4 text-sm text-gray-600">
                @yield('breadcrumb')
            </nav>
            @endif

            {{-- عنوان صفحه --}}
            @hasSection('page-title')
            <h1 class="text-2xl font-bold text-gray-800 mb-6">@yield('page-title')</h1>
            @endif

            {{-- Notification ناحیه --}}
            @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="mb-4 flex items-center justify-between bg-green-500 text-white px-4 py-3 rounded-lg shadow">
                <span>{{ session('success') }}</span>
                <button x-on:click="show = false" class="text-white hover:text-gray-200">&times;</button>
            </div>
            @endif

            @if (session('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="mb-4 flex items-center justify-between bg-red-500 text-white px-4 py-3 rounded-lg shadow">
                <span>{{ session('error') }}</span>
                <button x-on:click="show = false" class="text-white hover:text-gray-200">&times;</button>
            </div>
            @endif

            {{-- محتوای اصلی صفحه --}}
            @if (isset($slot) && trim($slot) !== '')
            {{ $slot }}
            @else
            @yield('content')
            @endif
        </main>

    </div>
</div>

{{-- اسکریپت‌های اضافی --}}
@livewireScripts
@stack('scripts')
</body>
</html>
