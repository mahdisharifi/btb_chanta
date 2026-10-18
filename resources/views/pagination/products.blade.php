@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex justify-center">
        <div class="flex max-w-full flex-wrap items-center justify-center gap-1" dir="rtl">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="inline-flex min-h-10 items-center rounded-lg border border-gray-200 bg-gray-100 px-3 text-xs text-gray-400">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="inline-flex min-h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 hover:border-blue-300 hover:text-blue-700">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true" class="inline-flex min-h-10 min-w-9 items-center justify-center px-2 text-sm text-gray-400">{{ $element }}</span>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-blue-600 bg-blue-600 px-3 text-sm font-bold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="رفتن به صفحه {{ $page }}" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="inline-flex min-h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 hover:border-blue-300 hover:text-blue-700">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span aria-disabled="true" class="inline-flex min-h-10 items-center rounded-lg border border-gray-200 bg-gray-100 px-3 text-xs text-gray-400">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>
    </nav>
@endif
