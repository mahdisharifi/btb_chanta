@props([
'title' => '',
'value' => 0,
'icon' => 'chart-bar',
'color' => 'blue',
'route' => null,
'subtitle' => null,
'highlight' => false,
])

@php
$colorClasses = [
'blue'   => 'bg-blue-50 text-blue-600 border-blue-200',
'green'  => 'bg-green-50 text-green-600 border-green-200',
'purple' => 'bg-purple-50 text-purple-600 border-purple-200',
'amber'  => 'bg-amber-50 text-amber-600 border-amber-200',
'rose'   => 'bg-rose-50 text-rose-600 border-rose-200',
'emerald'=> 'bg-emerald-50 text-emerald-600 border-emerald-200',
'orange' => 'bg-orange-50 text-orange-600 border-orange-200',
];

$iconPaths = [
'package'      => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
'truck'        => 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m10 0h5m-9 0H4m5 0h5m-9-5h10',
'folder-tree'  => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
'users'        => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
'tag'          => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
'check-circle' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
'clock'        => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
'chart-bar'    => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
];

$colorClass = $colorClasses[$color] ?? $colorClasses['blue'];
$iconPath = $iconPaths[$icon] ?? $iconPaths['chart-bar'];
$cardClasses = $highlight
? 'bg-white rounded-xl shadow-md border-r-4 border-orange-400 overflow-hidden transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5'
: 'bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md hover:-translate-y-0.5';
@endphp

@if($route)
<a href="{{ $route }}" class="block {{ $cardClasses }}">
    @else
    <div class="{{ $cardClasses }}">
        @endif

        <div class="p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">{{ $title }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($value) }}</p>
                    @if($subtitle)
                    <p class="text-xs text-gray-400 mt-1.5">{{ $subtitle }}</p>
                    @endif
                </div>
                <div class="w-12 h-12 rounded-lg {{ $colorClass }} border flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
                    </svg>
                </div>
            </div>
        </div>

        @if($highlight)
        <div class="bg-orange-50 px-5 py-1.5 border-t border-orange-100">
            <span class="text-xs text-orange-600 font-medium">نیاز به اقدام</span>
        </div>
        @endif

        @if($route)
</a>
@else
</div>
@endif
