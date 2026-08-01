@props([
'type' => 'button',
'href' => null,
'disabled' => false,
'size' => 'md',
'icon' => null,
'iconPosition' => 'left',
])

@php
// کلاس‌های سایز
$sizeClasses = [
'sm' => 'px-3 py-1.5 text-sm',
'md' => 'px-4 py-2 text-base',
'lg' => 'px-6 py-3 text-lg',
];

// کلاس‌های پایه
$baseClasses = 'inline-flex items-center justify-center font-medium rounded-lg border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

// کلاس‌های رنگ برای دکمه ثانویه
$colorClasses = 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:border-gray-400 focus:ring-gray-500';

// کلاس‌های نهایی
$classes = $baseClasses . ' ' . $colorClasses . ' ' . ($sizeClasses[$size] ?? $sizeClasses['md']);
@endphp

@if($href)
<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => $classes]) }}
    @if($disabled) aria-disabled="true" @endif
    >
    @if($icon && $iconPosition === 'left')
    <x-dynamic-component :component="$icon" class="w-4 h-4 mr-2" />
    @endif
    {{ $slot }}
    @if($icon && $iconPosition === 'right')
    <x-dynamic-component :component="$icon" class="w-4 h-4 ml-2" />
    @endif
</a>
@else
<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => $classes]) }}
    @if($disabled) disabled @endif
    >
    @if($icon && $iconPosition === 'left')
    <x-dynamic-component :component="$icon" class="w-4 h-4 mr-2" />
    @endif
    {{ $slot }}
    @if($icon && $iconPosition === 'right')
    <x-dynamic-component :component="$icon" class="w-4 h-4 ml-2" />
    @endif
</button>
@endif
