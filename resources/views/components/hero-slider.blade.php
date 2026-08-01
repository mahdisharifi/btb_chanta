@props(['sliders'])

@if($sliders && $sliders->isNotEmpty())
<section class="relative overflow-hidden bg-gray-900">
    <div id="hero-slider" class="splide" aria-label="اسلایدر اصلی">
        <div class="splide__track">
            <ul class="splide__list">
                @foreach($sliders as $slider)
                    <li class="splide__slide">
                        <div class="relative h-[300px] sm:h-[400px] md:h-[450px] lg:h-[500px] xl:h-[550px]"
                             style="background-color: {{ $slider->bg_color ?? '#1e3a5f' }};">

                            {{-- تصویر پس‌زمینه --}}
                            @php
                                $desktopImg = $slider->image_path 
                                    ? asset('storage/' . $slider->image_path) 
                                    : null;
                                $mobileImg = $slider->mobile_image_path 
                                    ? asset('storage/' . $slider->mobile_image_path) 
                                    : $desktopImg;
                            @endphp

                            @if($desktopImg)
                                {{-- Desktop Image --}}
                                <img data-splide-lazy="{{ $desktopImg }}"
                                     alt="{{ $slider->title ?? 'اسلایدر' }}"
                                     class="absolute inset-0 w-full h-full object-cover hidden sm:block">
                                
                                {{-- Mobile Image --}}
                                <img data-splide-lazy="{{ $mobileImg }}"
                                     alt="{{ $slider->title ?? 'اسلایدر' }}"
                                     class="absolute inset-0 w-full h-full object-cover sm:hidden">
                            @endif

                            {{-- Overlay گرادیانت --}}
                            <div class="absolute inset-0 bg-gradient-to-l from-transparent via-black/30 to-black/60"></div>

                            {{-- محتوای متنی --}}
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                                    <div class="max-w-xl 
                                        {{ ($slider->text_position ?? 'right') === 'left' ? 'mr-auto text-left' : '' }}
                                        {{ ($slider->text_position ?? 'right') === 'center' ? 'mx-auto text-center' : '' }}
                                        {{ ($slider->text_position ?? 'right') === 'right' ? 'ml-auto text-right' : '' }}
                                    ">

                                        {{-- عنوان --}}
                                        @if($slider->title)
                                            <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-black leading-tight mb-4 drop-shadow-lg"
                                                style="color: {{ $slider->text_color ?? '#ffffff' }};">
                                                {{ $slider->title }}
                                            </h2>
                                        @endif

                                        {{-- زیرعنوان --}}
                                        @if($slider->subtitle)
                                            <p class="text-base sm:text-lg md:text-xl leading-relaxed mb-6 opacity-90 drop-shadow-md"
                                               style="color: {{ $slider->text_color ?? '#ffffff' }};">
                                                {{ $slider->subtitle }}
                                            </p>
                                        @endif

                                        {{-- دکمه CTA --}}
                                        @if($slider->link_url)
                                            <a href="{{ $slider->link_url }}"
                                               target="{{ $slider->link_target ?? '_self' }}"
                                               class="inline-flex items-center gap-2 bg-white hover:bg-gray-100 
                                                      text-gray-900 font-bold px-6 sm:px-8 py-3 sm:py-4 
                                                      rounded-xl shadow-lg hover:shadow-xl 
                                                      transform hover:-translate-y-0.5 transition-all duration-300">
                                                {{ $slider->link_text ?? 'مشاهده بیشتر' }}
                                                <svg class="w-5 h-5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                                </svg>
                                            </a>
                                        @endif

                                    </div>
                                </div>
                            </div>

                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
@else
{{-- Fallback اگر اسلایدری وجود ندارد --}}
<section class="relative bg-gradient-to-bl from-blue-600 via-blue-700 to-indigo-800 h-[300px] sm:h-[400px] flex items-center justify-center">
    <div class="text-center text-white">
        <h2 class="text-3xl font-bold mb-2">به مارکت‌پلیس B2B خوش آمدید</h2>
        <p class="text-blue-200">بزرگترین پلتفرم تجارت عمده ایران</p>
    </div>
</section>
@endif

<scripts>
function heroSlider(totalSlides) {
    return {
        current: 0,
        total: totalSlides,
        loaded: false,
        paused: false,
        progress: 0,
        interval: null,
        progressInterval: null,
        autoPlayDuration: 5000,

        init() {
            // شروع اتوماتیک بعد از لود
            this.$nextTick(() => {
                this.loaded = true;
                this.startAutoPlay();
            });
        },

        startAutoPlay() {
            if (this.total <= 1) return;

            this.progress = 0;
            const step = 100 / (this.autoPlayDuration / 100);

            this.progressInterval = setInterval(() => {
                if (!this.paused) {
                    this.progress += step;
                    if (this.progress >= 100) {
                        this.next();
                    }
                }
            }, 100);
        },

        stopAutoPlay() {
            clearInterval(this.progressInterval);
            this.progress = 0;
        },

        resetProgress() {
            this.progress = 0;
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
        },

        trackSliderClick(sliderId) {
            // ارسال آمار کلیک (اختیاری)
            fetch(`/api/sliders/${sliderId}/click`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Content-Type': 'application/json',
                }
            }).catch(() => {});
        }
    };
}

</scripts>

@endpush
