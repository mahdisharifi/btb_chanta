@props([])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700']) }}>
    {{ $slot }}
</div>
