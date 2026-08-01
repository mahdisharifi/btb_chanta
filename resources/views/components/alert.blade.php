@props([
'type' => 'info',
'message' => null,
'dismissible' => false,
])

@php
$types = [
'success' => 'bg-green-50 border-green-400 text-green-800 dark:bg-green-900/20 dark:border-green-600 dark:text-green-400',
'error'   => 'bg-red-50 border-red-400 text-red-800 dark:bg-red-900/20 dark:border-red-600 dark:text-red-400',
'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800 dark:bg-yellow-900/20 dark:border-yellow-600 dark:text-yellow-400',
'info'    => 'bg-blue-50 border-blue-400 text-blue-800 dark:bg-blue-900/20 dark:border-blue-600 dark:text-blue-400',
];

$icon = [
'success' => '✅',
'error'   => '❌',
'warning' => '⚠️',
'info'    => 'ℹ️',
];
@endphp

<div x-data="{ show: true }" x-show="show"
     {{ $attributes->merge(['class' => 'flex items-center justify-between p-4 border rounded-lg shadow-sm ' . $types[$type]]) }}>

    <div class="flex items-center gap-3">
        <span>{{ $icon[$type] }}</span>
        <span>{{ $message ?? $slot }}</span>
    </div>

    @if ($dismissible)
    <button type="button" @click="show = false" class="text-current opacity-50 hover:opacity-100 transition-opacity">
        ✕
    </button>
    @endif
</div>
