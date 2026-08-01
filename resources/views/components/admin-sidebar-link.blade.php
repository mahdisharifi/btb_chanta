@props(['href' => '#', 'active' => false])

<a
    href="{{ $href }}"
    @class([
'flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150',
'bg-blue-600 text-white shadow-md' => $active,
'text-gray-300 hover:bg-gray-800 hover:text-white' => !$active,
])
>
{{ $slot }}
</a>
