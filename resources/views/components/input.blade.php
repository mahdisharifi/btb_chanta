@props([
'label' => null,
'icon' => null,
'disabled' => false,
])

@php
$wireModel = $attributes->get('wire:model') ?? $attributes->wire('model')->value() ?? null;
@endphp

<div>
    @if($label)
    <x-input-label :value="$label" />
    @endif

    <div class="mt-1 relative">
        @if($icon)
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="{{ match($icon) {
                              'user'  => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
                              'mail'  => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75',
                              default => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
                          } }}" />
            </svg>
        </div>
        @endif

        <input
            @disabled($disabled)
            {{ $attributes->merge([
        'class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full'
        . ($icon ? ' pr-10' : '')
        ]) }}
        >
    </div>

    @if($wireModel)
    @error($wireModel)
    <x-input-error :messages="$message" class="mt-2" />
    @enderror
    @endif
</div>
