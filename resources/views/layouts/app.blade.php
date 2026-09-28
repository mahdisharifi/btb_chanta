<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta Tags --}}
    <title>{{ $seoTitle ?? config('app.name') }}{{ !empty($pageTitle) ? ' — ' . $pageTitle : '' }}</title>

    <meta name="description" content="{{ $seoDescription ?? 'بزرگ‌ترین مارکت‌پلیس عمده‌فروشی ایران' }}">
    <meta name="keywords" content="{{ $seoKeywords ?? 'عمده فروشی, B2B, تأمین‌کننده, کاسب' }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $ogTitle ?? $seoTitle ?? config('app.name') }}">
    <meta property="og:description" content="{{ $ogDescription ?? $seoDescription ?? '' }}">
    <meta property="og:image" content="{{ $ogImage ?? asset('images/og-default.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="fa_IR">

    {{-- Canonical --}}
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">

    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon.jpg') }}">

    {{-- Font Preload: فقط فونت Regular که بیشترین استفاده را دارد - WOFF2 برای کاهش حجم --}}
    <link rel="preload" href="{{ asset('build/assets/Vazir-Regular-FD-M-dqDxKz.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- SEO Section (for schema, custom meta, etc.) --}}
    @yield('seo')

    {{-- Styles --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Livewire Styles --}}
    @livewireStyles

    {{-- Additional Head --}}
    @stack('head')

    <style>
        body {
            font-family: 'Vazir', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased min-h-screen flex flex-col">

{{-- Top Announcement Bar --}}
@hasSection('announcement')
<div class="bg-indigo-600 text-white text-center text-sm py-2 px-4">
    @yield('announcement')
</div>
@endif

{{-- Header / Navigation --}}
<header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
    {{-- Main Navigation --}}
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{ mobileMenu: false }">
        <div class="flex justify-between items-center h-16">
            {{-- Logo --}}
            <div class="flex-shrink-0">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <span class="text-xl font-bold text-gray-800 hidden sm:block">پلتفرم چن تا؟</span>
                    <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-lg">B2B</span>
                    </div>
                </a>
            </div>

            {{-- Desktop Menu --}}
            <div class="hidden md:flex items-center gap-1">
                <a href="{{ route('home') }}"
                   class="px-3 py-2 rounded-md text-sm text-gray-700 hover:text-indigo-600 hover:bg-gray-100 font-medium transition min-h-[40px] flex items-center {{ request()->routeIs('home') ? 'text-indigo-600 bg-indigo-50' : '' }}">
                    صفحه اصلی
                </a>
                <a href="{{ route('products.index') }}"
                   class="px-3 py-2 rounded-md text-sm text-gray-700 hover:text-indigo-600 hover:bg-gray-100 font-medium transition min-h-[40px] flex items-center {{ request()->routeIs('products.*') ? 'text-indigo-600 bg-indigo-50' : '' }}">
                    محصولات
                </a>
                <a href="{{ route('categories.index') }}"
                   class="px-3 py-2 rounded-md text-sm text-gray-700 hover:text-indigo-600 hover:bg-gray-100 font-medium transition min-h-[40px] flex items-center {{ request()->routeIs('categories.*') ? 'text-indigo-600 bg-indigo-50' : '' }}">
                    دسته‌بندی‌ها
                </a>
                <a href="{{ route('about') }}"
                   class="px-3 py-2 rounded-md text-sm text-gray-700 hover:text-indigo-600 hover:bg-gray-100 font-medium transition min-h-[40px] flex items-center {{ request()->routeIs('about') ? 'text-indigo-600 bg-indigo-50' : '' }}">
                    درباره ما
                </a>
                <a href="{{ route('contact') }}"
                   class="px-3 py-2 rounded-md text-sm text-gray-700 hover:text-indigo-600 hover:bg-gray-100 font-medium transition min-h-[40px] flex items-center {{ request()->routeIs('contact') ? 'text-indigo-600 bg-indigo-50' : '' }}">
                    تماس با ما
                </a>
            </div>

            {{-- Search --}}
            <div class="hidden lg:flex items-center flex-1 max-w-md mx-6">
                <form action="{{ route('products.index') }}" method="GET" class="w-full relative">
                    <input type="text"
                           name="q"
                           value="{{ request('q') }}"
                           placeholder="جستجوی محصول، برند، دسته‌بندی..."
                           class="w-full rounded-full border-gray-300 bg-gray-100 pr-4 pl-10 py-2.5 text-sm focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white transition min-h-[44px]"
                           aria-label="جستجوی محصول، برند، دسته‌بندی">
                    <button type="submit" 
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-indigo-600 p-2 min-w-[44px] min-h-[44px] flex items-center justify-center rounded-full hover:bg-gray-200 transition"
                            aria-label="جستجو">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </form>
            </div>

            {{-- Auth Buttons --}}
            <div class="hidden md:flex items-center gap-1">
                @auth
                <a href="{{ route('dashboard') }}" 
                   class="px-3 py-2 rounded-md text-sm text-gray-700 hover:text-indigo-600 hover:bg-gray-100 font-medium transition min-h-[40px] flex items-center">
                    پنل کاربری
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" 
                            class="px-3 py-2 rounded-md text-sm text-red-600 hover:text-red-700 hover:bg-red-50 font-medium transition min-h-[40px]">
                        خروج
                    </button>
                </form>
                @else
                <a href="{{ route('login') }}" 
                   class="px-3 py-2 rounded-md text-sm text-gray-700 hover:text-indigo-600 hover:bg-gray-100 font-medium transition min-h-[40px] flex items-center">
                    ورود
                </a>
                <a href="{{ route('register') }}" 
                   class="px-4 py-2 rounded-md text-sm bg-indigo-600 text-white hover:bg-indigo-700 font-medium transition min-h-[40px] flex items-center">
                    ثبت‌نام
                </a>
                @endauth
            </div>

            {{-- Mobile menu button --}}
            <button @click="mobileMenu = !mobileMenu" 
                    class="md:hidden p-3 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition min-w-[48px] min-h-[48px] flex items-center justify-center"
                    aria-label="منوی موبایل"
                    :aria-expanded="mobileMenu"
                    aria-controls="mobile-menu">
                <svg x-show="!mobileMenu" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-show="mobileMenu" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Mobile Menu --}}
        <div x-show="mobileMenu"
             id="mobile-menu"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="md:hidden pb-4 border-t border-gray-200 mt-2 pt-3"
             style="display: none;">
            <div class="flex flex-col gap-1">
                <a href="{{ route('home') }}" 
                   class="px-4 py-3 rounded-md text-gray-700 hover:bg-gray-100 hover:text-indigo-600 transition min-h-[48px] flex items-center">
                    صفحه اصلی
                </a>
                <a href="{{ route('products.index') }}" 
                   class="px-4 py-3 rounded-md text-gray-700 hover:bg-gray-100 hover:text-indigo-600 transition min-h-[48px] flex items-center">
                    محصولات
                </a>
                <a href="{{ route('categories.index') }}" 
                   class="px-4 py-3 rounded-md text-gray-700 hover:bg-gray-100 hover:text-indigo-600 transition min-h-[48px] flex items-center">
                    دسته‌بندی‌ها
                </a>
                <a href="{{ route('about') }}" 
                   class="px-4 py-3 rounded-md text-gray-700 hover:bg-gray-100 hover:text-indigo-600 transition min-h-[48px] flex items-center">
                    درباره ما
                </a>
                <a href="{{ route('contact') }}" 
                   class="px-4 py-3 rounded-md text-gray-700 hover:bg-gray-100 hover:text-indigo-600 transition min-h-[48px] flex items-center">
                    تماس با ما
                </a>
                
                {{-- Auth Buttons in Mobile Menu --}}
                <div class="border-t border-gray-200 mt-2 pt-2">
                    @auth
                    <a href="{{ route('dashboard') }}" 
                       class="px-4 py-3 rounded-md text-gray-700 hover:bg-gray-100 hover:text-indigo-600 transition min-h-[48px] flex items-center">
                        پنل کاربری
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" 
                                class="w-full px-4 py-3 rounded-md text-red-600 hover:bg-red-50 transition min-h-[48px] flex items-center">
                            خروج
                        </button>
                    </form>
                    @else
                    <a href="{{ route('login') }}" 
                       class="px-4 py-3 rounded-md text-gray-700 hover:bg-gray-100 hover:text-indigo-600 transition min-h-[48px] flex items-center">
                        ورود
                    </a>
                    <a href="{{ route('register') }}" 
                       class="px-4 py-3 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition min-h-[48px] flex items-center mt-1">
                        ثبت‌نام
                    </a>
                    @endauth
                </div>
            </div>
            
            {{-- Mobile Search --}}
            <form action="{{ route('search') }}" method="GET" class="mt-3 px-3">
                <input type="text" 
                       name="q" 
                       placeholder="جستجو..." 
                       class="w-full rounded-lg border-gray-300 text-sm min-h-[48px] px-4"
                       aria-label="جستجوی محصول">
            </form>
        </div>
    </nav>
</header>

{{-- Breadcrumb --}}
@hasSection('breadcrumb')
<div class="bg-gray-100 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <nav class="flex text-sm text-gray-500" aria-label="Breadcrumb">
            @yield('breadcrumb')
        </nav>
    </div>
</div>
@endif

{{-- Flash Messages --}}
@if(session('success'))
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between">
        <span>✅ {{ session('success') }}</span>
        <button @click="show = false" 
                class="text-green-500 hover:text-green-700" 
                aria-label="بستن پیام موفقیت">&times;</button>
    </div>
</div>
@endif

@if(session('error'))
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between">
        <span>❌ {{ session('error') }}</span>
        <button @click="show = false" 
                class="text-red-500 hover:text-red-700"
                aria-label="بستن پیام خطا">&times;</button>
    </div>
</div>
@endif

{{-- Main Content --}}
<main class="flex-grow">
    {{ $slot ?? '' }}
    @yield('content')
</main>

{{-- Footer --}}
<footer class="bg-gray-800 text-gray-100 mt-16">

    {{-- Main Footer --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {{-- About --}}
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-lg">B2B</span>
                    </div>
                    <span class="text-xl font-bold text-white">مارکت‌پلیس</span>
                </div>
                <p class="text-sm text-gray-400 leading-7">
                    بزرگ‌ترین پلتفرم تجارت عمده ایران.
                    ارتباط مستقیم تأمین‌کنندگان با کاسبان و فروشندگان سراسر کشور.
                </p>
            </div>

            {{-- Quick Links --}}
            <div>
                <h4 class="text-white font-semibold mb-4">دسترسی سریع</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('home') }}" class="hover:text-white transition">صفحه اصلی</a></li>
                    <li><a href="{{ route('products.index') }}" class="hover:text-white transition">محصولات</a></li>
                    <li><a href="{{ route('categories.index') }}" class="hover:text-white transition">دسته‌بندی‌ها</a></li>
                    <li><a href="{{ route('about') }}" class="hover:text-white transition">درباره ما</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white transition">تماس با ما</a></li>
                </ul>
            </div>

            {{-- For Businesses --}}
            <div>
                <h4 class="text-white font-semibold mb-4">ویژه کسب‌وکارها</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('register') }}" class="hover:text-white transition">ثبت‌نام تأمین‌کننده</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white transition">ثبت‌نام کاسب</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-white transition">شرایط استفاده</a></li>
                    <li><a href="{{ route('privacy') }}" class="hover:text-white transition">حریم خصوصی</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div>
                <h4 class="text-white font-semibold mb-4">تماس با ما</h4>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-center gap-2">
<li class="flex items-center gap-3"><span>📱</span> 09355921203</li>
                        
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span>info@chanta.ir</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>📍</span> شهرری،میدان شهید غیبی،خیابان 8 متری انقلاب،کوچه شهید تمجیدی،پلاک 12                    </li>





                </ul>

                {{-- Social Media --}}
                <div class="flex items-center gap-3 mt-4">
                    <a href="#" class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center hover:bg-indigo-600 transition">
                        <span class="text-xs">📱</span>
                    </a>
                    <a href="#" class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center hover:bg-indigo-600 transition">
                        <span class="text-xs">💬</span>
                    </a>
                    <a href="#" class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center hover:bg-indigo-600 transition">
                        <span class="text-xs">🐦</span>
                    </a>
                </div>

            </div>

        </div>
    </div>

    {{-- Copyright Bar --}}
    <div class="border-t border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-300">
            <span>© {{ date('Y') }} مارکت‌پلیس B2B — تمامی حقوق محفوظ است.</span>
            <span class="mt-2 sm:mt-0">توسعه با ❤️ در ایران</span>
        </div>
    </div>

</footer>

{{-- Livewire Scripts --}}
@livewireScripts

{{-- Additional Scripts --}}
@stack('scripts')

{{-- Scroll to Top Button --}}
<button x-data="{ show: false }"
        x-on:scroll.window="show = window.scrollY > 500"
        x-show="show"
        x-transition
        @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="fixed bottom-6 left-6 z-50 w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg flex items-center justify-center hover:bg-indigo-700 transition"
        style="display: none;"
        aria-label="بازگشت به بالای صفحه">
    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
</button>

</body>
</html>

