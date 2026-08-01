<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تایید موبایل</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">

        <!-- لوگو -->
        <div class="text-center">
            <x-application-logo class="mx-auto h-12 w-auto" />
            <h2 class="mt-6 text-3xl font-bold text-gray-900">
                تایید شماره موبایل
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                کد تایید به شماره <strong class="font-bold">{{ $mobile }}</strong> ارسال شد
            </p>
        </div>

        <!-- نمایش پیام‌ها -->
        @if (session('success'))
        <div class="rounded-md bg-green-50 p-4 border border-green-200">
            <div class="flex">
                <x-heroicon-s-check-circle class="h-5 w-5 text-green-400" />
                <div class="mr-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
        @endif

        @if (session('error'))
        <div class="rounded-md bg-red-50 p-4 border border-red-200">
            <div class="flex">
                <x-heroicon-s-x-circle class="h-5 w-5 text-red-400" />
                <div class="mr-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
        @endif

        @if (session('info'))
        <div class="rounded-md bg-blue-50 p-4 border border-blue-200">
            <div class="flex">
                <x-heroicon-s-information-circle class="h-5 w-5 text-blue-400" />
                <div class="mr-3">
                    <p class="text-sm font-medium text-blue-800">{{ session('info') }}</p>
                </div>
            </div>
        </div>
        @endif

        <!-- فرم تایید کد -->
        <form method="POST" action="{{ route('verify.mobile.verify') }}" class="mt-8 space-y-6">
            @csrf

            <div>
                <label for="code" class="block text-sm font-medium text-gray-700">
                    کد تایید 6 رقمی
                </label>
                <div class="mt-1">
                    <input
                        id="code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        pattern="\d{6}"
                        required
                        autofocus
                        class="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-center text-2xl tracking-widest font-mono @error('code') border-red-500 @enderror"
                        placeholder="------"
                        value="{{ old('code') }}"
                    >
                </div>
                @error('code')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <button
                    type="submit"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                        <span class="absolute right-0 inset-y-0 flex items-center pr-3">
                            <x-heroicon-s-lock-closed class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" />
                        </span>
                    تایید کد
                </button>
            </div>
        </form>

        <!-- ارسال مجدد -->
        <div class="text-center">
            <form method="POST" action="{{ route('verify.mobile.resend') }}" class="inline">
                @csrf
                <button
                    type="submit"
                    class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors"
                >
                    ارسال مجدد کد
                </button>
            </form>

            <span class="text-gray-500 mx-2">|</span>

            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button
                    type="submit"
                    class="font-medium text-gray-600 hover:text-gray-500 transition-colors"
                >
                    خروج از حساب
                </button>
            </form>
        </div>

        <!-- راهنما -->
        <div class="mt-6 p-4 bg-gray-100 rounded-lg">
            <div class="flex">
                <x-heroicon-o-information-circle class="h-5 w-5 text-gray-400 ml-2" />
                <div class="text-sm text-gray-600">
                    <p>کد تایید به شماره موبایل شما پیامک شد.</p>
                    <p class="mt-1">در صورت دریافت نکردن، از قسمت "ارسال مجدد" استفاده کنید.</p>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- اسکریپت برای فوکوس خودکار و پذیرش فقط عدد -->
<script>
    const codeInput = document.getElementById('code');

    // فقط اعداد بپذیرد
    codeInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // submit خودکار بعد از 6 رقم
    codeInput.addEventListener('input', function(e) {
        if (this.value.length === 6) {
            this.form.submit();
        }
    });
</script>
</body>
</html>
