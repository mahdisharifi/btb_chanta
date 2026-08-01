<?php

use App\Models\ProductGallery;
use function Livewire\Volt\{computed, layout};

$gallery = computed(function () {
    return ProductGallery::query()
        ->where('file_type', 'image')
        ->latest() // آخرین تصویر آپلود شده برای تست بهتر
        ->first();
});

layout('layouts.admin');
?>

<div class="min-h-screen bg-[#0a0a0a] px-4 py-12 flex items-center justify-center font-sans" dir="rtl">
    <div class="mx-auto w-full max-w-4xl rounded-[2rem] bg-gradient-to-b from-neutral-900 to-black border border-white/10 p-8 backdrop-blur-3xl shadow-[0_20px_50px_rgba(0,0,0,0.5)]">

        <div class="text-center mb-10">
            <h1 class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-[#d4af37] via-[#f2d57e] to-[#d4af37]">
                سیستم رندرینگ تطبیقی Gimmnet
            </h1>
            <p class="text-neutral-500 mt-2 text-sm tracking-widest uppercase">Responsive Image Delivery Core</p>
        </div>

        @if ($this->gallery)
        <div class="relative group">
            {{-- نور پس‌زمینه برای حالت Glassmorphism --}}
            <div class="absolute -inset-1 bg-gradient-to-r from-[#d4af37] to-[#aa8928] rounded-[2rem] blur opacity-10 group-hover:opacity-20 transition duration-1000"></div>

            <div class="relative overflow-hidden rounded-2xl bg-neutral-800/50 border border-white/5">
                <picture>
                    {{-- نسخه بسیار بزرگ برای مانیتورهای عریض --}}
                    @if($xl = $this->gallery->getVersionUrl('xl'))
                    <source srcset="{{ $xl }}" media="(min-width: 1280px)">
                    @endif

                    {{-- نسخه بزرگ (Desktop) --}}
                    @if($lg = $this->gallery->getVersionUrl('lg'))
                    <source srcset="{{ $lg }}" media="(min-width: 1024px)">
                    @endif

                    {{-- نسخه متوسط (Tablet) --}}
                    @if($md = $this->gallery->getVersionUrl('md'))
                    <source srcset="{{ $md }}" media="(min-width: 768px)">
                    @endif

                    {{-- نسخه کوچک (Mobile Landscape) --}}
                    @if($sm = $this->gallery->getVersionUrl('sm'))
                    <source srcset="{{ $sm }}" media="(min-width: 480px)">
                    @endif

                    {{-- نسخه موبایل و فال‌بک نهایی (XS) --}}
                    <img
                        src="{{ $this->gallery->getVersionUrl('xs') ?? $this->gallery->full_url }}"
                        alt="{{ $this->gallery->alt_text }}"
                        class="mx-auto h-auto w-full max-h-[65vh] object-contain shadow-2xl transition-all duration-700 ease-in-out group-hover:scale-[1.02]"
                    >
                </picture>
            </div>
        </div>

        {{-- بخش عیب‌یابی (Debug) برای اینکه بفهمی الان چه فایلی باید لود بشه --}}
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white/5 p-4 rounded-xl border border-white/5 text-center">
                <span class="text-[#d4af37] block text-xs mb-1">وضوح فعلی پنجره</span>
                <span id="window-width" class="text-white font-mono text-lg">---</span>
            </div>
            <div class="bg-white/5 p-4 rounded-xl border border-white/5 text-center">
                <span class="text-[#d4af37] block text-xs mb-1">نسخه مورد انتظار</span>
                <span id="expected-version" class="text-white font-bold text-lg">---</span>
            </div>
            <div class="bg-white/5 p-4 rounded-xl border border-white/5 text-center">
                <span class="text-[#d4af37] block text-xs mb-1">شناسه تصویر</span>
                <span class="text-white font-mono text-lg">#{{ $this->gallery->id }}</span>
            </div>
        </div>

        <script>
            function updateDebugInfo() {
                const width = window.innerWidth;
                document.getElementById('window-width').innerText = width + 'px';

                let version = 'xs (Mobile)';
                if (width >= 1280) version = 'xl (Widescreen)';
                else if (width >= 1024) version = 'lg (Desktop)';
                else if (width >= 768) version = 'md (Tablet)';
                else if (width >= 480) version = 'sm (Mobile L)';

                document.getElementById('expected-version').innerText = version;
            }
            window.addEventListener('resize', updateDebugInfo);
            updateDebugInfo();
        </script>
        @else
        <div class="text-center py-20 bg-neutral-900/50 rounded-2xl border border-dashed border-white/10">
            <p class="text-neutral-500 italic">دیتایی در جدول product_galleries یافت نشد.</p>
        </div>
        @endif
    </div>
</div>
