@props([
'label' => null,
'options' => [],
'disabled' => false,
'placeholder' => 'انتخاب کنید...',
])

@php
$wireModel = $attributes->get('wire:model') ?? $attributes->wire('model')->value() ?? null;
@endphp

<div>
    @if($label)
    <x-input-label :value="$label" />
    @endif

    <div class="mt-1">
        <select
            @disabled($disabled)
            {{ $attributes->merge([
            'class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full'
            ]) }}
            >
            @if($placeholder)
            <option value="">{{ $placeholder }}</option>
            @endif

            @foreach($options as $key => $value)
            @if(is_array($value) && isset($value['value']) && isset($value['label']))
            <option value="{{ $value['value'] }}">{{ $value['label'] }}</option>
            @elseif(is_array($value) && isset($value['value']))
            <option value="{{ $value['value'] }}">{{ $value['value'] }}</option>
            @else
            <option value="{{ $key }}">{{ $value }}</option>
            @endif
            @endforeach
        </select>
    </div>

    @if($wireModel)
    @error($wireModel)
    <x-input-error :messages="$message" class="mt-2" />
    @enderror
    @endif
</div>
