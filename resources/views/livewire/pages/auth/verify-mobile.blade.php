@php
    $active = $codeExpiresAt !== null;
    $sendError = ($sendResult && empty($sendResult['success'])) ? $sendResult['message'] : session('error');
    $codeError = session('errors')?->first('code');
    $remaining = $active ? max(0, (int) ceil(now()->diffInSeconds(\Carbon\Carbon::parse($codeExpiresAt), false))) : (int) $retryAfter;
    $clock = sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60);
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="{{ asset('favicon.jpg') }}">
    <title>تأیید شماره موبایل</title>
    @vite('resources/css/app.css')
    <style>
        [hidden] { display: none !important; }
        .otp-panel { min-height: 90px; }
        #otp-notice { min-height: 86px; }
        .otp-action { min-height: 48px; }
        .otp-clock { font-variant-numeric: tabular-nums; }
    </style>
    <script src="{{ asset('js/verify-mobile.js') }}?v={{ filemtime(public_path('js/verify-mobile.js')) }}" defer></script>
</head>
<body class="bg-gray-50 text-gray-900">
<main class="min-h-screen flex items-center justify-center py-8 px-4 sm:py-12">
    <section class="max-w-md w-full space-y-6" aria-labelledby="page-title">
        <header class="text-center">
            <x-registration-logo class="mx-auto h-20 w-20" />
            <h1 id="page-title" class="mt-6 text-2xl font-bold">تأیید شماره موبایل</h1>
            <p class="mt-3 text-sm text-gray-600">شماره موبایل شما: <bdi class="font-bold text-gray-900" dir="ltr">{{ $mobile }}</bdi></p>
        </header>

        <div id="otp-notice" class="rounded-lg border border-gray-200 bg-white p-4 text-sm leading-6" role="status" aria-live="polite" aria-atomic="true">
            @if ($sendError)
                <p class="text-red-700">{{ $sendError }}</p>
            @elseif ($active)
                <p class="font-medium text-green-700">کد تأیید ارسال شد.</p>
                <p class="mt-1 text-gray-600">کد ۶ رقمی پیامک‌شده را وارد کنید.</p>
            @elseif ($retryAfter > 0)
                <p class="text-gray-700">فعلاً امکان ارسال کد جدید نیست. زمان باقی‌مانده را در پایین ببینید.</p>
            @else
                <p class="text-gray-700">کد فعالی ندارید. برای دریافت کد جدید، «ارسال مجدد کد» را بزنید.</p>
            @endif
        </div>

        <form id="otp-form" method="POST" action="{{ $active ? route('verify.mobile.verify') : route('verify.mobile.resend') }}" class="space-y-4"
              data-expires-at="{{ $codeExpiresAt }}" data-server-now="{{ now()->toIso8601String() }}"
              data-retry-after="{{ (int) $retryAfter }}" data-resend-action="{{ route('verify.mobile.resend') }}"
              data-verify-action="{{ route('verify.mobile.verify') }}">
            @csrf
            <div class="otp-panel">
                <div id="otp-entry" @if (!$active) hidden @endif>
                    <label for="code" class="block text-sm font-medium text-gray-700">کد تأیید ۶ رقمی</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr"
                           maxlength="6" pattern="[0-9۰-۹٠-٩]{6}" @if ($active) required autofocus @else disabled @endif
                           aria-describedby="otp-code-error" aria-invalid="{{ $codeError ? 'true' : 'false' }}"
                           class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-3 text-center text-2xl tracking-widest focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="------" value="">
                </div>
                <p id="otp-expired" class="rounded-lg bg-gray-100 p-4 text-sm leading-6 text-gray-700" @if ($active) hidden @endif>برای ادامه، کد جدید دریافت کنید. فقط آخرین کد ارسال‌شده معتبر است.</p>
            </div>
            <p id="otp-code-error" class="text-sm text-red-700" role="alert" @if (!$codeError) hidden @endif>{{ $codeError }}</p>

            <p class="h-6 text-center text-sm text-gray-600">
                <span id="otp-timer-label">{{ $active ? 'اعتبار کد:' : ($retryAfter > 0 ? 'ارسال مجدد تا:' : 'آمادهٔ ارسال کد جدید') }}</span>
                <bdi id="otp-clock" dir="ltr" class="otp-clock font-bold text-gray-900" @if (!$active && $retryAfter <= 0) hidden @endif>{{ $clock }}</bdi>
            </p>
            <button id="otp-action" type="submit" @disabled(!$active && $retryAfter > 0)
                    class="otp-action flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                {{ $active ? 'تأیید کد' : 'ارسال مجدد کد' }}
            </button>
            <noscript><p class="text-sm text-gray-600">برای نمایش شمارش معکوس، جاوااسکریپت مرورگر را فعال کنید یا پس از پایان زمان صفحه را تازه کنید.</p></noscript>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="min-h-[44px] px-3 text-sm font-medium text-gray-600 hover:text-gray-900">خروج از حساب</button>
        </form>
    </section>
</main>
</body>
</html>
