@extends('layouts.guest')

@section('title', 'ثبت‌نام')

@section('content')
<h2 class="text-2xl font-bold text-center text-gray-900 mb-8">
    ایجاد حساب کاربری
</h2>

<form method="POST" action="{{ route('register.store') }}" class="space-y-6">
    @csrf

    {{-- Name --}}
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
            نام و نام خانوادگی
        </label>
        <input
            type="text"
            name="name"
            id="name"
            value="{{ old('name') }}"
            required
            autofocus
            autocomplete="name"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm
                   @error('name') border-red-500 @enderror"
            placeholder="نام کامل"
        >
        @error('name')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Email --}}
    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
            ایمیل
        </label>
        <input
            type="email"
            name="email"
            id="email"
            value="{{ old('email') }}"
            autocomplete="email"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm
                   @error('email') border-red-500 @enderror"
            placeholder="example@domain.com"
        >
        @error('email')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Mobile (اختیاری) --}}
    <div>
        <label for="mobile" class="block text-sm font-medium text-gray-700 mb-1">
            موبایل
        </label>
        <input
            type="text"
            name="mobile"
            id="mobile"
            value="{{ old('mobile') }}"
            autocomplete="tel"
            dir="ltr"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm
                   @error('mobile') border-red-500 @enderror"
            placeholder="09xxxxxxxxx"
        >
        @error('mobile')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-xs text-gray-500">شماره موبایل را با فرمت ایرانی وارد کنید (مثال: 09123456789)</p>
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
            autocomplete="new-password"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm
                   @error('password') border-red-500 @enderror"
            placeholder="حداقل ۸ کاراکتر"
        >
        @error('password')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Confirm Password --}}
    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
            تکرار رمز عبور
        </label>
        <input
            type="password"
            name="password_confirmation"
            id="password_confirmation"
            required
            autocomplete="new-password"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
            placeholder="تکرار رمز عبور"
        >
    </div>

    {{-- Submit --}}
    <button
        type="submit"
        class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium
               text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500
               transition duration-150"
    >
        ثبت‌نام
    </button>
</form>

{{-- Login Link --}}
<p class="mt-6 text-center text-sm text-gray-600">
    قبلاً ثبت‌نام کرده‌اید؟
    <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500">
        وارد شوید
    </a>
</p>
@endsection
