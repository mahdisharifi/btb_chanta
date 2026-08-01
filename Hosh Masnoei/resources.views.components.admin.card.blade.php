@props([
'header' => null,
'padding' => 'p-6',
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-200']) }}>
    {{-- Header Card --}}
    @if($header)
    <div class="px-6 py-4 border-b border-gray-200">
        @if(is_string($header))
        <h3 class="text-lg font-medium text-gray-900">{{ $header }}</h3>
        @else
        {!! $header !!}
        @endif
    </div>
    @endif

    {{-- Body Card --}}
    <div class="{{ $padding }}">
        {{ $slot }}
    </div>

    {{-- Footer Slot (اختیاری) --}}
    @if(isset($footer) && $footer->isNotEmpty())
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
        {{ $footer }}
    </div>
    @endif
</div>
