@props([
'title' => '',
'subtitle' => null,
'breadcrumb' => [],
'breadcrumbs' => [],

])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6']) }}>
    <div>
        {{-- Breadcrumb --}}
        @if(!empty($breadcrumb))
        <nav class="flex mb-1" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 rtl:space-x-reverse text-sm text-gray-500">
                @foreach($breadcrumb as $key => $item)
                <li class="inline-flex items-center">
                    @if($key > 0)
                    <x-icon name="heroicon-o-chevron-left" class="w-3.5 h-3.5 mx-1 text-gray-400" />
                    @endif

                    @if(isset($item['url']))
                    <a href="{{ $item['url'] }}" class="hover:text-primary-600 transition">
                        {{ $item['name'] }}
                    </a>
                    @else
                    <span class="text-gray-900 font-medium">{{ $item['name'] }}</span>
                    @endif
                </li>
                @endforeach
            </ol>
        </nav>
        @endif

        {{-- عنوان --}}
        <h2 class="text-xl font-semibold text-gray-900 font-[Figtree]">
            {{ $title }}
        </h2>

        {{-- زیرعنوان --}}
        @if($subtitle)
        <p class="mt-1 text-sm text-gray-500">
            {{ $subtitle }}
        </p>
        @endif
    </div>

    @if($slot->isNotEmpty())
    <div class="flex items-center gap-3">
        {{ $slot }}
    </div>
    @endif
</div>
