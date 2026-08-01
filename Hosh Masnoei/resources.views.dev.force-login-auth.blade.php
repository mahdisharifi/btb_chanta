<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>احراز هویت توسعه‌دهنده</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Vazir', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 min-h-screen flex items-center justify-center">
<div class="bg-gray-800 p-8 rounded-2xl shadow-2xl w-full max-w-md border border-gray-700">
    <div class="text-center mb-6">
        <div class="bg-yellow-500/10 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
            <x-heroicon-o-shield-check class="w-10 h-10 text-yellow-500"/>
        </div>
        <h1 class="text-2xl font-bold text-white mb-2">احراز هویت توسعه‌دهنده</h1>
        <p class="text-gray-400 text-sm">کد امنیتی را وارد کنید</p>
    </div>

    <form method="POST" action="{{ route('dev.force-login.verify') }}">
        @csrf
        <div class="mb-6">
            <label for="code" class="block text-sm font-medium text-gray-300 mb-2">کد امنیتی</label>
            <input
                type="password"
                id="code"
                name="code"
                value="{{ old('code') }}"
                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition"
                placeholder="کد را وارد کنید"
                autofocus
                required
            >
            @error('code')
            <p class="mt-2 text-sm text-red-400 flex items-center gap-1">
                <x-heroicon-o-x-circle class="w-4 h-4"/>
                {{ $message }}
            </p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-semibold py-3 rounded-lg transition duration-200 flex items-center justify-center gap-2"
        >
            <x-heroicon-o-lock-open class="w-5 h-5"/>
            تایید و ادامه
        </button>
    </form>

    <div class="mt-6 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
        <p class="text-xs text-red-400 flex items-start gap-2">
            <x-heroicon-o-exclamation-triangle class="w-4 h-4 mt-0.5 flex-shrink-0"/>
            <span>این ویژگی فقط برای محیط توسعه است. در production غیرفعال شود.</span>
        </p>
    </div>
</div>
</body>
</html>
