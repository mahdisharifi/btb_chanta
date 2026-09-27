@props([
'products',
'title' => '',
'subtitle' => '',
'moreLink' => null,
'userRole' => 'guest',
'icon' => null,
])

<div
    x-data="{
        dragged: false,
        startX: 0,
        scrollLeft: 0,
        isDown: false,
        dragThreshold: 8
    }"
    class="w-full py-3 select-none"
>
    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2.5">
            @isset($icon)
            <div class="shrink-0">
                {{ $icon }}
            </div>
            @endisset

            <div class="min-w-0">
                <h2 class="text-base font-extrabold leading-tight text-gray-800 sm:text-lg">
                    {{ $title }}
                </h2>

                @if($subtitle)
                <p class="mt-0.5 text-xs leading-relaxed text-gray-500">
                    {{ $subtitle }}
                </p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if(!empty($moreLink))
            <a
                href="{{ $moreLink }}"
                class="text-xs font-semibold text-amber-700 transition hover:text-amber-800"
                aria-label="مشاهده همه {{ $title }}"
            >
                مشاهده همه
            </a>
            @endif

            <button
                type="button"
                aria-label="اسکرول به راست"
                @click="$refs.slider.scrollBy({ left: 280, behavior: 'smooth' })"
                class="rounded-lg border border-amber-100 bg-white p-1.5 shadow-sm transition hover:bg-amber-50"
            >
                <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-700" />
            </button>

            <button
                type="button"
                aria-label="اسکرول به چپ"
                @click="$refs.slider.scrollBy({ left: -280, behavior: 'smooth' })"
                class="rounded-lg border border-amber-100 bg-white p-1.5 shadow-sm transition hover:bg-amber-50"
            >
                <x-heroicon-o-chevron-left class="h-4 w-4 text-gray-700" />
            </button>
        </div>
    </div>

    {{-- Scroll Container با Gradient Indicators --}}
    <div class="relative">
        {{-- Left Gradient Indicator --}}
        <div 
            class="pointer-events-none absolute left-0 top-0 z-10 h-full w-12 bg-gradient-to-r from-gray-50 to-transparent opacity-0 transition-opacity duration-300 lg:hidden"
            x-data="{ show: false }"
            x-init="let slider = $el.parentElement.querySelector('[x-ref=slider]'); if (slider) { slider.addEventListener('scroll', () => { show = slider.scrollLeft > 20; }); }"
            x-show="show"
            x-transition:enter="transition-opacity duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>
        
        {{-- Right Gradient Indicator --}}
        <div 
            class="pointer-events-none absolute right-0 top-0 z-10 h-full w-12 bg-gradient-to-l from-gray-50 to-transparent opacity-0 transition-opacity duration-300 lg:hidden"
            x-data="{ show: true }"
            x-init="let slider = $el.parentElement.querySelector('[x-ref=slider]'); if (slider) { slider.addEventListener('scroll', () => { show = slider.scrollLeft < (slider.scrollWidth - slider.clientWidth - 20); }); }"
            x-show="show"
            x-transition:enter="transition-opacity duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>

        <div
            x-ref="slider"
            @mousedown="isDown = true; dragged = false; startX = $event.pageX - $refs.slider.offsetLeft; scrollLeft = $refs.slider.scrollLeft"
            @mouseleave="isDown = false"
            @mouseup="isDown = false"
            @mousemove="if (!isDown) return; $event.preventDefault(); const x = $event.pageX - $refs.slider.offsetLeft; const walk = x - startX; if (Math.abs(walk) > dragThreshold) dragged = true; $refs.slider.scrollLeft = scrollLeft - walk"
            @click.away="dragged = false"
            ondragstart="return false"
            class="product-slider-scroll flex cursor-grab gap-3 overflow-x-auto scroll-smooth px-2 pb-2 active:cursor-grabbing snap-x"
            style="-ms-overflow-style: none; -webkit-overflow-scrolling: touch; scrollbar-width: none;"
        >
            <style>
                .product-slider-scroll::-webkit-scrollbar { display: none; }
            </style>

            @foreach($products as $product)
            <div
                class="w-[calc(50%-0.375rem)] flex-shrink-0 snap-start sm:w-[calc(33.333%-0.5rem)] md:w-[calc(25%-0.56rem)] lg:w-[calc(25%-0.56rem)]"
                @click="if (dragged) { $event.preventDefault(); $event.stopImmediatePropagation(); }"
            >
                <x-product.card :product="$product" />
            </div>
            @endforeach
        </div>
    </div>
</div>
