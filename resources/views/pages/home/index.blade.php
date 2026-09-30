@extends('layouts.app')

@section('title', $seo['title'] ?? config('app.name'))
@section('description', $seo['description'] ?? '')

@php
    $canSeePrices = in_array($userRole, ['super_admin', 'supplier', 'retailer_premium', 'retailer_basic']);
@endphp
@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- ═══════════════════════════════════════════════════════════
         اسلایدر اصلی سایت (Main Site Slider)
         ═══════════════════════════════════════════════════════════ --}}
    <x-hero-slider :sliders="$sliders" />

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- ═══════════════ MAIN CONTENT WRAPPER ═════════════════ --}}
    {{-- ══════════════════════════════════════════════════════════ --}}

    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-2 space-y-8">



        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- ═══════════ SECTION 8: POPULAR PRODUCTS ═════════ --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        @if($popularProducts->isNotEmpty())
        <x-product-slider
            :products="$popularProducts"
            title="پربازدیدترین‌ها"
            subtitle="محبوب‌ترین محصولات"
            :moreLink="route('products.index', ['sort' => 'popular'])"
            badge="popular"
            :userRole="$userRole ?? 'guest'"
        >
            <x-slot:icon>
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-50 text-orange-600">
                    <x-heroicon-o-fire class="h-7 w-7" />
                </div>
            </x-slot:icon>

        </x-product-slider>
        @endif




        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- ═══════════ SECTION 5: FEATURED PRODUCTS ════════ --}}
        {{-- ═══════════════════════════════════════════════════ --}}

        @if($featuredProducts->isNotEmpty())
        <section>
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-extrabold text-gray-800">محصولات ویژه</h2>
                        <p class="text-sm text-gray-500 mt-0.5">منتخب محصولات پیشنهادی</p>
                    </div>
                </div>
                <a href="{{ route('products.index', ['featured' => 1]) }}"
                   class="text-sm text-amber-600 hover:text-amber-700 font-medium flex items-center gap-1 transition"
                   aria-label="مشاهده همه محصولات ویژه">
                    مشاهده همه محصولات ویژه
                    <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            </div>

            <div class="flex gap-3 overflow-x-auto pb-3 snap-x snap-mandatory scrollbar-hide
                        lg:grid lg:grid-cols-4 lg:overflow-visible lg:pb-0">
                @foreach($featuredProducts as $product)
                    <div class="w-[calc(40%-0.3rem)] lg:min-w-0 snap-start">
                        @include('components.product-card-inline', ['product' => $product, 'badge' => 'featured'])
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- ═══════════ SECTION 6: LATEST PRODUCTS ══════════ --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        @if($latestProducts->isNotEmpty())
        <x-product-slider
            :products="$latestProducts"
            title="جدیدترین محصولات"
            subtitle="آخرین محصولات صنعتی و تجاری عرضه‌شده توسط تامین‌کنندگان"
            :moreLink="route('products.index', ['sort' => 'latest'])"
            badge="new"
            :userRole="$userRole ?? 'guest'"
        >
            <x-slot:icon>
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <x-heroicon-o-sparkles class="h-7 w-7" />
                </div>
            </x-slot:icon>
        </x-product-slider>
        @endif



        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- ═══════════ SECTION 7: PROMOTIONAL BANNERS ══════ --}}
        {{-- ═══════════════════════════════════════════════════ --}}

        <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- بنر خرده‌فروش --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 p-6 sm:p-8 text-white min-h-[180px] flex items-center group">
                <div class="relative z-10">
                    <h3 class="text-lg sm:text-xl font-extrabold mb-2">خرده‌فروش هستید؟</h3>
                    <p class="text-sm text-white/80 mb-4 max-w-sm">با ثبت‌نام رایگان به هزاران محصول عمده با قیمت‌های ویژه دسترسی پیدا کنید</p>
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center gap-2 bg-white text-blue-700 font-bold px-5 py-2.5 rounded-xl text-sm hover:bg-blue-50 transition shadow-lg">
                        ثبت‌نام رایگان
                        <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                </div>
                <div class="absolute -left-8 -bottom-8 w-40 h-40 bg-white/10 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
                <div class="absolute -left-4 -top-4 w-24 h-24 bg-white/5 rounded-full group-hover:scale-125 transition-transform duration-500"></div>
            </div>

            {{-- بنر تأمین‌کننده --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 p-6 sm:p-8 text-white min-h-[180px] flex items-center group">
                <div class="relative z-10">
                    <h3 class="text-lg sm:text-xl font-extrabold mb-2">تأمین‌کننده هستید؟</h3>
                    <p class="text-sm text-white/80 mb-4 max-w-sm">محصولات خود را در بزرگ‌ترین مارکت‌پلیس B2B عرضه کنید</p>
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center gap-2 bg-white text-emerald-700 font-bold px-5 py-2.5 rounded-xl text-sm hover:bg-emerald-50 transition shadow-lg">
                        شروع فروش
                        <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                </div>
                <div class="absolute -left-8 -bottom-8 w-40 h-40 bg-white/10 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
                <div class="absolute -left-4 -top-4 w-24 h-24 bg-white/5 rounded-full group-hover:scale-125 transition-transform duration-500"></div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- ═══════════ SECTION 10: RECENTLY VIEWED ═════════ --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        @if($recentlyViewed->isNotEmpty())


        <x-product-slider
            :products="$recentlyViewed"
            title="بازدیدهای اخیر شما"
            subtitle="محصولاتی که اخیراً مشاهده کرده‌اید"
            :moreLink="null"
            badge="null"
            :userRole="$userRole ?? 'guest'"
        >
            <x-slot:icon>
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                    <x-heroicon-o-clock class="h-7 w-7" />
                </div>
            </x-slot:icon>
        </x-product-slider>
        @endif
        @if(!in_array($userRole, ['guest', 'end_user' ]))

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- ═══════════ SECTION 9: TOP SUPPLIERS ════════════ --}}
        {{-- ═══════════════════════════════════════════════════ --}}

        @if($topSuppliers->isNotEmpty())
            <section>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-gray-800">تأمین‌کنندگان برتر</h2>
                            <p class="text-sm text-gray-500 mt-0.5">معتبرترین تأمین‌کنندگان فعال</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    @foreach($topSuppliers as $supplier)
                        <a href="{{ route('products.index', ['supplier' => $supplier->id ?: $supplier->id]) }}"
                           class="group bg-white rounded-2xl border border-gray-100 p-5 text-center hover:shadow-lg hover:border-purple-200 transition-all duration-300">
                            <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-gray-50 border-2 border-gray-100 flex items-center justify-center overflow-hidden group-hover:border-purple-200 transition">
                                @if($supplier->logo_path)
                                    <img src="{{ asset('storage/' . $supplier->logo_path) }}"
                                         alt="{{ $supplier->brand_name ?: $supplier->company_name }}"
                                         class="w-12 h-12 object-contain" loading="lazy">
                                @else
                                    <span class="text-xl font-extrabold text-gray-300">
                                    {{ mb_substr($supplier->brand_name ?: $supplier->company_name, 0, 1) }}
                                </span>
                                @endif
                            </div>
                            <h3 class="text-sm font-bold text-gray-700 group-hover:text-purple-700 transition mb-1 line-clamp-1">
                                {{ $supplier->brand_name ?: $supplier->company_name }}
                            </h3>
                            @if($supplier->city || $supplier->province)
                                <p class="text-[11px] text-gray-400 mb-2">
                                    {{ $supplier->city }}{{ $supplier->city && $supplier->province ? '، ' : '' }}{{ $supplier->province }}
                                </p>
                            @endif
                            <div class="flex items-center justify-center gap-2">
                                @if($supplier->is_verified)
                                    <span class="inline-flex items-center gap-0.5 text-[10px] text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    تأییدشده
                                </span>
                                @endif
                                <span class="text-[10px] text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">
                                {{ number_format($supplier->active_products_count) }} محصول
                            </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
        @endif


        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- ═══════════ SECTION 11: WHY CHOOSE US ═══════════ --}}
        {{-- ═══════════════════════════════════════════════════ --}}

        <section class="bg-white rounded-2xl border border-gray-100 p-6 sm:p-8">
            <div class="text-center mb-6">
                <h2 class="text-xl sm:text-2xl font-extrabold text-gray-800">چرا {{ config('app.name') }}؟</h2>
                <p class="text-sm text-gray-500 mt-1.5 max-w-xl mx-auto">بستری امن و حرفه‌ای برای تجارت عمده</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center group">
                    <div class="w-12 h-12 mx-auto mb-3 bg-blue-50 rounded-xl flex items-center justify-center group-hover:bg-blue-100 transition">
                        <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-800 mb-1">تأمین‌کنندگان تأییدشده</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">تمامی تأمین‌کنندگان پس از احراز هویت تأیید می‌شوند</p>
                </div>

                <div class="text-center group">
                    <div class="w-12 h-12 mx-auto mb-3 bg-emerald-50 rounded-xl flex items-center justify-center group-hover:bg-emerald-100 transition">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-800 mb-1">قیمت‌های رقابتی</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">مقایسه قیمت از چندین تأمین‌کننده</p>
                </div>

                <div class="text-center group">
                    <div class="w-12 h-12 mx-auto mb-3 bg-purple-50 rounded-xl flex items-center justify-center group-hover:bg-purple-100 transition">
                        <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-800 mb-1">سریع و آسان</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">فرایند سفارش ساده و پیگیری آسان</p>
                </div>

                <div class="text-center group">
                    <div class="w-12 h-12 mx-auto mb-3 bg-amber-50 rounded-xl flex items-center justify-center group-hover:bg-amber-100 transition">
                        <svg class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-800 mb-1">پشتیبانی ۲۴/۷</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">تیم پشتیبانی در تمام ساعات آماده پاسخگویی</p>
                </div>
            </div>
        </section>

    </div>
</div>
@endsection

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- ═══════════════ SEO & META (HEAD) ═════════════════════ --}}
{{-- ══════════════════════════════════════════════════════════ --}}

@push('head')
    @if(!empty($seo['og']))
        <meta property="og:title" content="{{ $seo['og']['title'] ?? '' }}">
        <meta property="og:description" content="{{ $seo['og']['description'] ?? '' }}">
        <meta property="og:type" content="{{ $seo['og']['type'] ?? 'website' }}">
        <meta property="og:url" content="{{ $seo['og']['url'] ?? url('/') }}">
        <meta property="og:image" content="{{ $seo['og']['image'] ?? '' }}">
        <meta property="og:site_name" content="{{ $seo['og']['site_name'] ?? config('app.name') }}">
    @endif
    <link rel="canonical" href="{{ $seo['canonical'] ?? url('/') }}">

    @if(!empty($seo['schema']))
        <script type="application/ld+json">
            {!! json_encode($seo['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
        </script>
    @endif
@endpush

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- ═══════════════ STYLES ════════════════════════════════ --}}
{{-- ══════════════════════════════════════════════════════════ --}}

@push('styles')
<style>
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

    @keyframes slideInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endpush

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- ═══════════════ SCRIPTS ═══════════════════════════════ --}}
{{-- ══════════════════════════════════════════════════════════ --}}

@push('scripts')
<script>
/**
 * Hero Slider — Alpine.js Component
 */
function heroSlider(totalSlides) {
    return {
        current: 0,
        total: totalSlides,
        loaded: false,
        progressInterval: null,
        progress: 0,
        paused: false,
        autoplayDuration: 5000,
        progressStep: 50,
        init() {
            this.$nextTick(() => {
                this.loaded = true;
                this.startAutoplay();
                this.setupSwipe();
            });
        },
        startAutoplay() {
            this.stopAutoplay();
            this.progress = 0;

            if (this.total <= 1) return;

            const stepPercent = (this.progressStep / this.autoplayDuration) * 100;

            this.progressInterval = setInterval(() => {
                if (this.paused) return;
                this.progress += stepPercent;
                if (this.progress >= 100) {
                    this.next();
                }
            }, this.progressStep);
        },
        stopAutoplay() {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }
        },

        pause()  { this.paused = true; },
        resume() { this.paused = false; },

        next() {
            this.current = (this.current + 1) % this.total;
            this.progress = 0;
        },

        prev() {
            this.current = (this.current - 1 + this.total) % this.total;
            this.progress = 0;
        },

        goTo(index) {
            if (index === this.current) return;
            this.current = index;
            this.progress = 0;
        },

        setupSwipe() {
            const el = this.$el;
            let startX = 0, startY = 0;

            el.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            }, { passive: true });

            el.addEventListener('touchend', (e) => {
                const endX = e.changedTouches[0].clientX;
                const endY = e.changedTouches[0].clientY;
                const diffX = startX - endX;
                const diffY = Math.abs(startY - endY);

                if (Math.abs(diffX) > 50 && diffY < 100) {
                    diffX > 0 ? this.prev() : this.next();
                }
            }, { passive: true });
        },

        trackSliderClick(sliderId) {
            fetch(`/api/sliders/${sliderId}/click`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            }).catch(() => {});
        },
    };
}

/**
 * Animate Count — شمارنده انیمیت‌دار
 */
function animateCount(target) {
    return {
        display: '0',
        started: false,
        init() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.started) {
                        this.started = true;
                        this.countUp(target);
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(this.$el);
        },
        countUp(target) {
            const duration = 1500;
            const startTime = performance.now();

            const step = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                const value = Math.floor(eased * target);

                this.display = value.toLocaleString('fa-IR');

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    this.display = target.toLocaleString('fa-IR');
                }
            };
            requestAnimationFrame(step);
        }
    };
}
</script>
@endpush
