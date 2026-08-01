<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود موفقیت‌آمیز</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Vazir', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-green-900 via-gray-800 to-gray-900 min-h-screen flex items-center justify-center p-6">
<div class="bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 w-full max-w-2xl overflow-hidden">
    <div class="bg-gradient-to-r from-green-600 to-green-700 p-8 text-center">
        <div class="bg-white/20 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
            <x-heroicon-o-check-circle class="w-12 h-12 text-white"/>
        </div>
        <h1 class="text-3xl font-bold text-white mb-2">ورود موفقیت‌آمیز</h1>
        <p class="text-green-100">شما با موفقیت وارد سیستم شدید</p>
    </div>

    <div class="p-8">
        <div class="space-y-4 mb-6">
            <div class="bg-gray-700 rounded-lg p-4">
                <label class="text-gray-400 text-sm block mb-1">نام کاربر</label>
                <p class="text-white font-semibold text-lg">{{ $user->name }}</p>
            </div>

            <div class="bg-gray-700 rounded-lg p-4">
                <label class="text-gray-400 text-sm block mb-1">ایمیل</label>
                <p class="text-white">{{ $user->email }}</p>
            </div>

            <div class="bg-gray-700 rounded-lg p-4">
                <label class="text-gray-400 text-sm block mb-2 flex items-center gap-2">
                    <x-heroicon-o-shield-check class="w-4 h-4"/>
                    نقش‌ها
                </label>
                <p class="text-white">{{ $roles }}</p>
            </div>

            @if($permissions)
            <div class="bg-gray-700 rounded-lg p-4">
                <label class="text-gray-400 text-sm block mb-2 flex items-center gap-2">
                    <x-heroicon-o-key class="w-4 h-4"/>
                    مجوزها
                </label>
                <p class="text-white text-sm">{{ $permissions }}</p>
            </div>
            @endif

            <div class="bg-gray-700 rounded-lg p-4">
                <label class="text-gray-400 text-sm block mb-1">شناسه کاربر</label>
                <p class="text-white font-mono">{{ $user->id }}</p>
            </div>
        </div>

        <a
            href="/dashboard"
            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-4 rounded-lg transition duration-200 flex items-center justify-center gap-2 group"
        >
            <span>رفتن به داشبورد</span>
            <x-heroicon-o-arrow-left class="w-5 h-5 group-hover:translate-x-1 transition-transform"/>
        </a>
    </div>
</div>
</body>
</html>
