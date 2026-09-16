{{-- 
    Product Gallery Component
    نمایش گالری محصول با پشتیبانی از تصاویر و ویدئو
    
    Props:
    - $product: مدل محصول
--}}

@props(['product'])

@php
// Placeholder URL
$placeholderUrl = asset('images/placeholders/product-default.svg');

// جمع‌آوری و مرتب‌سازی media: ویدئوها اول، بعد تصاویر
$allMedia = $product->allMediaSorted
    ->sortBy(function($gallery) {
        return $gallery->file_type === 'video' ? 0 : 1;
    })
    ->map(function ($gallery) use ($product, $placeholderUrl) {
        $url = null;
        $thumb = null;
        
        if ($gallery->file_type === 'image') {
            $url = $gallery->getVersionUrl('xl') ?? $placeholderUrl;
            $thumb = $gallery->getVersionUrl('sm') ?? $placeholderUrl;
        } else {
            $url = $gallery->full_url;
            $thumb = $gallery->getVideoThumbnailUrl();
        }
        
        return [
            'id' => $gallery->id,
            'type' => $gallery->file_type,
            'url' => $url,
            'thumb' => $thumb,
            'alt' => $gallery->alt_text ?? $product->title,
            'title' => $gallery->title,
            'fallback' => $placeholderUrl,
        ];
    })
    ->values();

// اگر هیچ media نداریم، یک placeholder اضافه کن
if ($allMedia->isEmpty()) {
    $allMedia = collect([[
        'id' => 0,
        'type' => 'image',
        'url' => $placeholderUrl,
        'thumb' => $placeholderUrl,
        'alt' => $product->title,
        'title' => 'تصویر پیش‌فرض',
        'fallback' => $placeholderUrl,
    ]]);
}
@endphp

<div class="lg:col-span-5" 
     x-data="{
         activeMedia: 0,
         zoomOpen: false,
         media: {{ Js::from($allMedia) }},
         placeholderUrl: '{{ $placeholderUrl }}',
         
         get currentMedia() { 
             return this.media[this.activeMedia] || this.media[0] || null; 
         },
         
         get isVideo() { 
             return this.currentMedia?.type === 'video'; 
         },
         
         get isImage() { 
             return this.currentMedia?.type === 'image'; 
         },
         
         get isPlaceholder() { 
             if (!this.currentMedia) return true;
             const url = this.currentMedia.url;
             return url === this.placeholderUrl || url.includes('product-default.svg');
         },
         
         next() { 
             this.activeMedia = (this.activeMedia + 1) % this.media.length; 
         },
         
         prev() { 
             this.activeMedia = (this.activeMedia - 1 + this.media.length) % this.media.length; 
         },
         
         openZoom() { 
             if (this.isImage && !this.isPlaceholder) {
                 this.zoomOpen = true; 
             }
         },
         
         closeZoom() { 
             this.zoomOpen = false; 
         },
         
         handleImageError(event, item) {
             event.target.src = item.fallback || this.placeholderUrl;
             event.target.onerror = null;
         }
     }">

    <div class="relative bg-white rounded-2xl border border-gray-200 overflow-hidden aspect-[4/3] sm:aspect-square mb-3 shadow-sm group">
        
        <template x-if="currentMedia && isImage">
            <div @click="openZoom()" 
                 :class="!isPlaceholder ? 'cursor-zoom-in' : 'cursor-default'"
                 class="w-full h-full">
                <img :src="currentMedia.url"
                     :alt="currentMedia.alt"
                     width="800" 
                     height="800"
                     loading="eager"
                     fetchpriority="high"
                     decoding="async"
                     x-on:error="handleImageError($event, currentMedia)"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="w-full h-full object-contain p-4 transition-transform duration-300 group-hover:scale-105">
                
                <div x-show="!isPlaceholder" 
                     class="absolute bottom-3 left-3 bg-black/60 text-white px-3 py-1.5 rounded-lg text-xs flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <x-heroicon-o-magnifying-glass-plus class="w-4 h-4" />
                    <span>کلیک برای بزرگنمایی</span>
                </div>
            </div>
        </template>

        <template x-if="currentMedia && isVideo">
            <div class="w-full h-full flex items-center justify-center p-4">
                <video 
                    :src="currentMedia.url"
                    :poster="currentMedia.thumb"
                    controls
                    class="w-full h-full object-contain rounded-lg"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100">
                    مرورگر شما از پخش ویدئو پشتیبانی نمی‌کند.
                </video>
            </div>
        </template>

        <template x-if="!currentMedia">
            <div class="w-full h-full flex items-center justify-center text-gray-300">
                <svg class="w-24 h-24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </template>

        <template x-if="media.length > 1">
            <div>
                <button @click="prev()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm shadow-lg flex items-center justify-center text-gray-600 hover:bg-white hover:text-blue-600 transition-all opacity-0 group-hover:opacity-100 z-10">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <button @click="next()"
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm shadow-lg flex items-center justify-center text-gray-600 hover:bg-white hover:text-blue-600 transition-all opacity-0 group-hover:opacity-100 z-10">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
            </div>
        </template>

        @if($product->is_featured)
            <span class="absolute top-3 right-3 bg-amber-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg z-10">
                ⭐ ویژه
            </span>
        @endif
    </div>

    <template x-if="media.length > 1">
        <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-gray-300">
            <template x-for="(item, index) in media" :key="index">
                <button @click="activeMedia = index"
                        class="relative shrink-0 w-16 h-16 rounded-xl border-2 overflow-hidden transition-all duration-200"
                        :class="activeMedia === index
                            ? 'border-blue-500 shadow-lg shadow-blue-100 ring-2 ring-blue-200'
                            : 'border-gray-200 hover:border-gray-300'">
                    <img :src="item.thumb" 
                         :alt="item.alt" 
                         class="w-full h-full object-cover"
                         x-on:error="handleImageError($event, item)">
                    
                    <template x-if="item.type === 'video'">
                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                            </svg>
                        </div>
                    </template>
                </button>
            </template>
        </div>
    </template>

    <div x-show="zoomOpen" 
         x-cloak
         @click.self="closeZoom()"
         @keydown.escape.window="closeZoom()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <button @click="closeZoom()"
                class="absolute top-4 left-4 w-10 h-10 rounded-full bg-white/10 backdrop-blur-sm hover:bg-white/20 flex items-center justify-center text-white transition-all z-10">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <template x-if="media.length > 1">
            <div>
                <button @click.stop="prev()"
                        class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 backdrop-blur-sm hover:bg-white/20 flex items-center justify-center text-white transition-all">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <button @click.stop="next()"
                        class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 backdrop-blur-sm hover:bg-white/20 flex items-center justify-center text-white transition-all">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
            </div>
        </template>

        <template x-if="currentMedia && isImage">
            <img :src="currentMedia.url"
                 :alt="currentMedia.alt"
                 class="max-w-full max-h-full object-contain"
                 x-on:error="handleImageError($event, currentMedia)"
                 @click.stop>
        </template>

        <div class="absolute bottom-4 right-1/2 translate-x-1/2 bg-white/10 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm">
            <span x-text="activeMedia + 1"></span> / <span x-text="media.length"></span>
        </div>
    </div>
</div>
