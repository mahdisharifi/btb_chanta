{{-- resources/views/components/hero-slider.blade.php --}}
{{-- ⚠️ هیچ @push / @endpush در این فایل نباید وجود داشته باشد --}}

@if($sliders->isNotEmpty())
<section id="hero-slider"
         class="relative overflow-hidden group"
         x-data="heroSlider({{ $sliders->count() }})"
         x-init="init()"
         x-cloak
         @mouseenter="pause()"
         @mouseleave="resume()"
         @keydown.left.window="next()"
         @keydown.right.window="prev()">

    {{-- ── Loading Skeleton ── --}}
    <div x-show="!loaded"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute inset-0 bg-gradient-to-br from-blue-900 to-blue-700 animate-pulse z-10">
        <div class="max-w-[1440px] mx-auto px-6 lg:px-8 h-full flex items-center">
            <div class="space-y-4">
                <div class="h-8 w-72 bg-white/20 rounded-lg"></div>
                <div class="h-5 w-96 bg-white/10 rounded-lg"></div>
                <div class="h-12 w-40 bg-white/15 rounded-xl mt-4"></div>
            </div>
        </div>
    </div>

    {{-- ── Slides Container ── --}}
    <div class="relative h-[260px] sm:h-[340px] md:h-[400px] lg:h-[460px] xl:h-[500px]">
        @foreach($sliders as $index => $slider)
            <div class="absolute inset-0 transition-all duration-700"
                 x-show="current === {{ $index }}"
                 x-transition:enter="transition ease-out duration-700"
                 x-transition:enter-start="opacity-0 transform scale-[1.02]"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-400"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-[0.98]">

                {{-- Background --}}
                <div class="absolute inset-0"
                     style="background-color: {{ $slider->bg_color ?? '#1e40af' }};">

                    @php
                        $imgUrl = $slider->image_url ?? ($slider->image_path ? asset('storage/' . $slider->image_path) : null);
                        $mobileImgUrl = $slider->mobile_image_url ?? ($slider->mobile_image_path ? asset('storage/' . $slider->mobile_image_path) : $imgUrl);
                    @endphp

                    @if($imgUrl)
                        <picture>
                            @if($mobileImgUrl && $mobileImgUrl !== $imgUrl)
                                <source media="(max-width: 640px)" srcset="{{ $mobileImgUrl }}">
                            @endif
                            <img src="{{ $imgUrl }}"
                                 alt="{{ $slider->title ?? 'اسلایدر' }}"
                                 class="w-full h-full object-cover"
                                 loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                 @if($index === 0) fetchpriority="high" @endif
                                 @load="loaded = true">
                        </picture>
                    @else
                        <div class="absolute inset-0 opacity-20"
                             style="background-image: radial-gradient(circle at 25% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);">
                        </div>
                    @endif

                    {{-- Overlay --}}
                    <div class="absolute inset-0"
                         style="background: linear-gradient(
                             {{ ($slider->text_position ?? 'right') === 'left' ? 'to right' : (($slider->text_position ?? 'right') === 'center' ? 'to top' : 'to left') }},
                             {{ $slider->overlay_color ?? 'rgba(0,0,0,0.4)' }} 0%,
                             {{ $slider->overlay_color ?? 'rgba(0,0,0,0.2)' }} 40%,
                             transparent 100%
                         );">
                    </div>
                </div>

                {{-- Content --}}
                <div class="relative h-full max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 flex items-center
                            {{ ($slider->text_position ?? 'right') === 'left' ? 'justify-end' : (($slider->text_position ?? 'right') === 'center' ? 'justify-center text-center' : 'justify-start') }}">
                    <div class="max-w-xl {{ ($slider->text_position ?? 'right') === 'center' ? 'text-center' : '' }}"
                         x-show="current === {{ $index }}"
                         x-transition:enter="transition ease-out duration-700 delay-300"
                         x-transition:enter-start="opacity-0 translate-y-8"
                         x-transition:enter-end="opacity-100 translate-y-0">

                        @if($slider->title)
                            <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-[2.75rem] font-extrabold leading-tight mb-3 drop-shadow-lg"
                                style="color: {{ $slider->text_color ?? '#ffffff' }}">
                                {{ $slider->title }}
                            </h2>
                        @endif

                        @if($slider->subtitle)
                            <p class="text-sm sm:text-base md:text-lg leading-relaxed mb-6 max-w-md opacity-90 drop-shadow-md
                                      {{ ($slider->text_position ?? 'right') === 'center' ? 'mx-auto' : '' }}"
                               style="color: {{ $slider->text_color ?? '#ffffff' }}">
                                {{ $slider->subtitle }}
                            </p>
                        @endif

                        @if(!empty($slider->description))
                            <p class="text-xs sm:text-sm leading-relaxed mb-4 max-w-lg opacity-70 hidden md:block
                                      {{ ($slider->text_position ?? 'right') === 'center' ? 'mx-auto' : '' }}"
                               style="color: {{ $slider->text_color ?? '#ffffff' }}">
                                {{ \Illuminate\Support\Str::limit($slider->description, 120) }}
                            </p>
                        @endif

                        @if($slider->link_url)
                            <a href="{{ $slider->link_url }}"
                               target="{{ $slider->link_target ?? '_self' }}"
                               class="inline-flex items-center gap-2.5 bg-white font-bold px-6 sm:px-8 py-3 sm:py-3.5 rounded-xl
                                      shadow-lg hover:shadow-2xl transform hover:-translate-y-0.5 transition-all duration-300
                                      text-sm sm:text-base group"
                               style="color: {{ $slider->bg_color ?? '#1e40af' }};">
                                {{ $slider->link_text ?? 'مشاهده بیشتر' }}
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 rotate-180 transition-transform group-hover:-translate-x-1"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ══════════ Navigation Controls ══════════ --}}
    @if($sliders->count() > 1)

        {{-- Arrow Buttons --}}
        <button @click="prev()"
                class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 z-20
                       w-10 h-10 sm:w-12 sm:h-12 rounded-full
                       bg-white/15 backdrop-blur-md border border-white/20
                       text-white hover:bg-white/30
                       flex items-center justify-center
                       transition-all duration-300 shadow-lg
                       opacity-0 group-hover:opacity-100 focus:opacity-100
                       hover:scale-110 active:scale-95"
                aria-label="اسلاید قبلی">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        <button @click="next()"
                class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 z-20
                       w-10 h-10 sm:w-12 sm:h-12 rounded-full
                       bg-white/15 backdrop-blur-md border border-white/20
                       text-white hover:bg-white/30
                       flex items-center justify-center
                       transition-all duration-300 shadow-lg
                       opacity-0 group-hover:opacity-100 focus:opacity-100
                       hover:scale-110 active:scale-95"
                aria-label="اسلاید بعدی">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        {{-- Dots Indicator --}}
        <div class="absolute bottom-4 sm:bottom-6 left-1/2 -translate-x-1/2 z-20
                    flex items-center gap-2 bg-black/20 backdrop-blur-sm rounded-full px-3 py-2">
            @foreach($sliders as $index => $slider)
                <button @click="goTo({{ $index }})"
                        class="relative rounded-full transition-all duration-500 ease-out"
                        :class="current === {{ $index }}
                            ? 'w-8 h-2.5 bg-white shadow-md'
                            : 'w-2.5 h-2.5 bg-white/40 hover:bg-white/70'"
                        aria-label="رفتن به اسلاید {{ $index + 1 }}">
                </button>
            @endforeach
        </div>

        {{-- Progress Bar --}}
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-white/10 z-20">
            <div class="h-full bg-white/50 transition-all duration-100 ease-linear rounded-full"
                 :style="`width: ${progress}%`">
            </div>
        </div>

        {{-- Slide Counter --}}
        <div class="absolute top-4 left-4 sm:top-6 sm:left-6 z-20
                    bg-black/25 backdrop-blur-sm text-white text-xs font-medium
                    px-3 py-1.5 rounded-full hidden sm:flex items-center gap-1.5">
            <span x-text="current + 1" class="font-bold"></span>
            <span class="opacity-50">/</span>
            <span class="opacity-70">{{ $sliders->count() }}</span>
        </div>

    @endif
</section>
@endif
