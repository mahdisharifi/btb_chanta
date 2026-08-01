<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>انتخاب کاربر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Vazir', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 min-h-screen p-6">
<div class="max-w-6xl mx-auto">
    <div class="bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 overflow-hidden">
        <div class="bg-gradient-to-r from-yellow-600 to-yellow-700 p-6">
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <x-heroicon-o-users class="w-8 h-8"/>
                انتخاب کاربر برای لاگین
            </h1>
            <p class="text-yellow-100 text-sm mt-2">کاربر مورد نظر را انتخاب کنید</p>
        </div>

        @if(session('error'))
        <div class="m-6 p-4 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400">
            {{ session('error') }}
        </div>
        @endif

        <div class="p-6">
            <div class="grid gap-3">
                @forelse($users as $user)
                <form method="POST" action="{{ route('dev.force-login.execute', $user->id) }}" class="contents">
                    @csrf
                    <button
                        type="submit"
                        class="w-full bg-gray-700 hover:bg-gray-600 border border-gray-600 rounded-xl p-4 transition duration-200 text-right group"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="bg-yellow-500/20 rounded-full p-3 group-hover:bg-yellow-500/30 transition">
                                    <x-heroicon-o-user class="w-6 h-6 text-yellow-500"/>
                                </div>
                                <div>
                                    <h3 class="text-white font-semibold text-lg">{{ $user->name }}</h3>
                                    <p class="text-gray-400 text-sm">{{ $user->email }}</p>
                                    <div class="flex items-center gap-2 mt-2">
                                        @foreach($user->roles as $role)
                                        <span class="px-2 py-1 bg-blue-500/20 text-blue-400 text-xs rounded-full border border-blue-500/30">
                                                        {{ $role->display_name ?? $role->name }}
                                                    </span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="text-gray-400 group-hover:text-yellow-500 transition">
                                <x-heroicon-o-arrow-left class="w-5 h-5"/>
                            </div>
                        </div>
                    </button>
                </form>
                @empty
                <div class="text-center py-12 text-gray-500">
                    <x-heroicon-o-user-group class="w-16 h-16 mx-auto mb-4 opacity-50"/>
                    <p>هیچ کاربری یافت نشد</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
</body>
</html>
