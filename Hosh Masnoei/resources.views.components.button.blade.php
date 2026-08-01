@props([
'type' => 'button',
'primary' => false,
'secondary' => false,
'negative' => false,
'flat' => false,
'icon' => null,
'href' => null,
'size' => 'md',
])

@php
$base = 'inline-flex items-center justify-center gap-2 font-medium rounded-md transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

$sizes = [
'sm' => 'px-3 py-1.5 text-xs',
'md' => 'px-4 py-2 text-sm',
'lg' => 'px-6 py-3 text-base',
];

$colorClasses = match(true) {
$negative && $flat => 'text-red-600 hover:bg-red-50 focus:ring-red-500',
$negative => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 shadow-sm',
$flat => 'text-gray-700 hover:bg-gray-100 focus:ring-indigo-500',
$secondary => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-indigo-500 shadow-sm',
default => 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500 shadow-sm', // primary
};

$classes = $base . ' ' . ($sizes[$size] ?? $sizes['md']) . ' ' . $colorClasses;
@endphp

@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon === 'save' ? 'M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z' : ($icon === 'x' ? 'M6 18L18 6M6 6l12 12' : 'M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3') }}" />
    </svg>
    @endif
    {{ $slot }}
</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon === 'save' ? 'M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z' : ($icon === 'x' ? 'M6 18L18 6M6 6l12 12' : 'M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3') }}" />
    </svg>
    @endif
    {{ $slot }}
</button>
@endif
