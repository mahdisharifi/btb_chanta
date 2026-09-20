{{-- ═══════════════════════════════════════════════════════════════
     صفحه پروفایل تأمین‌کننده — suppliers/show.blade.php
     Mobile-first, تمام اطلاعات تأمین‌کننده
     ═══════════════════════════════════════════════════════════════ --}}

@extends('layouts.app')

@php
    use App\Helpers\PriceHelper;
    use Illuminate\Support\Str;
@endphp

{{-- JSON-LD Structured Data (Organization + Breadcrumb) --}}
@push('head')
<script type="application/ld+json">
{!! json_encode($jsonLd ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
@if(!empty($breadcrumbs))
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        @foreach($breadcrumbs as $index => $crumb)
        {
            "@type": "ListItem",
            "position": {{ $index + 1 }},
            "name": "{{ $crumb['name'] }}",
            "item": "{{ $crumb['url'] ?? url()->current() }}"
        }{{ !$loop->last ? ',' : '' }}
        @endforeach
    ]
}
</script>
@endif
@endpush

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6">

        {{-- ═══════ BREADCRUMB ═══════ --}}
        @if(!empty($breadcrumbs))
            <nav class="mb-4 sm:mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center flex-wrap gap-1 text-xs sm:text-sm text-gray-500">
                    @foreach($breadcrumbs as $index => $crumb)
                        <li class="flex items-center">
                            @if($index > 0)
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 text-gray-300 mx-1 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            @endif
                            @if($crumb['url'])
                                <a href="{{ $crumb['url'] }}" class="hover:text-blue-600 transition-colors">{{ $crumb['name'] }}</a>
                            @else
                                <span class="text-gray-800 font-medium">{{ $crumb['name'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        {{-- ═══════ SUPPLIER HEADER CARD ═══════ --}}
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-gray-200 overflow-hidden shadow-sm mb-6">
            {{-- Banner --}}
            <div class="h-24 sm:h-32 bg-gradient-to-l from-blue-500 via-indigo-500 to-purple-600 relative">
                <div class="absolute inset-0 opacity-20">
                    <svg viewBox="0 0 800 200" fill="none" class="w-full h-full">
                        <circle cx="700" cy="50" r="150" fill="white"/>
                        <circle cx="100" cy="180" r="100" fill="white"/>
                    </svg>
                </div>
            </div>

            <div class="px-4 sm:px-6 lg:px-8 pb-6 sm:pb-8 -mt-10 sm:-mt-12 relative">
                <div class="flex flex-col gap-4">
                    {{-- Row 1: Logo + Basic Info --}}
                    <div class="flex flex-col sm:flex-row gap-4">
                        {{-- Logo --}}
                        <div class="shrink-0 mx-auto sm:mx-0">
                            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-white border-4 border-white shadow-lg overflow-hidden">
                                @if($supplier->logo_path)
                                    <img src="{{ asset('storage/' . $supplier->logo_path) }}"
                                         alt="{{ $supplier->company_name }}"
                                         width="96" height="96"
                                         loading="eager"
                                         class="w-full h-full object-contain p-2">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-2xl sm:text-3xl font-extrabold text-blue-300 bg-blue-50">
                                        {{ mb_substr($supplier->company_name, 0, 1) }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Basic Info --}}
                        <div class="flex-1 text-center sm:text-right">
                            <div class="flex items-center justify-center sm:justify-start gap-2 flex-wrap mb-2">
                                <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-gray-900">
                                    {{ $supplier->company_name }}
                                </h1>
                                @if($supplier->is_verified)
                                    <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        تأیید شده
                                    </span>
                                @endif
                            </div>

                            @if($supplier->brand_name)
                                <p class="text-gray-500 text-sm mb-1">برند: <span class="font-medium text-gray-700">{{ $supplier->brand_name }}</span></p>
                            @endif

                            @if($supplier->company_name_en)
                                <p class="text-gray-400 text-xs mb-1">{{ $supplier->company_name_en }}</p>
                            @endif

                            @if($supplier->province || $supplier->city)
                                <p class="text-gray-400 text-sm flex items-center justify-center sm:justify-start gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $supplier->city ?? '' }}{{ $supplier->city && $supplier->province ? '، ' : '' }}{{ $supplier->province ?? '' }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Description --}}
                    @if($supplier->description)
                        <div class="bg-gray-50 rounded-xl p-3 sm:p-4">
                            <p class="text-gray-600 text-sm leading-relaxed">{{ $supplier->description }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══════ STATS BAR ═══════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-200 p-3 sm:p-4 text-center">
                <div class="text-xl sm:text-2xl font-extrabold text-blue-600">{{ number_format($supplierStats['total_products'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">محصول</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-200 p-3 sm:p-4 text-center">
                <div class="text-xl sm:text-2xl font-extrabold text-purple-600">{{ number_format($supplierStats['total_categories'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">دسته‌بندی</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-200 p-3 sm:p-4 text-center">
                <div class="text-xl sm:text-2xl font-extrabold text-green-600">{{ number_format($supplierStats['total_views'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">بازدید</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-200 p-3 sm:p-4 text-center">
                <div class="text-xl sm:text-2xl font-extrabold text-amber-600">{{ $supplierStats['featured_count'] ?? 0 }}</div>
                <div class="text-xs text-gray-400 mt-1">محصول ویژه</div>
            </div>
        </div>

        {{-- ═══════ CONTACT INFO CARD ═══════ --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 sm:p-6 mb-6">
            <h2 class="font-bold text-gray-800 text-base sm:text-lg mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                اطلاعات تماس
            </h2>

            @if($canViewContact)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @if($supplier->phone)
                        <a href="tel:{{ $supplier->phone }}" class="flex items-center gap-2 bg-gray-50 hover:bg-blue-50 rounded-xl px-3 py-2.5 transition group">
                            <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition">
                                <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">تلفن</div>
                                <div class="text-sm font-bold text-gray-800 rtl:direction-ltr">{{ $supplier->phone }}</div>
                            </div>
                        </a>
                    @endif

                    @if($supplier->mobile)
                        <a href="tel:{{ $supplier->mobile }}" class="flex items-center gap-2 bg-gray-50 hover:bg-green-50 rounded-xl px-3 py-2.5 transition group">
                            <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition">
                                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">موبایل</div>
                                <div class="text-sm font-bold text-gray-800 rtl:direction-ltr">{{ $supplier->mobile }}</div>
                            </div>
                        </a>
                    @endif

                    @if($supplier->email)
                        <a href="mailto:{{ $supplier->email }}" class="flex items-center gap-2 bg-gray-50 hover:bg-red-50 rounded-xl px-3 py-2.5 transition group">
                            <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center group-hover:bg-red-200 transition">
                                <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">ایمیل</div>
                                <div class="text-sm font-bold text-gray-800 break-all">{{ $supplier->email }}</div>
                            </div>
                        </a>
                    @endif

                    @if($supplier->website)
                        <a href="{{ $supplier->website }}" target="_blank" rel="noopener" class="flex items-center gap-2 bg-gray-50 hover:bg-purple-50 rounded-xl px-3 py-2.5 transition group">
                            <div class="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center group-hover:bg-purple-200 transition">
                                <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">وب‌سایت</div>
                                <div class="text-sm font-bold text-gray-800">{{ Str::limit(str_replace(['http://', 'https://'], '', $supplier->website), 25) }}</div>
                            </div>
                        </a>
                    @endif

                    {{-- Social Networks --}}
                    @php
                        $socialNetworks = $supplier->social_networks ?? [];
                        if (is_string($socialNetworks)) {
                            $socialNetworks = json_decode($socialNetworks, true) ?? [];
                        }
                    @endphp
                    @if(!empty($socialNetworks) && is_array($socialNetworks))
                        @foreach($socialNetworks as $network => $link)
                            @if($link)
                                @php
                                    $icons = [
                                        'instagram' => '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
                                        'telegram' => '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
                                        'whatsapp' => '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
                                        'linkedin' => '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
                                        'twitter' => '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>',
                                    ];
                                    $colors = [
                                        'instagram' => 'bg-gradient-to-br from-purple-500 to-pink-500',
                                        'telegram' => 'bg-blue-400',
                                        'whatsapp' => 'bg-green-500',
                                        'linkedin' => 'bg-blue-600',
                                        'twitter' => 'bg-sky-500',
                                    ];
                                    $icon = $icons[$network] ?? '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>';
                                    $color = $colors[$network] ?? 'bg-gray-400';
                                    $label = ucfirst($network);
                                @endphp
                                <a href="{{ $link }}" target="_blank" rel="noopener" class="flex items-center gap-2 bg-gray-50 hover:bg-gray-100 rounded-xl px-3 py-2.5 transition group">
                                    <div class="w-9 h-9 rounded-lg {{ $color }} flex items-center justify-center text-white">
                                        {!! $icon !!}
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-400">شبکه اجتماعی</div>
                                        <div class="text-sm font-bold text-gray-800">{{ $label }}</div>
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    @endif
                </div>

                {{-- Address --}}
                @if($supplier->address)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400 mb-1">آدرس</div>
                                <p class="text-sm text-gray-700 leading-relaxed">
                                    {{ $supplier->address }}
                                    @if($supplier->postal_code)
                                        <br><span class="text-xs text-gray-400">کد پستی: {{ $supplier->postal_code }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-4 text-sm text-amber-700">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span class="font-bold">اطلاعات تماس محرمانه</span>
                    </div>
                    <p class="mt-2 text-xs">برای مشاهده اطلاعات تماس، به اشتراک ویژه ارتقا دهید.</p>
                </div>
            @endif
        </div>

        {{-- ═══════ CATEGORIES FILTER BAR (بالای صفحه) ═══════ --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 sm:p-5 mb-6 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 shadow-md">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800 text-sm sm:text-base">دسته‌بندی محصولات</h3>
                    <p class="text-xs text-gray-400">انتخاب دسته‌بندی برای مشاهده محصولات</p>
                </div>
            </div>

            {{-- Categories Chips --}}
            <div class="flex gap-2 overflow-x-auto pb-2 -mx-1 px-1 scrollbar-hide">
                <a href="{{ route('suppliers.show', $supplier->slug) }}"
                   class="shrink-0 group relative px-4 sm:px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all duration-300 {{ !request('category') ? 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/30 scale-105' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    <span class="relative z-10 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        همه محصولات
                        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-bold {{ !request('category') ? 'bg-white/20' : 'bg-gray-200 text-gray-600' }}">
                            {{ $supplierStats['total_products'] ?? 0 }}
                        </span>
                    </span>
                </a>
                
                @foreach($supplierCategories as $cat)
                    <a href="{{ route('suppliers.show', ['slug' => $supplier->slug, 'category' => $cat->slug]) }}"
                       class="shrink-0 group relative px-4 sm:px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all duration-300 {{ request('category') == $cat->slug ? 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/30 scale-105' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                        <span class="relative z-10 flex items-center gap-2">
                            @if($cat->icon)
                                <span class="text-base">{{ $cat->icon }}</span>
                            @endif
                            {{ $cat->name }}
                            <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-bold {{ request('category') == $cat->slug ? 'bg-white/20' : 'bg-gray-200 text-gray-600' }}">
                                {{ $cat->products_count }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>

            {{-- Price Range Info --}}
            @if(($supplierStats['min_price'] ?? 0) > 0)
                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-4 flex-wrap">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-xs text-gray-500">بازه قیمت:</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="font-bold text-emerald-600">{{ PriceHelper::formatNumber($supplierStats['min_price']) }}</span>
                        <span class="text-gray-400">تا</span>
                        <span class="font-bold text-rose-600">{{ PriceHelper::formatNumber($supplierStats['max_price']) }}</span>
                        <span class="text-gray-400">تومان</span>
                    </div>
                </div>
            @endif
        </div>

        {{-- ═══════ PRODUCTS SECTION ═══════ --}}
        <div>
                {{-- Sort Bar --}}
                <div class="flex items-center justify-between mb-4 sm:mb-6 gap-3">
                    <p class="text-xs sm:text-sm text-gray-500">
                        <span class="font-bold text-gray-700">{{ number_format($products->total()) }}</span> محصول
                    </p>
                    <form method="GET" class="flex items-center gap-2">
                        @if(request('category'))
                            <input type="hidden" name="category" value="{{ request('category') }}">
                        @endif
                        @if(request('q'))
                            <input type="hidden" name="q" value="{{ request('q') }}">
                        @endif
                        @if(request('per_page'))
                            <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                        @endif
                        <select name="sort" onchange="this.form.submit()"
                                class="border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="latest"   {{ $sortBy == 'latest'   ? 'selected' : '' }}>جدیدترین</option>
                            <option value="popular"  {{ $sortBy == 'popular'  ? 'selected' : '' }}>محبوب‌ترین</option>
                            <option value="price_low" {{ $sortBy == 'price_low' ? 'selected' : '' }}>ارزان‌ترین</option>
                            <option value="price_high" {{ $sortBy == 'price_high' ? 'selected' : '' }}>گران‌ترین</option>
                            <option value="name_asc" {{ $sortBy == 'name_asc' ? 'selected' : '' }}>نام (الف-ی)</option>
                        </select>
                    </form>
                </div>

                @if($products->isNotEmpty())
                    {{-- Products Grid با طراحی بهبود یافته --}}
                    {{-- موبایل: 1 ستون | تبلت: 2 ستون | دسکتاپ: 3 ستون (2 ستون بیشتر از موبایل) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                        @foreach($products as $product)
                            <x-product.card :product="$product" :supplier="$supplier" />
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-8">
                        {{ $products->links() }}
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-gray-200 p-8 sm:p-16 text-center">
                        <svg class="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-gray-200 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <h3 class="text-base sm:text-lg font-bold text-gray-600 mb-2">محصولی یافت نشد</h3>
                        <p class="text-gray-400 text-xs sm:text-sm">این تأمین‌کننده هنوز محصولی در این دسته‌بندی ثبت نکرده است</p>
                    </div>
                @endif
        </div>

    </div>
</div>

{{-- Hide scrollbar for horizontal category scroll --}}
<style>
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
@endsection
