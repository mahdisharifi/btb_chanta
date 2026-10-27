<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - تکمیل محتوای محصول</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-indigo-700">
            <x-heroicon-o-clipboard-document-check class="h-6 w-6" />
            <span>سامانه تکمیل محتوا</span>
        </a>
        <nav class="flex flex-wrap items-center gap-2 text-sm">
            @can('outsourcing.view_tasks')
                @if(auth()->user()?->hasRole('reviewer'))
                    <a class="rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('reviewer.outsourcing.tasks.index') }}">بررسی‌ها</a>
                @else
                    <a class="rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('outsourcer.tasks.index') }}">تسک‌های من</a>
                @endif
            @endcan
            @can('outsourcing.view_reviewed_commissions')
                <a class="rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('reviewer.outsourcing.commissions.index') }}">پورسانت‌های بررسی‌شده</a>
            @endcan
            @can('outsourcing.view_own_commissions')
                <a class="rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('outsourcer.settlements.index') }}">صورتحساب‌ها و تسویه‌ها</a>
                <a class="rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('outsourcer.commissions.index') }}">پورسانت من</a>
            @endcan
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-3 py-2 text-red-600 hover:bg-red-50">خروج</button></form>
        </nav>
    </div>
</header>
<main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6">{{ $slot }}</main>
<div x-data="{ items: [], add(e) { const d = Array.isArray(e.detail) ? e.detail[0] : e.detail; const id = Date.now() + Math.random(); this.items.push({ id, message: d.message, type: d.type || 'success' }); setTimeout(() => this.items = this.items.filter(i => i.id !== id), 6000); } }"
     @notify.window="add($event)"
     class="pointer-events-none fixed inset-x-0 top-4 z-[110] flex flex-col items-center gap-2 px-4" aria-live="polite">
    <template x-for="item in items" :key="item.id">
        <div class="pointer-events-auto w-full max-w-md rounded-xl px-4 py-3 text-sm leading-7 shadow-lg"
             :class="item.type === 'error' ? 'bg-red-700 text-white' : 'bg-emerald-700 text-white'"
             :role="item.type === 'error' ? 'alert' : 'status'">
            <div class="flex items-start justify-between gap-3">
                <span class="break-words" x-text="item.message"></span>
                <button type="button" class="min-h-8 shrink-0 px-2 text-lg leading-none" aria-label="بستن" @click="items = items.filter(i => i.id !== item.id)">×</button>
            </div>
        </div>
    </template>
</div>
@livewireScripts
</body>
</html>
