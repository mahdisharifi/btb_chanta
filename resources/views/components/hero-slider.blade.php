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
@endphp

@if($sliders && $sliders->isNotEmpty())
<section class="relative overflow-hidden">
    <div 
        x-data="mainSlider({{ $sliders->count() }})"
        x-init="init()"
        @mouseenter="pause()"
        @mouseleave="resume()"
        class="relative"
    >
        {{-- اسلایدها --}}
        <div class="relative h-[280px] sm:h-[320px] md:h-[360px] lg:h-[400px]">
            @foreach($sliders as $index => $slider)
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
                    {{-- محتوای اسلاید --}}
                    <div class="relative h-full flex items-center">
                        <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div class="max-w-4xl mx-auto text-center">
                                
                                {{-- عنوان --}}
                                @if($slider->title)
                                    <h2 
                                        class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-black leading-tight mb-3 sm:mb-4 drop-shadow-lg animate-fade-in-up"
                                        style="color: {{ $slider->text_color ?? '#ffffff' }}; animation-delay: 0.1s;"
                                    >
                                        {{ $slider->title }}
                                    </h2>
                                @endif

                                {{-- زیرعنوان --}}
                                @if($slider->subtitle)
                                    <p 
                                        class="text-sm sm:text-base md:text-lg leading-relaxed mb-4 sm:mb-6 opacity-95 drop-shadow-md animate-fade-in-up"
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
                                        class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-900 font-bold px-5 sm:px-8 py-2.5 sm:py-3 rounded-xl shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 animate-fade-in-up text-sm sm:text-base"
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
                class="absolute left-4 top-1/2 -translate-y-1/2 z-10 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white p-2 sm:p-3 rounded-full shadow-lg transition-all duration-300 hover:scale-110 group"
                aria-label="اسلاید قبلی"
            >
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            {{-- Next --}}
            <button 
                @click="next()"
                class="absolute right-4 top-1/2 -translate-y-1/2 z-10 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white p-2 sm:p-3 rounded-full shadow-lg transition-all duration-300 hover:scale-110 group"
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
                        @click="goTo({{ $index }})"
                        class="relative group"
                        aria-label="رفتن به اسلاید {{ $index + 1 }}"
                    >
                        <div class="w-10 sm:w-12 h-1.5 bg-white/30 rounded-full overflow-hidden backdrop-blur-sm">
                            <div 
                                x-show="current === {{ $index }}"
                                class="h-full bg-white rounded-full transition-all duration-300"
                                :style="`width: ${current === {{ $index }} ? progress : 0}%`"
                            ></div>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</section>

@else
{{-- Fallback - اسلایدر پیش‌فرض --}}
<section class="relative overflow-hidden h-[280px] sm:h-[320px] md:h-[360px] lg:h-[400px]" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
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
<script>
function mainSlider(totalSlides) {
    return {
        current: 0,
        total: totalSlides,
        paused: false,
        progress: 0,
        progressInterval: null,
        autoPlayDuration: 5000, // 5 ثانیه

        init() {
            if (this.total > 1) {
                this.startAutoPlay();
            }
        },

        startAutoPlay() {
            this.progress = 0;
            const step = 100 / (this.autoPlayDuration / 50);

            this.progressInterval = setInterval(() => {
                if (!this.paused) {
                    this.progress += step;
                    if (this.progress >= 100) {
                        this.next();
                    }
                }
            }, 50);
        },

        stopAutoPlay() {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }
        },

        resetProgress() {
            this.stopAutoPlay();
            this.progress = 0;
            this.startAutoPlay();
        },

        next() {
            this.current = (this.current + 1) % this.total;
            this.resetProgress();
        },

        prev() {
            this.current = (this.current - 1 + this.total) % this.total;
            this.resetProgress();
        },

        goTo(index) {
            if (index !== this.current) {
                this.current = index;
                this.resetProgress();
            }
        },

        pause() {
            this.paused = true;
        },

        resume() {
            this.paused = false;
        }
    };
}
</script>
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

/* بهینه‌سازی برای مرورگرهای قدیمی */
@supports not (backdrop-filter: blur(10px)) {
    .backdrop-blur-sm {
        background-color: rgba(255, 255, 255, 0.3) !important;
    }
}
</style>
@endpush
