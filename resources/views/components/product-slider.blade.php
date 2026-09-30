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
    class="w-full  select-none"
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
                @click="scrollByItem(1)"
                class="rounded-xl border border-amber-100 bg-white p-2 shadow-sm transition hover:bg-amber-50"
            >
                <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-700" />
            </button>

            <button
                type="button"
                aria-label="اسکرول به چپ"
                @click="scrollByItem(-1)"
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
        class="product-slider-scroll flex cursor-grab gap-3 overflow-x-auto scroll-smooth px-1 pb-4 active:cursor-grabbing snap-x"
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
        autoScrollInterval: null,
        isHovered: false,
        isIntersecting: false,
        observer: null,
        
        init() {
            // اضافه کردن event listener برای مدیریت کلیک روی لینک‌ها
            this.$refs.slider.addEventListener('click', (e) => {
                if (this.dragged) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    this.dragged = false;
                }
            }, true);
            
            // Intersection Observer برای شروع فقط وقتی اسلایدر دیده شد
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.isIntersecting = true;
                        // شروع auto-scroll با تأخیر 2 ثانیه پس از دیده شدن (بهینه‌سازی شده)
                        setTimeout(() => {
                            if (this.isIntersecting && !this.isHovered) {
                                this.startAutoScroll();
                            }
                        }, 2000);
                    } else {
                        this.isIntersecting = false;
                        this.stopAutoScroll();
                    }
                });
            }, { threshold: 0.3 }); // وقتی 30% اسلایدر دیده شد
            
            this.observer.observe(this.$refs.slider);
            
            // توقف auto-scroll هنگام hover
            this.$refs.slider.addEventListener('mouseenter', () => {
                this.isHovered = true;
                this.pauseAutoScroll();
            });
            
            this.$refs.slider.addEventListener('mouseleave', () => {
                this.isHovered = false;
                if (this.isIntersecting && !this.isDown) {
                    this.startAutoScroll();
                }
            });
            
            // Touch support برای موبایل
            this.$refs.slider.addEventListener('touchstart', () => {
                this.pauseAutoScroll();
            });
            
            this.$refs.slider.addEventListener('touchend', () => {
                // ادامه auto-scroll پس از 2 ثانیه
                setTimeout(() => {
                    if (!this.isHovered && this.isIntersecting) {
                        this.startAutoScroll();
                    }
                }, 2000);
            });
        },
        
        getItemWidth() {
            const slider = this.$refs.slider;
            const containerWidth = slider.clientWidth;
            const gap = 12; // gap-3 = 0.75rem = 12px
            
            // محاسبه عرض هر آیتم بر اساس breakpoint
            if (window.innerWidth < 640) {
                // موبایل: 2.5 کارت
                return (containerWidth * 0.4) + (gap * 0.5);
            } else if (window.innerWidth < 768) {
                // تبلت: 3 کارت
                return (containerWidth * 0.333) + gap;
            } else {
                // دسکتاپ: عرض ثابت w-52 = 208px
                return 220; // 208px + gap
            }
        },
        
        scrollByItem(direction) {
            const slider = this.$refs.slider;
            const itemWidth = this.getItemWidth();
            
            slider.scrollBy({ 
                left: itemWidth * direction,  // direction: 1 یا -1
                behavior: 'smooth' 
            });
            
            // توقف موقت auto-scroll پس از کلیک کاربر
            this.pauseAutoScroll();
            setTimeout(() => {
                if (!this.isHovered && this.isIntersecting) {
                    this.startAutoScroll();
                }
            }, 3000); // ادامه پس از 3 ثانیه
        },
        
        startAutoScroll() {
            this.stopAutoScroll();
            
            const slider = this.$refs.slider;
            const itemWidth = this.getItemWidth();
            
            // زمان 5 ثانیه (بهینه‌سازی شده برای خوانایی بهتر)
            this.autoScrollInterval = setInterval(() => {
                if (!this.isHovered && !this.isDown && this.isIntersecting) {
                    const maxScroll = slider.scrollWidth - slider.clientWidth;
                    
                    // بررسی رسیدن به انتها (در RTL، scrollLeft منفی می‌شود)
                    if (Math.abs(slider.scrollLeft) >= maxScroll - 10) {
                        // رسید به انتها - بازگشت به ابتدا
                        slider.scrollTo({ 
                            left: 0, 
                            behavior: 'smooth' 
                        });
                    } else {
                        // اسکرول به چپ (به سمت محصولات بعدی در RTL)
                        slider.scrollBy({ 
                            left: -itemWidth,  // منفی = به چپ در RTL
                            behavior: 'smooth' 
                        });
                    }
                }
            }, 5000); // هر 5 ثانیه
        },
        
        stopAutoScroll() {
            if (this.autoScrollInterval) {
                clearInterval(this.autoScrollInterval);
                this.autoScrollInterval = null;
            }
        },
        
        pauseAutoScroll() {
            this.stopAutoScroll();
        },
        
        startDrag(event) {
            this.isDown = true;
            this.dragged = false;
            this.startX = event.pageX - this.$refs.slider.offsetLeft;
            this.scrollLeft = this.$refs.slider.scrollLeft;
            this.pauseAutoScroll();
        },
        
        stopDrag() {
            this.isDown = false;
            if (!this.isHovered && this.isIntersecting) {
                this.startAutoScroll();
            }
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
