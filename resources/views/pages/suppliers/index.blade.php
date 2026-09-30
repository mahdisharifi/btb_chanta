{{-- ═══════════════════════════════════════════════════════════════
     صفحه لیست تأمین‌کنندگان — suppliers/index.blade.php
     ═══════════════════════════════════════════════════════════════ --}}

@extends('layouts.app')

@section('title', 'تأمین‌کنندگان')

@push('head')
{{-- Schema.org ItemList --}}
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "ItemList",
    "name": "لیست تأمین‌کنندگان",
    "description": "مجموعه‌ای از تأمین‌کنندگان معتبر و تأیید شده با محصولات متنوع",
    "numberOfItems": {{ $stats['total'] ?? 0 }},
    "itemListElement": [
        @foreach($suppliers as $index => $supplier)
        {
            "@@type": "ListItem",
            "position": {{ $index + 1 }},
            "item": {
                "@@type": "Organization",
                "name": "{{ $supplier->company_name }}",
                "url": "{{ route('suppliers.show', $supplier->slug) }}"
                @if($supplier->is_verified)
                ,"description": "تأمین‌کننده تأیید شده"
                @endif
            }
        }{{ !$loop->last ? ',' : '' }}
        @endforeach
    ]
}
</script>

{{-- Canonical URL --}}
<link rel="canonical" href="{{ $canonical ?? route('suppliers.index') }}" />

{{-- Open Graph --}}
<meta property="og:title" content="{{ $seoTitle ?? 'تأمین‌کنندگان' }}" />
<meta property="og:description" content="{{ $seoDescription ?? 'لیست تأمین‌کنندگان تأیید شده' }}" />
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ $canonical ?? route('suppliers.index') }}" />
@endpush

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        {{-- ═══════ راهنمای کاربر ═══════ --}}
        <div x-data="{ showGuide: false }" class="mb-6">
            <button @click="showGuide = !showGuide" 
                    class="flex items-center gap-2 text-blue-600 hover:text-blue-700 transition-colors">
                <x-heroicon-o-information-circle class="w-5 h-5" />
                <span x-text="showGuide ? 'بستن راهنما' : 'راهنمای استفاده از صفحه'"></span>
            </button>
            
            <div x-show="showGuide" 
                 x-collapse
                 class="mt-4 p-5 bg-blue-50 rounded-lg border border-blue-200">
                <h3 class="text-base font-semibold text-blue-900 mb-3 flex items-center gap-2">
                    <x-heroicon-o-information-circle class="w-5 h-5" />
                    راهنمای لیست تأمین‌کنندگان
                </h3>
                
                <div class="space-y-4 text-sm text-blue-900 leading-relaxed">
                    <div>
                        <p class="font-medium mb-1">این صفحه برای چیست؟</p>
                        <p>در این صفحه می‌توانید تمامی تأمین‌کنندگان فعال و تأیید شده را مشاهده کنید و بر اساس استان، شهر، یا نام جستجو نمایید.</p>
                    </div>
                    
                    <div>
                        <p class="font-medium mb-2">نحوه استفاده:</p>
                        <ul class="list-disc list-inside space-y-1 mr-4">
                            <li><strong>جستجو:</strong> نام شرکت یا برند را در کادر جستجو وارد کنید</li>
                            <li><strong>فیلتر استان:</strong> تأمین‌کنندگان یک استان خاص را مشاهده کنید</li>
                            <li><strong>مرتب‌سازی:</strong> نتایج را بر اساس جدیدترین، بیشترین محصول، یا الفبا مرتب کنید</li>
                            <li><strong>بج تأیید شده:</strong> تأمین‌کنندگان دارای بج سبز، توسط مدیریت سایت تأیید شده‌اند</li>
                        </ul>
                    </div>
                    
                    <div class="bg-blue-100 border border-blue-300 p-3 rounded flex items-start gap-2">
                        <x-heroicon-o-light-bulb class="w-5 h-5 text-blue-700 flex-shrink-0 mt-0.5" />
                        <div class="text-xs">
                            <p class="font-medium">نکته:</p>
                            <p>با کلیک روی هر کارت، به صفحه پروفایل تأمین‌کننده و لیست محصولات او منتقل می‌شوید.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════ HERO HEADER ═══════ --}}
        <div class="bg-gradient-to-l from-blue-600 to-indigo-700 rounded-3xl p-8 mb-8 text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 400 200" fill="none">
                    <circle cx="350" cy="50" r="120" fill="currentColor"/>
                    <circle cx="50" cy="180" r="80" fill="currentColor"/>
                </svg>
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl lg:text-4xl font-extrabold mb-3">🏭 تأمین‌کنندگان</h1>
                <p class="text-blue-100 text-lg max-w-2xl leading-relaxed">
                    مجموعه‌ای از تأمین‌کنندگان معتبر و تأیید شده با محصولات متنوع و قیمت‌های رقابتی
                </p>

                @isset($stats)
                    <div class="flex flex-wrap gap-6 mt-6">
                        <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                            <x-heroicon-o-building-office-2 class="w-5 h-5" />
                            <span class="font-bold text-lg">{{ number_format($stats['total'] ?? 0) }}</span>
                            <span class="text-sm text-blue-200">تأمین‌کننده</span>
                        </div>
                        <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                            <x-heroicon-o-check-badge class="w-5 h-5 text-green-300" />
                            <span class="font-bold text-lg">{{ number_format($stats['verified'] ?? 0) }}</span>
                            <span class="text-sm text-blue-200">تأیید شده</span>
                        </div>
                        <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                            <x-heroicon-o-cube class="w-5 h-5" />
                            <span class="font-bold text-lg">{{ number_format($stats['total_products'] ?? 0) }}</span>
                            <span class="text-sm text-blue-200">محصول</span>
                        </div>
                        <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                            <x-heroicon-o-map-pin class="w-5 h-5" />
                            <span class="font-bold text-lg">{{ number_format($stats['cities'] ?? 0) }}</span>
                            <span class="text-sm text-blue-200">شهر</span>
                        </div>
                    </div>
                @endisset
            </div>
        </div>

        {{-- ═══════ SEARCH & FILTER BAR ═══════ --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-8 shadow-sm">
            <form method="GET" action="{{ route('suppliers.index') }}" 
                  class="flex flex-wrap items-center gap-4"
                  x-data
                  x-init="
                      document.getElementById('provinceSelect').addEventListener('change', function() {
                          // پاک کردن شهر وقتی استان تغییر می‌کند
                          const citySelect = document.querySelector('select[name=\"city\"]');
                          if (citySelect) {
                              citySelect.value = '';
                          }
                      });
                  ">
                {{-- Search Input --}}
                <div class="flex-1 min-w-[250px] relative">
                    <x-heroicon-o-magnifying-glass class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input type="text" name="q" value="{{ $searchQuery ?? '' }}"
                           placeholder="جستجوی نام شرکت، برند..."
                           class="w-full pr-10 pl-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>

                {{-- Province Filter --}}
                @isset($provinces)
                    <select name="province"
                            id="provinceSelect"
                            class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 min-w-[160px]">
                        <option value="">همه استان‌ها</option>
                        @foreach($provinces as $province)
                            <option value="{{ $province }}" {{ request('province') == $province ? 'selected' : '' }}>
                                {{ $province }}
                            </option>
                        @endforeach
                    </select>
                @endisset

                {{-- City Filter --}}
                @if(request('province') && isset($cities) && $cities->isNotEmpty())
                    <select name="city"
                            class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 min-w-[160px]">
                        <option value="">همه شهرها</option>
                        @foreach($cities as $city)
                            <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>
                                {{ $city }}
                            </option>
                        @endforeach
                    </select>
                @endif

                {{-- Sort --}}
                <select name="sort"
                        class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 min-w-[160px]">
                    <option value="newest" {{ ($sortBy ?? 'newest') == 'newest' ? 'selected' : '' }}>جدیدترین</option>
                    <option value="name_asc" {{ ($sortBy ?? '') == 'name_asc' ? 'selected' : '' }}>نام (الف-ی)</option>
                    <option value="most_products" {{ ($sortBy ?? '') == 'most_products' ? 'selected' : '' }}>بیشترین محصول</option>
                    <option value="verified_first" {{ ($sortBy ?? '') == 'verified_first' ? 'selected' : '' }}>تأیید شده اول</option>
                </select>

                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2.5 rounded-xl text-sm transition-colors">
                    جستجو
                </button>

                @if(($searchQuery ?? '') || request('province') || ($sortBy ?? 'newest') !== 'newest')
                    <a href="{{ route('suppliers.index') }}"
                       class="text-gray-400 hover:text-red-500 text-sm transition-colors">
                        ✕ حذف فیلترها
                    </a>
                @endif
            </form>
        </div>

        {{-- ═══════ SUPPLIERS GRID ═══════ --}}
        @if($suppliers->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                @foreach($suppliers as $supplier)
                    <x-supplier-card :supplier="$supplier" />
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-8">
                {{ $suppliers->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
                <x-heroicon-o-building-office-2 class="w-20 h-20 mx-auto text-gray-300 mb-4" />
                <h3 class="text-lg font-bold text-gray-600 mb-2">تأمین‌کننده‌ای یافت نشد</h3>
                <p class="text-gray-400 text-sm mb-6">با فیلترهای انتخاب شده، تأمین‌کننده‌ای وجود ندارد</p>
                
                @if(($searchQuery ?? '') || request('province') || ($sortBy ?? 'newest') !== 'newest')
                    <a href="{{ route('suppliers.index') }}"
                       class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2.5 rounded-xl transition-colors">
                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                        حذف فیلترها و مشاهده همه
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
