@props(['title' => 'راهنمای استفاده'])
<div x-data="{ open: false }" class="mb-5 rounded-xl border border-blue-200 bg-blue-50">
    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 p-4 text-right text-sm font-semibold text-blue-900">
        <span class="flex items-center gap-2"><x-heroicon-o-information-circle class="h-5 w-5" />{{ $title }}</span>
        <x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="open && 'rotate-180'" />
    </button>
    <div x-cloak x-show="open" x-collapse class="border-t border-blue-200 p-4 text-sm leading-7 text-blue-900">{{ $slot }}</div>
</div>
