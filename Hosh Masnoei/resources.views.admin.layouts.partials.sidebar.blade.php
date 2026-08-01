{{-- لوگو / عنوان پنل --}}
<div class="flex items-center justify-center h-16 bg-gray-950 border-b border-gray-800">
    <a href="{{ route('admin.dashboard') }}" class="text-xl font-bold tracking-wider text-white hover:text-blue-400 transition">
        ⚡ پنل مدیریت
    </a>
</div>

{{-- منوی ناوبری --}}
<nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

    {{-- ✅ داشبورد --}}
    <x-admin-sidebar-link
        :href="route('admin.dashboard')"
        :active="request()->routeIs('admin.dashboard')"
    >
        <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        داشبورد
    </x-admin-sidebar-link>

    {{-- جداکننده - مدیریت محتوا --}}
    <div class="pt-4 mt-4 border-t border-gray-700">
        <span class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">مدیریت محتوا</span>
    </div>

    {{-- 🛒 محصولات --}}
    <x-admin-sidebar-link
        :href="route('admin.products.index')"
        :active="request()->routeIs('admin.products.*')"
    >
        <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        محصولات
    </x-admin-sidebar-link>

    {{-- 📁 دسته‌بندی‌ها --}}
    <x-admin-sidebar-link
        :href="route('admin.categories.index')"
        :active="request()->routeIs('admin.categories.*')"
    >
        <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        دسته‌بندی‌ها
    </x-admin-sidebar-link>

    {{-- 🖼️ اسلایدرها (غیرفعال - مسیر تعریف نشده) --}}
    {{--
    <x-admin-sidebar-link
        :href="route('admin.sliders.index')"
        :active="request()->routeIs('admin.sliders.*')"
    >
        <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        اسلایدرها
    </x-admin-sidebar-link>
    --}}

    {{-- جداکننده - مالی و فروش --}}
    <div class="pt-4 mt-4 border-t border-gray-700">
        <span class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">مالی و فروش</span>
    </div>

    {{-- 💰 قیمت‌های خرده‌فروشی --}}
    <x-admin-sidebar-link
        :href="route('admin.retail-prices.index')"
        :active="request()->routeIs('admin.retail-prices.*')"
    >
        <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        قیمت‌های خرده‌فروشی
    </x-admin-sidebar-link>

    {{-- جداکننده - سئو --}}
    <div class="pt-4 mt-4 border-t border-gray-700">
        <span class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">سئو</span>
    </div>

    {{-- 🔍 صفحات سئو (غیرفعال - مسیر تعریف نشده) --}}
    {{--
    <x-admin-sidebar-link
        :href="route('admin.seo.index')"
        :active="request()->routeIs('admin.seo.*')"
    >
        <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        صفحات سئو
    </x-admin-sidebar-link>
    --}}

    {{-- جداکننده - سیستم (فقط سوپر ادمین) --}}
    @can('super_admin')
    <div class="pt-4 mt-4 border-t border-gray-700">
        <span class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">سیستم</span>
    </div>

    {{-- 👥 کاربران --}}
    <x-admin-sidebar-link
        :href="route('admin.users.index')"
        :active="request()->routeIs('admin.users.*')"
    >
        <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        کاربران
    </x-admin-sidebar-link>

    {{-- 🚚 تأمین‌کنندگان --}}
    <x-admin-sidebar-link
        :href="route('admin.suppliers.index')"
        :active="request()->routeIs('admin.suppliers.*')"
    >
        <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
        </svg>
        تأمین‌کنندگان
    </x-admin-sidebar-link>
    @endcan

</nav>

{{-- فوتر سایدبار --}}
<div class="p-3 border-t border-gray-700 text-xs text-gray-500 text-center">
    {{ config('app.name') }} v1.0
</div>
