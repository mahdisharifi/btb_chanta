@props([
'products',
'title' => '',
'subtitle' => '',
'moreLink' => null,
'userRole' => 'guest',
'icon' => null,
])

<div
    x-data="productSlider()"
    x-init="init()"
    class="w-full py-6 select-none"
>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            @isset($icon)
            <div class="shrink-0">
                {{ $icon }}
            </div>
            @endisset

            <div class="min-w-0">
                <h2 class="text-lg font-extrabold leading-tight text-gray-800 sm:text-xl">
                    {{ $title }}
                </h2>

                @if($subtitle)
                <p class="mt-0.5 text-sm leading-relaxed text-gray-500">
                    {{ $subtitle }}
                </p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if(!empty($moreLink))
            <a
                href="{{ $moreLink }}"
                class="text-sm font-semibold text-amber-700 transition hover:text-amber-800"
                aria-label="مشاهده همه {{ $title }}"
            >
                مشاهده همه
            </a>
            @endif

            <button
                type="button"
                aria-label="اسکرول به راست"
                @click="$refs.slider.scrollBy({ left: 300, behavior: 'smooth' })"
                class="rounded-xl border border-amber-100 bg-white p-2 shadow-sm transition hover:bg-amber-50"
            >
                <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-700" />
            </button>

            <button
                type="button"
                aria-label="اسکرول به چپ"
                @click="$refs.slider.scrollBy({ left: -300, behavior: 'smooth' })"
                class="rounded-xl border border-amber-100 bg-white p-2 shadow-sm transition hover:bg-amber-50"
            >
                <x-heroicon-o-chevron-left class="h-5 w-5 text-gray-700" />
            </button>
        </div>
    </div>

    <div
        x-ref="slider"
        @mousedown="startDrag($event)"
        @mouseleave="stopDrag()"
        @mouseup="stopDrag()"
        @mousemove="onDrag($event)"
        ondragstart="return false"
        class="product-slider-scroll flex cursor-grab gap-3 overflow-x-auto scroll-smooth px-4 pb-4 active:cursor-grabbing snap-x"
        style="-ms-overflow-style: none; -webkit-overflow-scrolling: touch; scrollbar-width: none;"
    >
        <style>
            .product-slider-scroll::-webkit-scrollbar { display: none; }
            .product-slider-scroll { -webkit-user-select: none; user-select: none; }
            .product-slider-scroll a { 
                cursor: pointer;
                pointer-events: auto;
            }
            .product-slider-scroll img { pointer-events: none; }
        </style>

        @foreach($products as $product)
        <div
            class="w-[calc(50%-0.375rem)] flex-shrink-0 snap-start sm:w-[calc(33.333%-0.67rem)] md:w-52"
        >
            <x-product.card :product="$product" />
        </div>
        @endforeach
    </div>
</div>

<script>
function productSlider() {
    return {
        dragged: false,
        startX: 0,
        scrollLeft: 0,
        isDown: false,
        dragThreshold: 8,
        
        init() {
            // اضافه کردن event listener برای مدیریت کلیک روی لینک‌ها
            this.$refs.slider.addEventListener('click', (e) => {
                if (this.dragged) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    this.dragged = false;
                }
            }, true); // capture phase - قبل از اینکه event به لینک برسد
        },
        
        startDrag(event) {
            this.isDown = true;
            this.dragged = false;
            this.startX = event.pageX - this.$refs.slider.offsetLeft;
            this.scrollLeft = this.$refs.slider.scrollLeft;
        },
        
        stopDrag() {
            this.isDown = false;
        },
        
        onDrag(event) {
            if (!this.isDown) return;

            event.preventDefault();

            const currentX = event.pageX - this.$refs.slider.offsetLeft;
            const distance = currentX - this.startX;

            if (Math.abs(distance) > this.dragThreshold) {
                this.dragged = true;
            }

            this.$refs.slider.scrollLeft = this.scrollLeft - distance;
        }
    }
}
</script>
