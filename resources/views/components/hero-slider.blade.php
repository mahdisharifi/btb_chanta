{{-- 
    ═══════════════════════════════════════════════════════════════
    اسلایدر اصلی سایت (Main Site Slider)
    
    ویژگی‌ها:
    - بدون بکگراند تصویری
    - گرادینت‌های جذاب و استاندارد
    - ارتفاع بهینه
    - اسلایدر خودکار با Alpine.js
    - سازگار با تمام مرورگرها
    ═══════════════════════════════════════════════════════════════
--}}

@props(['sliders'])

@php
    // گرادینت‌های پیش‌فرض با پشتیبانی کامل مرورگر
    $defaultGradients = [
        'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);', // بنفش آبی
        'background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);', // صورتی قرمز
        'background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);', // آبی فیروزه‌ای
        'background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);', // سبز نعنایی
        'background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);', // قرمز زرد
        'background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);', // فیروزه‌ای بنفش
        'background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);', // پاستل
        'background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);', // صورتی ملایم
    ];
    $sliderImages = app(\App\Services\SliderImageService::class);
@endphp

@if($sliders && $sliders->isNotEmpty())
<section class="relative overflow-hidden">
    <div 
        x-data="mainSlider({{ $sliders->count() }})"
        x-init="init()"
        @mouseenter="pause()"
        @mouseleave="resume()"
        @focusin="pauseForFocus(true)"
        @focusout="if (!$el.contains($event.relatedTarget)) pauseForFocus(false)"
        @pointerdown="startSwipe($event)"
        @pointerup="endSwipe($event)"
        @pointercancel="cancelSwipe($event)"
        @click.capture="suppressSwipeClick($event)"
        class="homepage-hero-slider relative touch-pan-y"
    >
        {{-- اسلایدها --}}
        <div class="relative h-[190px] sm:h-[250px] md:h-[270px] lg:h-[320px] xl:h-[350px]">
            @foreach($sliders as $index => $slider)
                @php
                    $storedDesktopPath = data_get($slider, 'image_path');
                    $desktopImageUrl = filled($storedDesktopPath)
                        ? ($slider->image_url ?? asset('storage/' . ltrim($storedDesktopPath, '/')))
                        : (is_null($storedDesktopPath) ? data_get($slider, 'image_url') : null);
                    $storedMobilePath = data_get($slider, 'mobile_image_path');
                    $mobileImageUrl = filled($storedMobilePath)
                        ? ($slider->mobile_image_url ?? asset('storage/' . ltrim($storedMobilePath, '/')))
                        : (is_null($storedMobilePath) ? data_get($slider, 'mobile_image_url') : null);
                    $desktopResponsiveSet = $sliderImages->responsiveSet($storedDesktopPath);
                    $mobileResponsiveSet = $sliderImages->responsiveSet($storedMobilePath);
                    if ($desktopResponsiveSet && $desktopResponsiveSet['srcset']) {
                        $desktopImageUrl = $desktopResponsiveSet['fallback'];
                    }
                    if ($mobileResponsiveSet && $mobileResponsiveSet['srcset']) {
                        $mobileImageUrl = $mobileResponsiveSet['fallback'];
                    }
                    $desktopSrcset = $desktopResponsiveSet['srcset'] ?? null;
                    $mobileSrcset = $mobileResponsiveSet['srcset'] ?? null;
                @endphp
                <div
                    x-show="current === {{ $index }}"
                    x-transition:enter="transition ease-out duration-500"
                    x-transition:enter-start="opacity-0 transform translate-x-full"
                    x-transition:enter-end="opacity-100 transform translate-x-0"
                    x-transition:leave="transition ease-in duration-500"
                    x-transition:leave-start="opacity-100 transform translate-x-0"
                    x-transition:leave-end="opacity-0 transform -translate-x-full"
                    class="absolute inset-0 w-full h-full"
                    style="{{ $slider->bg_gradient ?? $defaultGradients[$index % count($defaultGradients)] }}"
                >
                    @if($desktopImageUrl)
                        <picture class="absolute inset-0 block h-full w-full">
                            @if($mobileImageUrl && $mobileImageUrl !== $desktopImageUrl)
                                <source media="(max-width: 639px)" srcset="{{ $mobileSrcset ?: $mobileImageUrl }}" sizes="100vw">
                            @endif
                            <img
                                src="{{ $desktopImageUrl }}"
                                @if($desktopSrcset) srcset="{{ $desktopSrcset }}" sizes="100vw" @endif
                                alt=""
                                aria-hidden="true"
                                class="absolute inset-0 h-full w-full object-cover object-center"
                                loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                decoding="async"
                                @if($index === 0) fetchpriority="high" @endif
                            >
                        </picture>
                        <div class="pointer-events-none absolute inset-0" style="background-color: {{ $slider->overlay_color ?: 'rgba(0, 0, 0, 0.35)' }}" aria-hidden="true"></div>
                    @endif
                    {{-- محتوای اسلاید --}}
                    <div class="relative z-[1] h-full flex items-center">
                        <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div class="max-w-4xl mx-auto text-center">
                                
                                {{-- عنوان --}}
                                @if($slider->title)
                                    <h2 
                                        class="hero-slide-title text-lg sm:text-2xl md:text-3xl lg:text-4xl font-black leading-tight mb-2 sm:mb-4 drop-shadow-lg animate-fade-in-up"
                                        style="color: {{ $slider->text_color ?? '#ffffff' }}; animation-delay: 0.1s;"
                                    >
                                        {{ $slider->title }}
                                    </h2>
                                @endif

                                {{-- زیرعنوان --}}
                                @if($slider->subtitle)
                                    <p 
                                        class="hero-slide-subtitle text-xs sm:text-base md:text-lg leading-snug sm:leading-relaxed mb-3 sm:mb-6 opacity-95 drop-shadow-md animate-fade-in-up"
                                        style="color: {{ $slider->text_color ?? '#ffffff' }}; animation-delay: 0.2s;"
                                    >
                                        {{ $slider->subtitle }}
                                    </p>
                                @endif

                                {{-- دکمه CTA --}}
                                @if($slider->link_url)
                                    <a 
                                        href="{{ $slider->link_url }}"
                                        target="{{ $slider->link_target ?? '_self' }}"
                                        @if(($slider->link_target ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                                        @if($slider instanceof \App\Models\Slider) @click="trackClick('{{ route('sliders.click', $slider->id) }}')" @endif
                                        class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-900 font-bold px-4 sm:px-8 py-2 sm:py-3 rounded-xl shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 animate-fade-in-up text-xs sm:text-base"
                                        style="animation-delay: 0.3s;"
                                    >
                                        {{ $slider->link_text ?? 'مشاهده بیشتر' }}
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                        </svg>
                                    </a>
                                @endif

                            </div>
                        </div>
                    </div>

                    {{-- Decorative Elements (اختیاری) --}}
                    <div class="absolute bottom-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl -mr-48 -mb-48 pointer-events-none"></div>
                    <div class="absolute top-0 left-0 w-72 h-72 bg-white/5 rounded-full blur-3xl -ml-36 -mt-36 pointer-events-none"></div>
                </div>
            @endforeach
        </div>

        {{-- Navigation Arrows --}}
        @if($sliders->count() > 1)
            {{-- Previous --}}
            <button 
                @click="prev()"
                class="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 z-10 hidden sm:block bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white p-2 sm:p-3 rounded-full shadow-lg transition-all duration-300 hover:scale-110 group"
                aria-label="اسلاید قبلی"
            >
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            {{-- Next --}}
            <button 
                @click="next()"
                class="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 z-10 hidden sm:block bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white p-2 sm:p-3 rounded-full shadow-lg transition-all duration-300 hover:scale-110 group"
                aria-label="اسلاید بعدی"
            >
                <svg class="w-5 h-5 sm:w-6 sm:h-6 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            {{-- Dots Navigation --}}
            <div class="absolute bottom-4 sm:bottom-6 left-1/2 -translate-x-1/2 z-10 flex gap-2">
                @foreach($sliders as $index => $slider)
                    <button
                        type="button"
                        @click="goTo({{ $index }})"
                        class="relative group flex min-h-6 min-w-6 items-center justify-center p-0"
                        :aria-current="current === {{ $index }} ? 'true' : 'false'"
                        aria-label="رفتن به اسلاید {{ $index + 1 }}"
                    >
                        <div class="w-5 sm:w-12 h-1.5 bg-white/30 rounded-full overflow-hidden backdrop-blur-sm">
                            <div 
                                x-show="current === {{ $index }}"
                                class="h-full bg-white rounded-full transition-all duration-300"
                                :style="`width: ${current === {{ $index }} ? progress : 0}%`"
                            ></div>
                        </div>
                    </button>
                @endforeach
            </div>

            <button
                type="button"
                x-show="!reducedMotion"
                @click="toggleUserPause()"
                class="absolute top-3 right-3 sm:top-5 sm:right-5 z-10 rounded-lg bg-black/40 px-3 py-2 text-xs font-semibold text-white hover:bg-black/60 focus:outline-none focus:ring-2 focus:ring-white"
                :aria-label="(userPaused || focusPaused) ? 'ادامهٔ چرخش اسلایدها' : 'توقف چرخش اسلایدها'"
                x-text="(userPaused || focusPaused) ? 'ادامه' : 'توقف'"
            ></button>
        @endif
    </div>
</section>

@else
{{-- Fallback - اسلایدر پیش‌فرض --}}
<section class="relative overflow-hidden h-[190px] sm:h-[250px] md:h-[270px] lg:h-[320px] xl:h-[350px]" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="relative h-full flex items-center justify-center">
        <div class="text-center text-white px-4">
            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-black mb-3 drop-shadow-lg animate-fade-in-up">
                به مارکت‌پلیس B2B خوش آمدید
            </h2>
            <p class="text-sm sm:text-base md:text-lg text-white/90 drop-shadow-md animate-fade-in-up" style="animation-delay: 0.1s;">
                بزرگترین پلتفرم تجارت عمده ایران
            </p>
        </div>
    </div>
    
    {{-- Decorative Elements --}}
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl -mr-48 -mb-48 pointer-events-none"></div>
    <div class="absolute top-0 left-0 w-72 h-72 bg-white/5 rounded-full blur-3xl -ml-36 -mt-36 pointer-events-none"></div>
</section>
@endif

@push('scripts')
{{-- JavaScript در فایل slider-utils.js قرار دارد --}}
@endpush

@push('styles')
<style>
/* انیمیشن Fade In Up */
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.6s ease-out forwards;
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .homepage-hero-slider .animate-fade-in-up {
        animation: none !important;
        opacity: 1 !important;
    }

    .homepage-hero-slider .transition {
        transition: none !important;
    }
}

.homepage-hero-slider .hero-slide-title,
.homepage-hero-slider .hero-slide-subtitle {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.homepage-hero-slider .hero-slide-title {
    -webkit-line-clamp: 2;
}

.homepage-hero-slider .hero-slide-subtitle {
    -webkit-line-clamp: 3;
}

@media (max-width: 639px) {
    .homepage-hero-slider .hero-slide-subtitle {
        -webkit-line-clamp: 2;
    }
}

/* بهینه‌سازی برای مرورگرهای قدیمی */
@supports not (backdrop-filter: blur(10px)) {
    .backdrop-blur-sm {
        background-color: rgba(255, 255, 255, 0.3) !important;
    }
}
</style>
@endpush
