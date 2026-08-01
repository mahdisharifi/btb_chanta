<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="{{ $description ?? '' }}">
    <title>@yield('title', config('app.name', 'پنل مدیریت'))</title>

    @livewireStyles

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased bg-gray-100 min-h-screen flex flex-col items-center justify-center p-4">

<div class="w-full max-w-md">
    {{-- Brand / Logo --}}
    <div class="text-center mb-6">
        <a href="{{ route('home') }}" class="inline-block">
            <x-application-logo class="w-16 h-16 mx-auto fill-current text-[#d4af37]" />
        </a>
    </div>

    {{-- Main Card --}}
    <div class="bg-white shadow-md rounded-xl px-8 py-8">

        {{-- Validation Errors --}}
        @if ($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @yield('content')

        @if(isset($slot))
        {{ $slot }}
        @endif
    </div>
</div>

@livewireScripts
</body>
</html>
