@props([
'label' => null,
'disabled' => false,
])

@php
$wireModel = $attributes->get('wire:model') ?? $attributes->wire('model')->value() ?? null;
@endphp

<div class="flex items-center gap-3" x-data="{ on: @entangle($wireModel) }">
    <button
        type="button"
        role="switch"
        x-ref="toggle"
        x-init="on = !!on"
        :aria-checked="on"
        @click="on = !on"
        @disabled($disabled)
        {{ $attributes->only(['wire:model', 'wire:model.live', 'wire:model.defer'])->merge([
        'class' => 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
        ':class' => "{ 'bg-indigo-600': on, 'bg-gray-200': !on }",
        ]) }}
        >
        <span
            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            :class="{ 'rtl:-translate-x-5 ltr:translate-x-5': on, 'translate-x-0': !on }"
        ></span>
    </button>

    @if($label)
    <span class="text-sm cursor-pointer text-gray-700 select-none" @click="$refs.toggle?.click()">
            {{ $label }}
        </span>
    @endif
</div>
