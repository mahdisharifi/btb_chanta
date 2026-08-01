@extends('layouts.guest')

@section('title', 'ورود')

@section('content')
<h2 class="text-2xl font-bold text-center text-gray-900 mb-8">
    ورود به حساب کاربری
</h2>

<form method="POST" action="{{ route('login') }}" class="space-y-6">
    @csrf

    {{-- Username (Email or Mobile) --}}
    <div>
        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
            ایمیل یا موبایل
        </label>
        <input
            type="text"
            name="username"
            id="username"
            value="{{ old('username') }}"
            required
            autofocus
            autocomplete="username"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm
                   @error('username') border-red-500 @enderror
                   @error('email') border-red-500 @enderror"
            placeholder="09xxxxxxxxx یا email@example.com"
        >
        @error('username')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('email')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Password --}}
    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
            رمز عبور
        </label>
        <input
            type="password"
            name="password"
            id="password"
            required
            autocomplete="current-password"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm
                   @error('password') border-red-500 @enderror"
            placeholder="رمز عبور"
        >
        @error('password')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Remember Me --}}
    <div class="flex items-center">
        <input
            type="checkbox"
            name="remember"
            id="remember"
            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
        >
        <label for="remember" class="mr-2 block text-sm text-gray-900">
            مرا به خاطر بسپار
        </label>
    </div>

    {{-- Submit --}}
    <button
        type="submit"
        class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium
               text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500
               transition duration-150"
    >
        ورود
    </button>
</form>

{{-- Links --}}
<div class="mt-6 flex items-center justify-between text-sm">
    <a href="{{ route('password.mobile') }}" class="text-blue-600 hover:text-blue-500">
        رمز عبور را فراموش کرده‌اید؟
    </a>
    <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-500">
        ثبت‌نام
    </a>
</div>
@endsection
