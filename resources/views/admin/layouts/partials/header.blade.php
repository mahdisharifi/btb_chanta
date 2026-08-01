<header class="bg-white border-b border-gray-200 shadow-sm z-30">
    <div class="flex items-center justify-between h-16 px-4 md:px-6">

        {{-- دکمه همبرگری (موبایل) --}}
        <button
            x-on:click="sidebarOpen = !sidebarOpen"
            class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-800 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        {{-- عنوان صفحه جاری (سمت راست) --}}
        <div class="flex-1 text-right lg:text-right">
            <span class="text-lg font-semibold text-gray-700 hidden sm:inline">
                @yield('page-header-title', 'داشبورد')
            </span>
        </div>

        {{-- بخش کاربر (سمت چپ) --}}
        <div class="flex items-center space-x-4 space-x-reverse">

            {{-- اعلان‌ها (placeholder) --}}
            <button class="relative p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-full focus:outline-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
            </button>

            {{-- منوی کاربر --}}
            <div x-data="{ open: false }" class="relative">
                <button
                    x-on:click="open = !open"
                    class="flex items-center space-x-2 space-x-reverse text-gray-700 hover:text-gray-900 focus:outline-none p-1.5 rounded-lg hover:bg-gray-100"
                >
                    <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-semibold">
                        {{ mb_substr(auth()->user()->name ?? 'A', 0, 1) }}
                    </div>
                    <span class="hidden md:inline text-sm font-medium">{{ auth()->user()->name ?? 'ادمین' }}</span>
                    <svg class="w-4 h-4 hidden md:inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- دراپ‌داون --}}
                <div
                    x-show="open"
                    x-on:click.away="open = false"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
                >
                    <div class="py-1">
                        <span class="block px-4 py-2 text-xs text-gray-500 border-b">
                            {{ auth()->user()->email ?? '' }}
                        </span>
                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">داشبورد</a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="block w-full text-right px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                خروج از حساب
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
