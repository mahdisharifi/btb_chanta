{{-- ═══════════════════════════════════════════════════════════════
     Supplier Card Component
     استفاده: <x-supplier-card :supplier="$supplier" />
     ═══════════════════════════════════════════════════════════════ --}}

@props(['supplier'])

<a href="{{ route('suppliers.show', $supplier->slug) }}"
   class="group bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-xl hover:border-blue-300 hover:-translate-y-1 transition-all duration-300">

    {{-- ═══════ HEADER ═══════ --}}
    <div class="bg-gradient-to-l from-gray-50 to-blue-50 p-6 text-center relative">
        {{-- Verified Badge --}}
        @if($supplier->is_verified)
            <span class="absolute top-3 left-3 bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                تأیید شده
            </span>
        @endif

        {{-- Logo --}}
        <div class="w-20 h-20 mx-auto rounded-2xl bg-white border border-gray-200 overflow-hidden shadow-sm mb-3 group-hover:shadow-md transition-shadow">
            @if($supplier->logo_path)
                <img src="{{ $supplier->logoUrl('small') }}"
                     alt="{{ $supplier->company_name }}"
                     class="w-full h-full object-contain p-2"
                     loading="lazy">
            @else
                <div class="w-full h-full flex items-center justify-center text-2xl font-extrabold text-blue-300">
                    {{ mb_substr($supplier->company_name, 0, 1) }}
                </div>
            @endif
        </div>

        {{-- Company Name --}}
        <h3 class="font-bold text-gray-800 group-hover:text-blue-600 transition line-clamp-1 text-base">
            {{ $supplier->company_name }}
        </h3>
        
        {{-- Brand Name --}}
        @if($supplier->brand_name)
            <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $supplier->brand_name }}</p>
        @endif
    </div>

    {{-- ═══════ DETAILS ═══════ --}}
    <div class="p-4 space-y-2.5">
        {{-- Location --}}
        @if($supplier->province || $supplier->city)
            <div class="flex items-center gap-1.5 text-xs text-gray-500">
                <x-heroicon-o-map-pin class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                <span class="line-clamp-1">
                    {{ $supplier->city ?? '' }}{{ $supplier->city && $supplier->province ? '، ' : '' }}{{ $supplier->province ?? '' }}
                </span>
            </div>
        @endif

        {{-- Products Count --}}
        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <span class="flex items-center gap-1.5 text-xs text-gray-500">
                <x-heroicon-o-cube class="w-3.5 h-3.5 text-gray-400" />
                <span class="font-medium text-gray-700">{{ number_format($supplier->total_products_count ?? $supplier->products_count ?? 0) }}</span>
                محصول
            </span>
            
            {{-- CTA --}}
            <span class="text-xs text-blue-500 font-medium group-hover:text-blue-600 flex items-center gap-0.5">
                مشاهده پروفایل
                <x-heroicon-o-arrow-left class="w-3 h-3 rtl:rotate-180 group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5 transition-transform" />
            </span>
        </div>

        {{-- Member Since (Optional) --}}
        @if(isset($supplier->created_at))
            <div class="text-[10px] text-gray-400 text-center pt-1">
                عضو از {{ jdate($supplier->created_at)->format('Y') }}
            </div>
        @endif
    </div>
</a>
